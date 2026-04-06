<?php

namespace Upgrader\Modules\Laravel\Transformers\Laravel13;

use Upgrader\Core\TransformerInterface;

/**
 * Laravel 13 Transformer: Polymorphic Pivot Table Names
 * 
 * Warns about changes to polymorphic pivot table name generation.
 */
class PolymorphicPivotTransformer implements TransformerInterface
{
    public function getName(): string
    {
        return 'Polymorphic Pivot Table Name Updates';
    }

    public function getDescription(): string
    {
        return 'Checks for custom polymorphic pivot models that may need explicit table names';
    }

    public function getPriority(): int
    {
        return 80;
    }

    public function shouldRun(string $projectPath): bool
    {
        // Check if project has pivot models
        $modelsPath = $projectPath . '/app/Models';
        
        if (!is_dir($modelsPath)) {
            return false;
        }

        return $this->hasPivotModels($modelsPath);
    }

    public function transform(string $projectPath): array
    {
        $pivotModels = $this->findPivotModels($projectPath . '/app/Models');

        return [
            'success' => true,
            'message' => 'Found ' . count($pivotModels) . ' potential pivot models',
            'files_changed' => [],
            'manual_steps' => array_merge(
                $this->getManualSteps(),
                $this->generatePivotWarnings($pivotModels)
            ),
        ];
    }

    public function getAffectedFiles(string $projectPath): array
    {
        return $this->findPivotModels($projectPath . '/app/Models');
    }

    public function validate(string $projectPath): bool
    {
        return is_dir($projectPath . '/app/Models');
    }

    public function getManualSteps(): array
    {
        return [
            'Review polymorphic pivot models with custom pivot classes',
            'Table names are now pluralized - define $table property explicitly if needed',
            'Check morphTo and morphedByMany relationships',
        ];
    }

    private function hasPivotModels(string $path): bool
    {
        $files = glob($path . '/*.php');
        
        foreach ($files as $file) {
            $content = file_get_contents($file);
            if (str_contains($content, 'extends Pivot') || 
                str_contains($content, 'use Illuminate\Database\Eloquent\Relations\Pivot')) {
                return true;
            }
        }

        return false;
    }

    private function findPivotModels(string $path): array
    {
        $pivotModels = [];
        $files = glob($path . '/*.php');
        
        foreach ($files as $file) {
            $content = file_get_contents($file);
            if (str_contains($content, 'extends Pivot') || 
                str_contains($content, 'extends MorphPivot')) {
                $pivotModels[] = $file;
            }
        }

        return $pivotModels;
    }

    private function generatePivotWarnings(array $pivotModels): array
    {
        $warnings = [];
        
        foreach ($pivotModels as $model) {
            $content = file_get_contents($model);
            
            // Check if $table property is defined
            if (!preg_match('/protected\s+\$table\s*=/', $content)) {
                $warnings[] = 'Model ' . basename($model) . ' may need explicit $table property';
            }
        }

        return $warnings;
    }
}
