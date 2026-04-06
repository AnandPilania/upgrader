<?php

namespace Upgrader\Modules\TailwindCSS;

use Upgrader\Core\AbstractModule;

/**
 * Tailwind CSS Upgrade Module
 * Independent module for Tailwind CSS upgrades
 */
class TailwindCSSModule extends AbstractModule
{
    private array $versions = [
        '2.0', '2.1', '2.2', '3.0', '3.1', '3.2', '3.3', '3.4'
    ];

    public function getName(): string
    {
        return 'tailwindcss';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDescription(): string
    {
        return 'Tailwind CSS upgrade module';
    }

    public function canHandle(string $projectPath): bool
    {
        // Check for tailwind.config.js
        if (file_exists($projectPath . '/tailwind.config.js') ||
            file_exists($projectPath . '/tailwind.config.ts')) {
            return true;
        }

        // Check package.json
        $packageFile = $projectPath . '/package.json';
        if (file_exists($packageFile)) {
            $package = json_decode(file_get_contents($packageFile), true);
            $dependencies = array_merge(
                $package['dependencies'] ?? [],
                $package['devDependencies'] ?? []
            );

            return isset($dependencies['tailwindcss']);
        }

        return false;
    }

    public function detectCurrentVersion(string $projectPath): ?string
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

        $version = $dependencies['tailwindcss'] ?? null;
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

        return [
            'current_version' => $currentVersion,
            'target_version' => $targetVersion,
            'breaking_changes' => $this->getBreakingChanges($currentVersion, $targetVersion),
            'deprecated_classes' => $this->getDeprecatedClasses($currentVersion, $targetVersion),
            'new_features' => $this->getNewFeatures($targetVersion),
            'jit_mode_available' => $this->supportsJIT($targetVersion),
            'config_changes' => $this->getConfigChanges($currentVersion, $targetVersion),
        ];
    }

    public function upgrade(string $projectPath, string $fromVersion, string $toVersion): array
    {
        $results = [];

        // 1. Apply Tailwind transformers
        $transformers = $this->getTransformers($fromVersion, $toVersion);
        $transformResults = [];

        foreach ($transformers as $transformer) {
            if ($transformer->shouldRun($projectPath)) {
                $this->log("Applying transformer: {$transformer->getName()}");
                $result = $transformer->transform($projectPath);
                $transformResults[] = $result;
            }
        }

        $results['tailwind_transformations'] = $transformResults;

        // 2. Update package.json
        $this->updatePackageJson($projectPath, $toVersion);

        // 3. Update tailwind.config
        $this->updateTailwindConfig($projectPath, $toVersion);

        // 4. Update PostCSS config if needed
        if ($this->isMajorUpgrade($fromVersion, $toVersion)) {
            $this->updatePostCSSConfig($projectPath, $toVersion);
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

        return $transformers;
    }

    private function getVersionTransformers(string $from, string $to): array
    {
        $transformers = [];

        // Tailwind 2.x -> 3.0
        if (version_compare($from, '3.0', '<') && version_compare($to, '3.0', '>=')) {
            $transformers[] = new Transformers\Tailwind3\ColorPaletteTransformer();
            $transformers[] = new Transformers\Tailwind3\PurgeToContentTransformer();
            $transformers[] = new Transformers\Tailwind3\JITEnabledTransformer();
            $transformers[] = new Transformers\Tailwind3\DeprecatedClassesTransformer();
        }

        return $transformers;
    }

    private function supportsJIT(string $version): bool
    {
        return version_compare($version, '2.1', '>=');
    }

    private function isMajorUpgrade(string $from, string $to): bool
    {
        $fromMajor = (int) explode('.', $from)[0];
        $toMajor = (int) explode('.', $to)[0];
        
        return $fromMajor !== $toMajor;
    }

    private function getDeprecatedClasses(string $fromVersion, string $toVersion): array
    {
        $deprecated = [];

        // Tailwind 3.0 deprecations
        if (version_compare($fromVersion, '3.0', '<') && version_compare($toVersion, '3.0', '>=')) {
            $deprecated = [
                'overflow-clip' => 'Use overflow-hidden instead',
                'flex-grow-0' => 'Use grow-0 instead',
                'flex-shrink-0' => 'Use shrink-0 instead',
            ];
        }

        return $deprecated;
    }

    private function getNewFeatures(string $version): array
    {
        $features = [
            '2.1' => ['JIT mode', 'Arbitrary value support'],
            '3.0' => ['JIT by default', 'Colored box shadows', 'Multi-column layout'],
            '3.1' => ['First-party TypeScript types', 'Nested group support'],
            '3.2' => ['Container queries', 'Dynamic breakpoints'],
            '3.3' => ['Extended color palette', 'Line-height modifiers'],
        ];

        return $features[$version] ?? [];
    }

    private function getConfigChanges(string $fromVersion, string $toVersion): array
    {
        $changes = [];

        if (version_compare($fromVersion, '3.0', '<') && version_compare($toVersion, '3.0', '>=')) {
            $changes[] = 'purge → content (rename property)';
            $changes[] = 'mode: jit no longer needed (enabled by default)';
            $changes[] = 'Default color palette updated';
        }

        return $changes;
    }

    private function updatePackageJson(string $projectPath, string $version): void
    {
        $packageFile = $projectPath . '/package.json';
        if (!file_exists($packageFile)) {
            return;
        }

        $package = json_decode(file_get_contents($packageFile), true);
        
        if (isset($package['devDependencies']['tailwindcss'])) {
            $package['devDependencies']['tailwindcss'] = "^{$version}";
        }

        // Update related packages
        if (version_compare($version, '3.0', '>=')) {
            if (isset($package['devDependencies']['@tailwindcss/forms'])) {
                $package['devDependencies']['@tailwindcss/forms'] = '^0.5.0';
            }
            if (isset($package['devDependencies']['@tailwindcss/typography'])) {
                $package['devDependencies']['@tailwindcss/typography'] = '^0.5.0';
            }
        }

        file_put_contents(
            $packageFile,
            json_encode($package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );
    }

    private function updateTailwindConfig(string $projectPath, string $version): void
    {
        $configFile = $projectPath . '/tailwind.config.js';
        $configFileTs = $projectPath . '/tailwind.config.ts';
        
        $targetFile = file_exists($configFile) ? $configFile : 
                     (file_exists($configFileTs) ? $configFileTs : null);

        if (!$targetFile) {
            return;
        }

        $content = file_get_contents($targetFile);

        // Tailwind 3.0 config updates
        if (version_compare($version, '3.0', '>=')) {
            // Replace purge with content
            $content = preg_replace('/purge\s*:/', 'content:', $content);
            
            // Remove mode: 'jit'
            $content = preg_replace('/mode\s*:\s*[\'"]jit[\'"]\s*,?\s*/', '', $content);
        }

        file_put_contents($targetFile, $content);
    }

    private function updatePostCSSConfig(string $projectPath, string $version): void
    {
        $postcssFile = $projectPath . '/postcss.config.js';
        
        if (!file_exists($postcssFile)) {
            return;
        }

        // Update PostCSS config if needed for Tailwind 3
        // Implementation would go here
    }

    protected function getDefaultConfig(): array
    {
        return [
            'enable_jit' => true,
            'update_plugins' => true,
        ];
    }

    public function getConfigSchema(): array
    {
        return [
            'enable_jit' => [
                'type' => 'boolean',
                'description' => 'Enable JIT mode',
                'default' => true,
            ],
            'update_plugins' => [
                'type' => 'boolean',
                'description' => 'Update Tailwind CSS plugins',
                'default' => true,
            ],
        ];
    }
}
