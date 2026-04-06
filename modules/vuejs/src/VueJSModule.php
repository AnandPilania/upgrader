<?php

namespace Upgrader\Modules\VueJS;

use Upgrader\Core\AbstractModule;

/**
 * Vue.js Upgrade Module
 * Depends on: JavaScript OR TypeScript
 */
class VueJSModule extends AbstractModule
{
    private array $versions = [
        '2.6', '2.7', '3.0', '3.1', '3.2', '3.3', '3.4'
    ];

    public function getName(): string
    {
        return 'vuejs';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDescription(): string
    {
        return 'Vue.js upgrade module';
    }

    public function getDependencies(): array
    {
        // Vue can use either JavaScript or TypeScript
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

        return isset($dependencies['vue']);
    }

    public function detectCurrentVersion(string $projectPath): ?string
    {
        $packageFile = $projectPath . '/package.json';
        if (!file_exists($packageFile)) {
            return null;
        }

        $package = json_decode(file_get_contents($packageFile), true);
        $version = $package['dependencies']['vue'] ?? null;

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
            'composition_api_available' => $this->supportsCompositionAPI($targetVersion),
            'script_setup_available' => $this->supportsScriptSetup($targetVersion),
            'major_upgrade' => $this->isMajorUpgrade($currentVersion, $targetVersion),
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

        // 2. Apply Vue transformers
        $transformers = $this->getTransformers($fromVersion, $toVersion);
        $transformResults = [];

        foreach ($transformers as $transformer) {
            if ($transformer->shouldRun($projectPath)) {
                $this->log("Applying transformer: {$transformer->getName()}");
                $result = $transformer->transform($projectPath);
                $transformResults[] = $result;
            }
        }

        $results['vue_transformations'] = $transformResults;

        // 3. Update package.json
        $this->updatePackageJson($projectPath, $toVersion);

        // 4. Update Vite/Webpack config if needed
        if ($this->isMajorUpgrade($fromVersion, $toVersion)) {
            $this->updateBuildConfig($projectPath, $toVersion);
        }

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

        // Vue 2.x -> 3.0
        if (version_compare($from, '3.0', '<') && version_compare($to, '3.0', '>=')) {
            $transformers[] = new Transformers\Vue3\GlobalAPITransformer();
            $transformers[] = new Transformers\Vue3\VModelTransformer();
            $transformers[] = new Transformers\Vue3\FiltersTransformer();
            $transformers[] = new Transformers\Vue3\FunctionalComponentsTransformer();
        }

        // Vue 3.0 -> 3.2 (Script Setup)
        if (version_compare($from, '3.2', '<') && version_compare($to, '3.2', '>=')) {
            $transformers[] = new Transformers\Vue32\ScriptSetupTransformer();
        }

        return $transformers;
    }

    private function usesTypeScript(string $projectPath): bool
    {
        return file_exists($projectPath . '/tsconfig.json') ||
               $this->hasVueTypeScriptFiles($projectPath);
    }

    private function hasVueTypeScriptFiles(string $path): bool
    {
        if (!is_dir($path)) {
            return false;
        }

        // Check for .vue files with <script lang="ts">
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'vue') {
                $content = file_get_contents($file->getPathname());
                if (strpos($content, 'lang="ts"') !== false || strpos($content, "lang='ts'") !== false) {
                    return true;
                }
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

    private function getRecommendedLanguageVersion(string $vueVersion, bool $useTypeScript): string
    {
        if ($useTypeScript) {
            $mapping = [
                '2.6' => '3.9',
                '2.7' => '4.0',
                '3.0' => '4.0',
                '3.2' => '4.5',
                '3.4' => '5.0',
            ];
        } else {
            $mapping = [
                '2.6' => 'ES6',
                '2.7' => 'ES2020',
                '3.0' => 'ES2020',
                '3.2' => 'ES2020',
                '3.4' => 'ES2022',
            ];
        }

        foreach ($mapping as $version => $lang) {
            if (version_compare($vueVersion, $version, '>=')) {
                $recommended = $lang;
            }
        }

        return $recommended ?? ($useTypeScript ? '4.0' : 'ES6');
    }

    private function supportsCompositionAPI(string $version): bool
    {
        return version_compare($version, '2.7', '>=');
    }

    private function supportsScriptSetup(string $version): bool
    {
        return version_compare($version, '3.2', '>=');
    }

    private function isMajorUpgrade(string $from, string $to): bool
    {
        $fromMajor = (int) explode('.', $from)[0];
        $toMajor = (int) explode('.', $to)[0];
        
        return $fromMajor !== $toMajor;
    }

    private function getNewFeatures(string $version): array
    {
        $features = [
            '2.7' => ['Composition API backport', 'Script setup backport'],
            '3.0' => ['Composition API', 'Teleport', 'Fragments', 'Suspense'],
            '3.2' => ['Script setup', 'v-memo directive'],
            '3.3' => ['defineModel', 'Generic components'],
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
        
        // Update Vue
        if (isset($package['dependencies']['vue'])) {
            $package['dependencies']['vue'] = "^{$version}";
        }

        // Update Vue loader/compiler for Vue 3
        if (version_compare($version, '3.0', '>=')) {
            if (isset($package['devDependencies']['vue-loader'])) {
                $package['devDependencies']['vue-loader'] = '^17.0.0';
            }
            if (isset($package['devDependencies']['@vue/compiler-sfc'])) {
                $package['devDependencies']['@vue/compiler-sfc'] = "^{$version}";
            }
        }

        file_put_contents(
            $packageFile,
            json_encode($package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );
    }

    private function updateBuildConfig(string $projectPath, string $version): void
    {
        // Update vite.config or webpack config for Vue 3
        // Implementation would go here
    }

    protected function getDefaultConfig(): array
    {
        return [
            'use_composition_api' => true,
            'use_script_setup' => true,
            'migrate_filters' => true,
        ];
    }

    public function getConfigSchema(): array
    {
        return [
            'use_composition_api' => [
                'type' => 'boolean',
                'description' => 'Migrate to Composition API',
                'default' => true,
            ],
            'use_script_setup' => [
                'type' => 'boolean',
                'description' => 'Use <script setup> syntax',
                'default' => true,
            ],
        ];
    }
}
