<?php

namespace Upgrader\Modules\Laravel\Transformers\Laravel13;

use Upgrader\Core\TransformerInterface;

/**
 * Laravel 13 Transformer: VerifyCsrfToken -> PreventRequestForgery
 * 
 * Updates CSRF middleware references from the old VerifyCsrfToken
 * to the new PreventRequestForgery middleware name.
 */
class PreventRequestForgeryTransformer implements TransformerInterface
{
    public function getName(): string
    {
        return 'CSRF Middleware Rename (VerifyCsrfToken → PreventRequestForgery)';
    }

    public function getDescription(): string
    {
        return 'Updates CSRF middleware references to use the new PreventRequestForgery class name';
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function shouldRun(string $projectPath): bool
    {
        // Check if any files reference the old middleware
        $files = $this->getPhpFiles($projectPath);
        
        foreach ($files as $file) {
            $content = file_get_contents($file);
            if (str_contains($content, 'VerifyCsrfToken')) {
                return true;
            }
        }
        
        return false;
    }

    public function transform(string $projectPath): array
    {
        $changes = [];
        $files = $this->getPhpFiles($projectPath);

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $originalContent = $content;

            // Replace use statements
            $content = str_replace(
                'use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken',
                'use Illuminate\Foundation\Http\Middleware\PreventRequestForgery',
                $content
            );

            // Replace class references
            $content = str_replace(
                'VerifyCsrfToken::class',
                'PreventRequestForgery::class',
                $content
            );

            // Replace in comments and documentation
            $content = preg_replace(
                '/VerifyCsrfToken(?!\w)/',
                'PreventRequestForgery',
                $content
            );

            if ($content !== $originalContent) {
                file_put_contents($file, $content);
                $changes[] = basename($file);
            }
        }

        return [
            'success' => true,
            'message' => 'Updated CSRF middleware references in ' . count($changes) . ' files',
            'files_changed' => $changes,
            'manual_steps' => $this->getManualSteps(),
        ];
    }

    public function getAffectedFiles(string $projectPath): array
    {
        $affected = [];
        $files = $this->getPhpFiles($projectPath);

        foreach ($files as $file) {
            $content = file_get_contents($file);
            if (str_contains($content, 'VerifyCsrfToken')) {
                $affected[] = $file;
            }
        }

        return $affected;
    }

    public function validate(string $projectPath): bool
    {
        return is_dir($projectPath . '/app');
    }

    public function getManualSteps(): array
    {
        return [
            'Review test files that exclude CSRF middleware - update to use PreventRequestForgery::class',
            'Check route definitions that reference CSRF middleware',
            'Update any custom documentation referencing VerifyCsrfToken',
        ];
    }

    private function getPhpFiles(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                // Skip vendor directory
                if (str_contains($file->getPathname(), '/vendor/')) {
                    continue;
                }
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
