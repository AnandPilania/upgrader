<?php

namespace Upgrader\Core;

/**
 * Interface for version-specific transformers
 */
interface TransformerInterface
{
    /**
     * Get transformer name
     */
    public function getName(): string;

    /**
     * Get transformer description
     */
    public function getDescription(): string;

    /**
     * Get transformer priority (higher = runs first)
     */
    public function getPriority(): int;

    /**
     * Check if transformer should run
     */
    public function shouldRun(string $projectPath): bool;

    /**
     * Transform the project
     * 
     * @param string $projectPath Path to project
     * @return array Result with 'success', 'message', and 'changes'
     */
    public function transform(string $projectPath): array;

    /**
     * Get files affected by this transformer
     */
    public function getAffectedFiles(string $projectPath): array;

    /**
     * Validate transformation can be applied
     */
    public function validate(string $projectPath): bool;

    /**
     * Get required manual steps
     */
    public function getManualSteps(): array;
}
