<?php

declare(strict_types=1);

namespace Upgrader\Modules\PHP;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Upgrader\Core\AbstractModule;

/**
 * PHP Version Upgrade Module
 * Handles PHP version upgrades and compatibility checks
 */
class PHPModule extends AbstractModule
{
    private array $versions = [
        '7.4', '8.0', '8.1', '8.2', '8.3', '8.4',
    ];

    public function getName(): string
    {
        return 'php';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDescription(): string
    {
        return 'PHP version upgrade and compatibility module';
    }

    public function canHandle(string $projectPath): bool
    {
        return file_exists($projectPath . '/composer.json') ||
               $this->hasPhpFiles($projectPath);
    }

    public function detectCurrentVersion(string $projectPath): ?string
    {
        // Check composer.json
        $composerFile = $projectPath . '/composer.json';
        if (file_exists($composerFile)) {
            $composer = json_decode(file_get_contents($composerFile), true);
            $phpVersion = $composer['require']['php'] ?? null;

            if ($phpVersion && preg_match('/(\d+\.\d+)/', $phpVersion, $matches)) {
                return $matches[1];
            }
        }

        // Check running PHP version
        return PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
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
            'deprecated_features' => $this->getDeprecatedFeatures($currentVersion, $targetVersion),
            'new_features' => $this->getNewFeatures($targetVersion),
            'compatibility_issues' => $this->checkCompatibility($projectPath, $targetVersion),
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

        // Update composer.json
        $this->updateComposerPhpVersion($projectPath, $toVersion);

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
        $path = $this->getUpgradePath($fromVersion, $toVersion);

        for ($i = 0; $i < count($path) - 1; $i++) {
            $from = $path[$i];
            $to = $path[$i + 1];

            $transformers = array_merge(
                $transformers,
                $this->getVersionTransformers($from, $to)
            );
        }

        // Sort by priority
        usort($transformers, fn ($a, $b) => $b->getPriority() <=> $a->getPriority());

        return $transformers;
    }

    public function getConfigSchema(): array
    {
        return [
            'strict_types' => [
                'type' => 'boolean',
                'description' => 'Add declare(strict_types=1) to files',
                'default' => false,
            ],
            'rector_enabled' => [
                'type' => 'boolean',
                'description' => 'Enable Rector for automated refactoring',
                'default' => true,
            ],
        ];
    }

    protected function getDefaultConfig(): array
    {
        return [
            'strict_types' => false,
            'rector_enabled' => true,
        ];
    }

    private function getVersionTransformers(string $from, string $to): array
    {
        $transformers = [];

        // PHP 7.4 -> 8.0
        if ($from === '7.4' && $to === '8.0') {
            $transformers[] = new Transformers\PHP80\NamedArgumentsTransformer;
            $transformers[] = new Transformers\PHP80\UnionTypesTransformer;
            $transformers[] = new Transformers\PHP80\NullsafeOperatorTransformer;
        }

        // PHP 8.0 -> 8.1
        if ($from === '8.0' && $to === '8.1') {
            $transformers[] = new Transformers\PHP81\EnumsTransformer;
            $transformers[] = new Transformers\PHP81\ReadonlyPropertiesTransformer;
            $transformers[] = new Transformers\PHP81\FibersTransformer;
        }

        // PHP 8.1 -> 8.2
        if ($from === '8.1' && $to === '8.2') {
            $transformers[] = new Transformers\PHP82\ReadonlyClassesTransformer;
            $transformers[] = new Transformers\PHP82\DisjunctiveNormalFormTransformer;
        }

        // PHP 8.2 -> 8.3
        if ($from === '8.2' && $to === '8.3') {
            $transformers[] = new Transformers\PHP83\TypedClassConstantsTransformer;
            $transformers[] = new Transformers\PHP83\DynamicClassConstantFetchTransformer;
        }

        return $transformers;
    }

    private function hasPhpFiles(string $path): bool
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                return true;
            }
        }

        return false;
    }

    private function updateComposerPhpVersion(string $projectPath, string $version): void
    {
        $composerFile = $projectPath . '/composer.json';
        if (! file_exists($composerFile)) {
            return;
        }

        $composer = json_decode(file_get_contents($composerFile), true);
        $composer['require']['php'] = "^{$version}";

        file_put_contents(
            $composerFile,
            json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );
    }

    private function getDeprecatedFeatures(string $fromVersion, string $toVersion): array
    {
        $deprecated = [];

        // Add version-specific deprecated features
        if (version_compare($toVersion, '8.0', '>=')) {
            $deprecated[] = 'Required parameters after optional parameters';
            $deprecated[] = 'Implicit nullable parameter types';
        }

        return $deprecated;
    }

    private function getNewFeatures(string $version): array
    {
        $features = [
            '8.0' => ['Named arguments', 'Attributes', 'Union types', 'Nullsafe operator'],
            '8.1' => ['Enums', 'Readonly properties', 'Fibers', 'First-class callable syntax'],
            '8.2' => ['Readonly classes', 'Disjunctive Normal Form types'],
            '8.3' => ['Typed class constants', 'Dynamic class constant fetch'],
        ];

        return $features[$version] ?? [];
    }

    private function checkCompatibility(string $projectPath, string $targetVersion): array
    {
        return [];
    }
}
