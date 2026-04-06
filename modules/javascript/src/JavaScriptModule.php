<?php

declare(strict_types=1);

namespace Upgrader\Modules\JavaScript;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Upgrader\Core\AbstractModule;

/**
 * JavaScript Upgrade Module
 * Handles JavaScript/Node.js version upgrades
 */
class JavaScriptModule extends AbstractModule
{
    private array $versions = [
        'ES5', 'ES6', 'ES2016', 'ES2017', 'ES2018', 'ES2019', 'ES2020', 'ES2021', 'ES2022', 'ES2023',
    ];

    public function getName(): string
    {
        return 'javascript';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDescription(): string
    {
        return 'JavaScript/ECMAScript version upgrade module';
    }

    public function canHandle(string $projectPath): bool
    {
        return file_exists($projectPath . '/package.json') ||
               $this->hasJavaScriptFiles($projectPath);
    }

    public function detectCurrentVersion(string $projectPath): ?string
    {
        // Check package.json for ECMAScript version in browserslist or similar
        // For now, we'll default to ES6
        return 'ES6';
    }

    public function getAvailableVersions(): array
    {
        return $this->versions;
    }

    public function analyze(string $projectPath, string $targetVersion): array
    {
        return [
            'current_version' => $this->detectCurrentVersion($projectPath),
            'target_version' => $targetVersion,
            'new_features' => $this->getNewFeatures($targetVersion),
            'babel_required' => $this->requiresBabel($targetVersion),
            'polyfills_needed' => $this->getRequiredPolyfills($targetVersion),
        ];
    }

    public function upgrade(string $projectPath, string $fromVersion, string $toVersion): array
    {
        $transformers = $this->getTransformers($fromVersion, $toVersion);
        $results = [];

        foreach ($transformers as $transformer) {
            if ($transformer->shouldRun($projectPath)) {
                $result = $transformer->transform($projectPath);
                $results[] = $result;
            }
        }

        // Update package.json browserslist
        $this->updatePackageJson($projectPath, $toVersion);

        return [
            'success' => true,
            'from_version' => $fromVersion,
            'to_version' => $toVersion,
            'transformations' => $results,
        ];
    }

    public function getTransformers(string $fromVersion, string $toVersion): array
    {
        $transformers = [];

        // Add transformers based on version upgrades
        if ($this->shouldUpgradeToES6($fromVersion, $toVersion)) {
            $transformers[] = new Transformers\ES6\ArrowFunctionsTransformer;
            $transformers[] = new Transformers\ES6\ClassSyntaxTransformer;
            $transformers[] = new Transformers\ES6\TemplateLiteralsTransformer;
        }

        if ($this->shouldUpgradeToES2020($fromVersion, $toVersion)) {
            $transformers[] = new Transformers\ES2020\OptionalChainingTransformer;
            $transformers[] = new Transformers\ES2020\NullishCoalescingTransformer;
        }

        return $transformers;
    }

    protected function getDefaultConfig(): array
    {
        return [
            'use_babel' => true,
            'minify' => true,
        ];
    }

    private function hasJavaScriptFiles(string $path): bool
    {
        if (! is_dir($path)) {
            return false;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && in_array($file->getExtension(), ['js', 'mjs', 'cjs'])) {
                return true;
            }
        }

        return false;
    }

    private function getNewFeatures(string $version): array
    {
        $features = [
            'ES6' => ['Arrow functions', 'Classes', 'Template literals', 'Destructuring'],
            'ES2020' => ['Optional chaining', 'Nullish coalescing', 'Dynamic import'],
            'ES2022' => ['Top-level await', 'Class fields', 'Private methods'],
        ];

        return $features[$version] ?? [];
    }

    private function requiresBabel(string $targetVersion): bool
    {
        // Modern browsers support up to ES2020 natively
        $modernVersions = ['ES2020', 'ES2021', 'ES2022', 'ES2023'];

        return ! in_array($targetVersion, $modernVersions);
    }

    private function getRequiredPolyfills(string $targetVersion): array
    {
        // Return required polyfills for the target version
        return [];
    }

    private function shouldUpgradeToES6(string $from, string $to): bool
    {
        $fromIndex = array_search($from, $this->versions);
        $toIndex = array_search($to, $this->versions);
        $es6Index = array_search('ES6', $this->versions);

        return $fromIndex < $es6Index && $toIndex >= $es6Index;
    }

    private function shouldUpgradeToES2020(string $from, string $to): bool
    {
        $fromIndex = array_search($from, $this->versions);
        $toIndex = array_search($to, $this->versions);
        $es2020Index = array_search('ES2020', $this->versions);

        return $fromIndex < $es2020Index && $toIndex >= $es2020Index;
    }

    private function updatePackageJson(string $projectPath, string $version): void
    {
        $packageFile = $projectPath . '/package.json';
        if (! file_exists($packageFile)) {
            return;
        }

        $package = json_decode(file_get_contents($packageFile), true);

        // Update browserslist
        $package['browserslist'] = $this->getBrowserslist($version);

        file_put_contents(
            $packageFile,
            json_encode($package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );
    }

    private function getBrowserslist(string $version): array
    {
        $lists = [
            'ES6' => ['>0.2%', 'not dead', 'not op_mini all'],
            'ES2020' => ['defaults', 'not IE 11'],
            'ES2022' => ['last 2 versions'],
        ];

        return $lists[$version] ?? $lists['ES6'];
    }
}
