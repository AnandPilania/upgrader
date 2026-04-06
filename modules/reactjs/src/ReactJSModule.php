<?php

namespace Upgrader\Modules\ReactJS;

use Upgrader\Core\AbstractModule;

/**
 * React.js Upgrade Module
 * Depends on: JavaScript OR TypeScript
 */
class ReactJSModule extends AbstractModule
{
    private array $versions = [
        '16.0', '16.8', '17.0', '18.0', '19.0'
    ];

    public function getName(): string
    {
        return 'reactjs';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDescription(): string
    {
        return 'React.js upgrade module';
    }

    public function getDependencies(): array
    {
        // React can use either JavaScript or TypeScript
        // We'll check which one is available during initialization
        return [];
    }

    public function canHandle(string $projectPath): bool
    {
        $packageFile = $projectPath . '/package.json';
        if (!file_exists($packageFile)) {
            return false;
        }

        $package = json_decode(file_get_contents($packageFile), true);
        $dependencies = array_merge(
            $package['dependencies'] ?? [],
            $package['devDependencies'] ?? []
        );

        return isset($dependencies['react']);
    }

    public function detectCurrentVersion(string $projectPath): ?string
    {
        $packageFile = $projectPath . '/package.json';
        if (!file_exists($packageFile)) {
            return null;
        }

        $package = json_decode(file_get_contents($packageFile), true);
        $version = $package['dependencies']['react'] ?? null;

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
        $useTypeScript = $this->usesTypeScript($projectPath);
        
        // Get language module analysis
        $langModule = $this->getLanguageModule($projectPath);
        $langAnalysis = null;
        
        if ($langModule) {
            $currentLangVersion = $langModule->detectCurrentVersion($projectPath);
            $targetLangVersion = $this->getRecommendedLanguageVersion($targetVersion, $useTypeScript);
            
            if ($currentLangVersion && version_compare($currentLangVersion, $targetLangVersion, '<')) {
                $langAnalysis = $langModule->analyze($projectPath, $targetLangVersion);
            }
        }

        return [
            'current_version' => $currentVersion,
            'target_version' => $targetVersion,
            'uses_typescript' => $useTypeScript,
            'language_module' => $useTypeScript ? 'typescript' : 'javascript',
            'language_analysis' => $langAnalysis,
            'breaking_changes' => $this->getBreakingChanges($currentVersion, $targetVersion),
            'new_features' => $this->getNewFeatures($targetVersion),
            'hooks_migration' => $this->needsHooksMigration($currentVersion, $targetVersion),
            'concurrent_features' => $this->supportsConcurrentFeatures($targetVersion),
        ];
    }

    public function upgrade(string $projectPath, string $fromVersion, string $toVersion): array
    {
        $results = [];
        $useTypeScript = $this->usesTypeScript($projectPath);

        // 1. Upgrade language (JS or TS) if needed
        $langModule = $this->getLanguageModule($projectPath);
        if ($langModule) {
            $currentLangVersion = $langModule->detectCurrentVersion($projectPath);
            $targetLangVersion = $this->getRecommendedLanguageVersion($toVersion, $useTypeScript);
            
            if ($currentLangVersion && version_compare($currentLangVersion, $targetLangVersion, '<')) {
                $this->log("Upgrading " . ($useTypeScript ? 'TypeScript' : 'JavaScript'));
                $results['language_upgrade'] = $langModule->upgrade(
                    $projectPath,
                    $currentLangVersion,
                    $targetLangVersion
                );
            }
        }

        // 2. Apply React transformers
        $transformers = $this->getTransformers($fromVersion, $toVersion);
        $transformResults = [];

        foreach ($transformers as $transformer) {
            if ($transformer->shouldRun($projectPath)) {
                $this->log("Applying transformer: {$transformer->getName()}");
                $result = $transformer->transform($projectPath);
                $transformResults[] = $result;
            }
        }

        $results['react_transformations'] = $transformResults;

        // 3. Update package.json
        $this->updatePackageJson($projectPath, $toVersion);

        return [
            'success' => true,
            'from_version' => $fromVersion,
            'to_version' => $toVersion,
            'uses_typescript' => $useTypeScript,
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

        // React 16.0 -> 16.8 (Hooks introduction)
        if (version_compare($from, '16.8', '<') && version_compare($to, '16.8', '>=')) {
            $transformers[] = new Transformers\React168\HooksTransformer();
            $transformers[] = new Transformers\React168\ClassToFunctionalTransformer();
        }

        // React 16.x -> 17.0
        if (version_compare($from, '17.0', '<') && version_compare($to, '17.0', '>=')) {
            $transformers[] = new Transformers\React17\JSXTransformTransformer();
            $transformers[] = new Transformers\React17\EventPoolingTransformer();
        }

        // React 17.x -> 18.0
        if (version_compare($from, '18.0', '<') && version_compare($to, '18.0', '>=')) {
            $transformers[] = new Transformers\React18\ConcurrentFeaturesTransformer();
            $transformers[] = new Transformers\React18\AutomaticBatchingTransformer();
            $transformers[] = new Transformers\React18\SuspenseTransformer();
        }

        return $transformers;
    }

    private function usesTypeScript(string $projectPath): bool
    {
        return file_exists($projectPath . '/tsconfig.json') ||
               $this->hasTypeScriptFiles($projectPath);
    }

    private function hasTypeScriptFiles(string $path): bool
    {
        if (!is_dir($path)) {
            return false;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'tsx') {
                return true;
            }
        }

        return false;
    }

    private function getLanguageModule(string $projectPath)
    {
        if (!$this->registry) {
            return null;
        }

        $useTypeScript = $this->usesTypeScript($projectPath);
        $moduleName = $useTypeScript ? 'typescript' : 'javascript';

        return $this->registry->getModule($moduleName);
    }

    private function getRecommendedLanguageVersion(string $reactVersion, bool $useTypeScript): string
    {
        if ($useTypeScript) {
            $mapping = [
                '16.8' => '4.0',
                '17.0' => '4.0',
                '18.0' => '4.5',
                '19.0' => '5.0',
            ];
        } else {
            $mapping = [
                '16.8' => 'ES6',
                '17.0' => 'ES2020',
                '18.0' => 'ES2020',
                '19.0' => 'ES2022',
            ];
        }

        foreach ($mapping as $version => $lang) {
            if (version_compare($reactVersion, $version, '>=')) {
                $recommended = $lang;
            }
        }

        return $recommended ?? ($useTypeScript ? '4.0' : 'ES6');
    }

    private function needsHooksMigration(string $from, string $to): bool
    {
        return version_compare($from, '16.8', '<') && version_compare($to, '16.8', '>=');
    }

    private function supportsConcurrentFeatures(string $version): bool
    {
        return version_compare($version, '18.0', '>=');
    }

    private function getNewFeatures(string $version): array
    {
        $features = [
            '16.8' => ['Hooks', 'useState', 'useEffect', 'useContext'],
            '17.0' => ['New JSX transform', 'No event pooling', 'Effect cleanup timing'],
            '18.0' => ['Concurrent rendering', 'Automatic batching', 'Suspense', 'Transitions'],
            '19.0' => ['Server Components', 'Asset loading', 'Document metadata'],
        ];

        return $features[$version] ?? [];
    }

    private function updatePackageJson(string $projectPath, string $version): void
    {
        $packageFile = $projectPath . '/package.json';
        if (!file_exists($packageFile)) {
            return;
        }

        $package = json_decode(file_get_contents($packageFile), true);
        
        // Update React and React DOM
        if (isset($package['dependencies']['react'])) {
            $package['dependencies']['react'] = "^{$version}";
            $package['dependencies']['react-dom'] = "^{$version}";
        }

        // Update React types for TypeScript projects
        if (isset($package['devDependencies']['@types/react'])) {
            $package['devDependencies']['@types/react'] = "^{$version}";
            $package['devDependencies']['@types/react-dom'] = "^{$version}";
        }

        file_put_contents(
            $packageFile,
            json_encode($package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );
    }

    protected function getDefaultConfig(): array
    {
        return [
            'migrate_to_hooks' => true,
            'enable_strict_mode' => true,
            'update_types' => true,
        ];
    }

    public function getConfigSchema(): array
    {
        return [
            'migrate_to_hooks' => [
                'type' => 'boolean',
                'description' => 'Automatically migrate class components to hooks',
                'default' => true,
            ],
            'enable_strict_mode' => [
                'type' => 'boolean',
                'description' => 'Wrap app in React.StrictMode',
                'default' => true,
            ],
        ];
    }
}
