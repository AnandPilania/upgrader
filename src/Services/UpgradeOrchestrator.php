<?php

namespace Upgrader\Services;

use Upgrader\Core\ModuleRegistry;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Orchestrates upgrades across multiple modules and their dependencies
 */
class UpgradeOrchestrator
{
    private ModuleRegistry $registry;
    private SymfonyStyle $io;
    private array $upgraded = [];

    public function __construct(ModuleRegistry $registry, SymfonyStyle $io)
    {
        $this->registry = $registry;
        $this->io = $io;
    }

    /**
     * Upgrade a module and optionally its dependencies
     */
    public function upgrade(
        string $moduleName,
        string $projectPath,
        string $fromVersion,
        string $toVersion,
        array $options = []
    ): array {
        $withDependencies = $options['with_dependencies'] ?? false;
        $dryRun = $options['dry_run'] ?? false;

        $this->io->section("Upgrading {$moduleName}");

        $module = $this->registry->getModule($moduleName);
        if (!$module) {
            return [
                'success' => false,
                'error' => "Module not found: {$moduleName}"
            ];
        }

        // Check if module is applicable
        if (!$module->canHandle($projectPath)) {
            return [
                'success' => false,
                'error' => "Module '{$moduleName}' is not applicable to this project"
            ];
        }

        // Validate upgrade is possible
        if (!$module->canUpgrade($projectPath, $fromVersion, $toVersion)) {
            return [
                'success' => false,
                'error' => "Cannot upgrade from {$fromVersion} to {$toVersion}"
            ];
        }

        $results = [];
        $summary = [];
        $manualSteps = [];

        // 1. Upgrade dependencies first if requested
        if ($withDependencies) {
            $depResults = $this->upgradeDependencies($module, $projectPath, $dryRun);
            $results['dependencies'] = $depResults;
            
            foreach ($depResults as $depName => $depResult) {
                if ($depResult['success']) {
                    $summary[] = "✓ Upgraded dependency: {$depName}";
                } else {
                    $summary[] = "✗ Failed to upgrade dependency: {$depName}";
                }
            }
        }

        // 2. Perform the main upgrade
        try {
            if (!$dryRun) {
                $this->io->text("Performing upgrade...");
                $upgradeResult = $module->upgrade($projectPath, $fromVersion, $toVersion);
                $results['main_upgrade'] = $upgradeResult;

                if ($upgradeResult['success']) {
                    $this->upgraded[$moduleName] = true;
                    $summary[] = "✓ Upgraded {$moduleName} from {$fromVersion} to {$toVersion}";
                    
                    // Collect manual steps from transformers
                    if (isset($upgradeResult['results'])) {
                        $manualSteps = $this->collectManualSteps($upgradeResult['results']);
                    }
                } else {
                    $summary[] = "✗ Failed to upgrade {$moduleName}";
                }
            } else {
                $this->io->text("DRY RUN: Would upgrade {$moduleName} from {$fromVersion} to {$toVersion}");
                $summary[] = "[DRY RUN] Would upgrade {$moduleName}";
            }

            return [
                'success' => true,
                'results' => $results,
                'summary' => $summary,
                'manual_steps' => $manualSteps,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'summary' => $summary,
            ];
        }
    }

    /**
     * Upgrade all dependencies of a module
     */
    private function upgradeDependencies($module, string $projectPath, bool $dryRun): array
    {
        $results = [];
        $dependencies = $module->getDependencies();

        if (empty($dependencies)) {
            return $results;
        }

        $this->io->text("Checking dependencies: " . implode(', ', $dependencies));

        foreach ($dependencies as $depName) {
            // Skip if already upgraded in this session
            if (isset($this->upgraded[$depName])) {
                $results[$depName] = ['success' => true, 'skipped' => 'already_upgraded'];
                continue;
            }

            $depModule = $this->registry->getModule($depName);
            if (!$depModule) {
                $results[$depName] = [
                    'success' => false,
                    'error' => 'Module not found'
                ];
                continue;
            }

            // Check if dependency is applicable
            if (!$depModule->canHandle($projectPath)) {
                $results[$depName] = [
                    'success' => true,
                    'skipped' => 'not_applicable'
                ];
                continue;
            }

            // Detect current version
            $currentVersion = $depModule->detectCurrentVersion($projectPath);
            if (!$currentVersion) {
                $results[$depName] = [
                    'success' => false,
                    'error' => 'Could not detect current version'
                ];
                continue;
            }

            // Get latest version
            $availableVersions = $depModule->getAvailableVersions();
            $targetVersion = end($availableVersions);

            // Check if upgrade needed
            if ($currentVersion === $targetVersion) {
                $results[$depName] = [
                    'success' => true,
                    'skipped' => 'already_latest'
                ];
                continue;
            }

            // Recursively upgrade this dependency
            $this->io->text("Upgrading dependency: {$depName}");
            $result = $this->upgrade(
                $depName,
                $projectPath,
                $currentVersion,
                $targetVersion,
                ['with_dependencies' => true, 'dry_run' => $dryRun]
            );

            $results[$depName] = $result;
        }

        return $results;
    }

    /**
     * Collect manual steps from transformation results
     */
    private function collectManualSteps(array $results): array
    {
        $manualSteps = [];

        foreach ($results as $key => $result) {
            if (is_array($result)) {
                // Check for transformations
                if (isset($result['transformations'])) {
                    foreach ($result['transformations'] as $transformation) {
                        if (isset($transformation['manual_steps'])) {
                            $manualSteps = array_merge($manualSteps, $transformation['manual_steps']);
                        }
                    }
                }

                // Recursively check nested results
                $nested = $this->collectManualSteps($result);
                $manualSteps = array_merge($manualSteps, $nested);
            }
        }

        return array_unique($manualSteps);
    }

    /**
     * Get upgrade plan (what will be upgraded)
     */
    public function getPlan(
        string $moduleName,
        string $projectPath,
        string $fromVersion,
        string $toVersion,
        bool $withDependencies = false
    ): array {
        $module = $this->registry->getModule($moduleName);
        if (!$module) {
            return [];
        }

        $plan = [
            'module' => $moduleName,
            'from' => $fromVersion,
            'to' => $toVersion,
            'path' => $module->getUpgradePath($fromVersion, $toVersion),
            'dependencies' => [],
        ];

        if ($withDependencies) {
            foreach ($module->getDependencies() as $depName) {
                $depModule = $this->registry->getModule($depName);
                if ($depModule && $depModule->canHandle($projectPath)) {
                    $currentVersion = $depModule->detectCurrentVersion($projectPath);
                    $availableVersions = $depModule->getAvailableVersions();
                    $targetVersion = end($availableVersions);

                    if ($currentVersion && $currentVersion !== $targetVersion) {
                        $plan['dependencies'][$depName] = [
                            'from' => $currentVersion,
                            'to' => $targetVersion,
                            'path' => $depModule->getUpgradePath($currentVersion, $targetVersion),
                        ];
                    }
                }
            }
        }

        return $plan;
    }
}
