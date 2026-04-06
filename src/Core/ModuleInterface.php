<?php

declare(strict_types=1);

namespace Upgrader\Core;

/**
 * Base interface for all upgrade modules
 */
interface ModuleInterface
{
    /**
     * Get module name
     */
    public function getName(): string;

    /**
     * Get module version
     */
    public function getVersion(): string;

    /**
     * Get module description
     */
    public function getDescription(): string;

    /**
     * Get module dependencies (other modules this module depends on)
     *
     * @return array<string> Array of module names
     */
    public function getDependencies(): array;

    /**
     * Check if module can handle the given project
     */
    public function canHandle(string $projectPath): bool;

    /**
     * Detect current version in project
     */
    public function detectCurrentVersion(string $projectPath): ?string;

    /**
     * Get available upgrade paths
     */
    public function getAvailableVersions(): array;

    /**
     * Initialize module with configuration
     */
    public function initialize(array $config): void;

    /**
     * Get module-specific configuration schema
     */
    public function getConfigSchema(): array;
}
