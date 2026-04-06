<?php

namespace Upgrader\Modules\Laravel\Transformers\Laravel13;

use Upgrader\Core\TransformerInterface;

/**
 * Laravel 13 Transformer: Cache Configuration Updates
 * 
 * Adds the new serializable_classes configuration option to cache config.
 */
class CacheConfigTransformer implements TransformerInterface
{
    public function getName(): string
    {
        return 'Cache Configuration Updates';
    }

    public function getDescription(): string
    {
        return 'Adds serializable_classes option to cache configuration for enhanced security';
    }

    public function getPriority(): int
    {
        return 90;
    }

    public function shouldRun(string $projectPath): bool
    {
        $configFile = $projectPath . '/config/cache.php';
        
        if (!file_exists($configFile)) {
            return false;
        }

        $content = file_get_contents($configFile);
        return !str_contains($content, 'serializable_classes');
    }

    public function transform(string $projectPath): array
    {
        $configFile = $projectPath . '/config/cache.php';
        
        if (!file_exists($configFile)) {
            return [
                'success' => false,
                'message' => 'Cache configuration file not found',
            ];
        }

        $content = file_get_contents($configFile);
        
        // Add serializable_classes configuration
        // Find the stores array and add the config before it
        $insertion = <<<'PHP'

    /*
    |--------------------------------------------------------------------------
    | Cache Serializable Classes
    |--------------------------------------------------------------------------
    |
    | This option controls which classes may be unserialized from the cache.
    | Set to false to allow all classes, or provide an array of specific
    | classes to enhance security against deserialization attacks.
    |
    */

    'serializable_classes' => false,

PHP;

        // Try to insert before 'stores' array
        if (str_contains($content, "'stores' =>")) {
            $content = str_replace(
                "'stores' =>",
                $insertion . "    'stores' =>",
                $content
            );
        } else {
            // Fallback: add at the end of the return array
            $content = preg_replace(
                '/(\];)\s*$/',
                $insertion . "$1",
                $content
            );
        }

        file_put_contents($configFile, $content);

        return [
            'success' => true,
            'message' => 'Added serializable_classes configuration to cache.php',
            'files_changed' => ['config/cache.php'],
            'manual_steps' => $this->getManualSteps(),
        ];
    }

    public function getAffectedFiles(string $projectPath): array
    {
        return [$projectPath . '/config/cache.php'];
    }

    public function validate(string $projectPath): bool
    {
        return file_exists($projectPath . '/config/cache.php');
    }

    public function getManualSteps(): array
    {
        return [
            'If you cache PHP objects, update serializable_classes to whitelist allowed classes',
            'Example: \'serializable_classes\' => [App\\Data\\CachedStats::class]',
            'Review .env for CACHE_PREFIX and SESSION_COOKIE if using default values',
        ];
    }
}
