<?php

namespace Upgrader\Modules\Laravel;

use Upgrader\Core\AbstractModule;
use Upgrader\Modules\PHP\PHPModule;
use Upgrader\Modules\JavaScript\JavaScriptModule;
use Upgrader\Modules\TypeScript\TypeScriptModule;

/**
 * Laravel Framework Upgrade Module
 * Depends on: PHP module, and optionally JavaScript/TypeScript for frontend
 */
class LaravelModule extends AbstractModule
{
    protected array $dependencies = ['php'];

    private array $versions = [
        '8.0', '9.0', '10.0', '11.0', '12.0', '13.0'
    ];

    public function getName(): string
    {
        return 'laravel';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDescription(): string
    {
        return 'Laravel framework upgrade module';
    }

    public function canHandle(string $projectPath): bool
    {
        $composerFile = $projectPath . '/composer.json';
        if (!file_exists($composerFile)) {
            return false;
        }

        $composer = json_decode(file_get_contents($composerFile), true);
        return isset($composer['require']['laravel/framework']);
    }

    public function detectCurrentVersion(string $projectPath): ?string
    {
        $composerFile = $projectPath . '/composer.json';
        if (!file_exists($composerFile)) {
            return null;
        }

        $composer = json_decode(file_get_contents($composerFile), true);
        $version = $composer['require']['laravel/framework'] ?? null;

        if ($version && preg_match('/(\d+)\./', $version, $matches)) {
            return $matches[1] . '.0';
        }

        return null;
    }

    public function getAvailableVersions(): array
    {
        return $this->versions;
    }

    public function analyze(string $projectPath, string $targetVersion): array
    {
        $currentVersion = $this->detectCurrentVersion($projectPath);
        
        // Get PHP module analysis
        $phpModule = $this->getDependency('php');
        $phpAnalysis = null;
        
        if ($phpModule) {
            $requiredPhpVersion = $this->getRequiredPhpVersion($targetVersion);
            $currentPhpVersion = $phpModule->detectCurrentVersion($projectPath);
            
            if (version_compare($currentPhpVersion, $requiredPhpVersion, '<')) {
                $phpAnalysis = $phpModule->analyze($projectPath, $requiredPhpVersion);
            }
        }

        // Detect frontend framework
        $frontendModule = $this->detectFrontendFramework($projectPath);

        return [
            'current_version' => $currentVersion,
            'target_version' => $targetVersion,
            'php_upgrade_required' => $phpAnalysis !== null,
            'php_analysis' => $phpAnalysis,
            'frontend_framework' => $frontendModule,
            'breaking_changes' => $this->getBreakingChanges($currentVersion, $targetVersion),
            'deprecated_features' => $this->getDeprecatedFeatures($currentVersion, $targetVersion),
            'configuration_changes' => $this->getConfigurationChanges($currentVersion, $targetVersion),
            'dependency_updates' => $this->getDependencyUpdates($targetVersion),
        ];
    }

    public function upgrade(string $projectPath, string $fromVersion, string $toVersion): array
    {
        $results = [];

        // 1. Upgrade PHP if needed
        $phpModule = $this->getDependency('php');
        if ($phpModule) {
            $requiredPhpVersion = $this->getRequiredPhpVersion($toVersion);
            $currentPhpVersion = $phpModule->detectCurrentVersion($projectPath);
            
            if (version_compare($currentPhpVersion, $requiredPhpVersion, '<')) {
                $this->log("Upgrading PHP from {$currentPhpVersion} to {$requiredPhpVersion}");
                $results['php_upgrade'] = $phpModule->upgrade(
                    $projectPath,
                    $currentPhpVersion,
                    $requiredPhpVersion
                );
            }
        }

        // 2. Apply Laravel transformers
        $transformers = $this->getTransformers($fromVersion, $toVersion);
        $transformResults = [];

        foreach ($transformers as $transformer) {
            if ($transformer->shouldRun($projectPath)) {
                $this->log("Applying transformer: {$transformer->getName()}");
                $result = $transformer->transform($projectPath);
                $transformResults[] = $result;
            }
        }

        $results['laravel_transformations'] = $transformResults;

        // 3. Update composer.json
        $this->updateComposerFile($projectPath, $toVersion);

        // 4. Upgrade frontend if detected
        $frontendModule = $this->detectFrontendFramework($projectPath);
        if ($frontendModule && $this->registry) {
            $module = $this->registry->getModule($frontendModule);
            if ($module) {
                $this->log("Upgrading frontend framework: {$frontendModule}");
                $results['frontend_upgrade'] = $this->upgradeFrontend($projectPath, $module);
            }
        }

        return [
            'success' => true,
            'from_version' => $fromVersion,
            'to_version' => $toVersion,
            'results' => $results,
        ];
    }

    public function getTransformers(string $fromVersion, string $toVersion): array
    {
        $transformers = [];
        $path = $this->getUpgradePath($fromVersion, $toVersion);

        for ($i = 0; $i < count($path) - 1; $i++) {
            $from = $path[$i];
            $to = $path[$i + 1];

            $transformers = array_merge(
                $transformers,
                $this->getVersionTransformers($from, $to)
            );
        }

        usort($transformers, fn($a, $b) => $b->getPriority() <=> $a->getPriority());

        return $transformers;
    }

    private function getVersionTransformers(string $from, string $to): array
    {
        $transformers = [];

        // Laravel 8 -> 9
        if ($from === '8.0' && $to === '9.0') {
            $transformers[] = new Transformers\Laravel9\RouteNamespaceTransformer();
            $transformers[] = new Transformers\Laravel9\FlysystemTransformer();
            $transformers[] = new Transformers\Laravel9\StringHelpersTransformer();
        }

        // Laravel 9 -> 10
        if ($from === '9.0' && $to === '10.0') {
            $transformers[] = new Transformers\Laravel10\NativeTypesTransformer();
            $transformers[] = new Transformers\Laravel10\InvokableRulesTransformer();
        }

        // Laravel 10 -> 11
        if ($from === '10.0' && $to === '11.0') {
            $transformers[] = new Transformers\Laravel11\ApplicationStructureTransformer();
            $transformers[] = new Transformers\Laravel11\HealthRoutingTransformer();
        }

        // Laravel 12 -> 13
        if ($from === '12.0' && $to === '13.0') {
            $transformers[] = new Transformers\Laravel13\PreventRequestForgeryTransformer();
            $transformers[] = new Transformers\Laravel13\CacheConfigTransformer();
            $transformers[] = new Transformers\Laravel13\PolymorphicPivotTransformer();
        }

        return $transformers;
    }

    private function getRequiredPhpVersion(string $laravelVersion): string
    {
        $phpVersions = [
            '8.0' => '7.3',
            '9.0' => '8.0',
            '10.0' => '8.1',
            '11.0' => '8.2',
            '12.0' => '8.2',
            '13.0' => '8.3',
        ];

        return $phpVersions[$laravelVersion] ?? '8.1';
    }

    private function detectFrontendFramework(string $projectPath): ?string
    {
        $packageFile = $projectPath . '/package.json';
        if (!file_exists($packageFile)) {
            return null;
        }

        $package = json_decode(file_get_contents($packageFile), true);
        $dependencies = array_merge(
            $package['dependencies'] ?? [],
            $package['devDependencies'] ?? []
        );

        if (isset($dependencies['react'])) {
            return 'reactjs';
        }

        if (isset($dependencies['vue'])) {
            return 'vuejs';
        }

        return null;
    }

    private function upgradeFrontend(string $projectPath, $module): array
    {
        // Detect frontend language (JS or TS)
        $useTypeScript = file_exists($projectPath . '/tsconfig.json');
        
        if ($useTypeScript && $this->registry->hasModule('typescript')) {
            $tsModule = $this->registry->getModule('typescript');
            // Upgrade TypeScript first if needed
        }

        // Then upgrade the frontend framework
        $currentVersion = $module->detectCurrentVersion($projectPath);
        $targetVersion = $this->getLatestCompatibleVersion($module);
        
        if ($currentVersion && $targetVersion) {
            return $module->upgrade($projectPath, $currentVersion, $targetVersion);
        }

        return ['skipped' => true];
    }

    private function getLatestCompatibleVersion($module): ?string
    {
        $versions = $module->getAvailableVersions();
        return end($versions) ?: null;
    }

    private function updateComposerFile(string $projectPath, string $version): void
    {
        $composerFile = $projectPath . '/composer.json';
        if (!file_exists($composerFile)) {
            return;
        }

        $composer = json_decode(file_get_contents($composerFile), true);
        $major = (int) explode('.', $version)[0];
        $composer['require']['laravel/framework'] = "^{$major}.0";

        // Update PHP version requirement
        $composer['require']['php'] = "^{$this->getRequiredPhpVersion($version)}";

        file_put_contents(
            $composerFile,
            json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );
    }

    private function getDeprecatedFeatures(string $fromVersion, string $toVersion): array
    {
        // Implementation would return deprecated features
        return [];
    }

    private function getConfigurationChanges(string $fromVersion, string $toVersion): array
    {
        // Implementation would return config changes
        return [];
    }

    private function getDependencyUpdates(string $version): array
    {
        // Implementation would return dependency updates
        return [];
    }

    protected function getDefaultConfig(): array
    {
        return [
            'update_frontend' => true,
            'run_tests' => true,
            'backup_database' => true,
        ];
    }

    public function getConfigSchema(): array
    {
        return [
            'update_frontend' => [
                'type' => 'boolean',
                'description' => 'Automatically update frontend dependencies',
                'default' => true,
            ],
            'run_tests' => [
                'type' => 'boolean',
                'description' => 'Run tests after upgrade',
                'default' => true,
            ],
            'backup_database' => [
                'type' => 'boolean',
                'description' => 'Create database backup before migration',
                'default' => true,
            ],
        ];
    }
}
