<?php

declare(strict_types=1);

namespace Upgrader\Modules\TypeScript;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Upgrader\Core\AbstractModule;

/**
 * TypeScript Upgrade Module
 * Depends on: JavaScript module
 */
class TypeScriptModule extends AbstractModule
{
    protected array $dependencies = ['javascript'];

    private array $versions = [
        '4.0', '4.1', '4.2', '4.3', '4.4', '4.5', '4.6', '4.7', '4.8', '4.9',
        '5.0', '5.1', '5.2', '5.3', '5.4',
    ];

    public function getName(): string
    {
        return 'typescript';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDescription(): string
    {
        return 'TypeScript version upgrade module';
    }

    public function canHandle(string $projectPath): bool
    {
        return file_exists($projectPath . '/tsconfig.json') ||
               $this->hasTypeScriptFiles($projectPath);
    }

    public function detectCurrentVersion(string $projectPath): ?string
    {
        $packageFile = $projectPath . '/package.json';
        if (! file_exists($packageFile)) {
            return null;
        }

        $package = json_decode(file_get_contents($packageFile), true);
        $dependencies = array_merge(
            $package['dependencies'] ?? [],
            $package['devDependencies'] ?? []
        );

        $version = $dependencies['typescript'] ?? null;
        if ($version && preg_match('/(\d+\.\d+)/', $version, $matches)) {
            return $matches[1];
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

        // Get JavaScript module analysis
        $jsModule = $this->getDependency('javascript');
        $jsAnalysis = null;

        if ($jsModule) {
            $targetES = $this->getTargetECMAScript($targetVersion);
            $jsAnalysis = $jsModule->analyze($projectPath, $targetES);
        }

        return [
            'current_version' => $currentVersion,
            'target_version' => $targetVersion,
            'javascript_target' => $this->getTargetECMAScript($targetVersion),
            'javascript_analysis' => $jsAnalysis,
            'breaking_changes' => $this->getBreakingChanges($currentVersion, $targetVersion),
            'new_features' => $this->getNewFeatures($targetVersion),
            'strict_mode_recommendations' => $this->getStrictModeRecommendations($targetVersion),
        ];
    }

    public function upgrade(string $projectPath, string $fromVersion, string $toVersion): array
    {
        $results = [];

        // 1. Upgrade JavaScript target if needed
        $jsModule = $this->getDependency('javascript');
        if ($jsModule) {
            $currentES = $this->getTargetECMAScript($fromVersion);
            $targetES = $this->getTargetECMAScript($toVersion);

            if ($currentES !== $targetES) {
                $this->log("Upgrading JavaScript target from {$currentES} to {$targetES}");
                $results['javascript_upgrade'] = $jsModule->upgrade(
                    $projectPath,
                    $currentES,
                    $targetES
                );
            }
        }

        // 2. Apply TypeScript transformers
        $transformers = $this->getTransformers($fromVersion, $toVersion);
        $transformResults = [];

        foreach ($transformers as $transformer) {
            if ($transformer->shouldRun($projectPath)) {
                $this->log("Applying transformer: {$transformer->getName()}");
                $result = $transformer->transform($projectPath);
                $transformResults[] = $result;
            }
        }

        $results['typescript_transformations'] = $transformResults;

        // 3. Update tsconfig.json
        $this->updateTsConfig($projectPath, $toVersion);

        // 4. Update package.json
        $this->updatePackageJson($projectPath, $toVersion);

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

        return $transformers;
    }

    public function getConfigSchema(): array
    {
        return [
            'strict_mode' => [
                'type' => 'boolean',
                'description' => 'Enable TypeScript strict mode',
                'default' => true,
            ],
            'update_types' => [
                'type' => 'boolean',
                'description' => 'Automatically update @types packages',
                'default' => true,
            ],
        ];
    }

    protected function getDefaultConfig(): array
    {
        return [
            'strict_mode' => true,
            'update_types' => true,
        ];
    }

    private function getVersionTransformers(string $from, string $to): array
    {
        $transformers = [];

        // TypeScript 4.x -> 5.x
        if (version_compare($from, '5.0', '<') && version_compare($to, '5.0', '>=')) {
            $transformers[] = new Transformers\TS5\DecoratorsTransformer;
            $transformers[] = new Transformers\TS5\ConstTypeParametersTransformer;
        }

        return $transformers;
    }

    private function hasTypeScriptFiles(string $path): bool
    {
        if (! is_dir($path)) {
            return false;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && in_array($file->getExtension(), ['ts', 'tsx'])) {
                return true;
            }
        }

        return false;
    }

    private function getTargetECMAScript(string $tsVersion): string
    {
        $mapping = [
            '4.0' => 'ES2020',
            '4.5' => 'ES2021',
            '5.0' => 'ES2022',
            '5.2' => 'ES2023',
        ];

        foreach ($mapping as $version => $es) {
            if (version_compare($tsVersion, $version, '>=')) {
                $target = $es;
            }
        }

        return $target ?? 'ES2020';
    }

    private function getNewFeatures(string $version): array
    {
        $features = [
            '4.0' => ['Variadic tuple types', 'Labeled tuple elements'],
            '4.5' => ['Template string types as discriminants', 'Awaited type'],
            '5.0' => ['Decorators', 'Const type parameters'],
            '5.2' => ['Using declarations', 'Explicit resource management'],
        ];

        foreach ($features as $v => $f) {
            if (version_compare($version, $v, '>=')) {
                return $f;
            }
        }

        return [];
    }

    private function getStrictModeRecommendations(string $version): array
    {
        if (version_compare($version, '5.0', '>=')) {
            return [
                'Enable strict mode in tsconfig.json',
                'Consider using strictNullChecks',
                'Enable noImplicitAny',
            ];
        }

        return [];
    }

    private function updateTsConfig(string $projectPath, string $version): void
    {
        $tsConfigFile = $projectPath . '/tsconfig.json';
        if (! file_exists($tsConfigFile)) {
            return;
        }

        $tsConfig = json_decode(file_get_contents($tsConfigFile), true);

        // Update target
        $tsConfig['compilerOptions']['target'] = $this->getTargetECMAScript($version);

        // Update lib
        $tsConfig['compilerOptions']['lib'] = $this->getLibraries($version);

        file_put_contents(
            $tsConfigFile,
            json_encode($tsConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );
    }

    private function updatePackageJson(string $projectPath, string $version): void
    {
        $packageFile = $projectPath . '/package.json';
        if (! file_exists($packageFile)) {
            return;
        }

        $package = json_decode(file_get_contents($packageFile), true);

        if (isset($package['devDependencies']['typescript'])) {
            $package['devDependencies']['typescript'] = "^{$version}";
        }

        file_put_contents(
            $packageFile,
            json_encode($package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );
    }

    private function getLibraries(string $version): array
    {
        $target = $this->getTargetECMAScript($version);

        return [
            mb_strtolower($target),
            'dom',
            'dom.iterable',
        ];
    }
}
