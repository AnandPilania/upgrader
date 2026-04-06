<?php

namespace Upgrader\Core;

/**
 * Abstract base class for all modules
 */
abstract class AbstractModule implements UpgradeModuleInterface
{
    protected array $config = [];
    protected array $dependencies = [];
    protected ?ModuleRegistry $registry = null;

    public function initialize(array $config): void
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }

    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    public function setRegistry(ModuleRegistry $registry): void
    {
        $this->registry = $registry;
    }

    /**
     * Get dependent module instance
     */
    protected function getDependency(string $moduleName): ?ModuleInterface
    {
        if (!$this->registry) {
            return null;
        }

        return $this->registry->getModule($moduleName);
    }

    /**
     * Check if all dependencies are available
     */
    public function checkDependencies(): bool
    {
        if (!$this->registry) {
            return false;
        }

        foreach ($this->getDependencies() as $dependency) {
            if (!$this->registry->hasModule($dependency)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get default configuration
     */
    protected function getDefaultConfig(): array
    {
        return [];
    }

    /**
     * Get configuration value
     */
    protected function getConfig(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    public function getConfigSchema(): array
    {
        return [];
    }

    public function canUpgrade(string $projectPath, string $fromVersion, string $toVersion): bool
    {
        $availableVersions = $this->getAvailableVersions();
        return in_array($fromVersion, $availableVersions) && in_array($toVersion, $availableVersions);
    }

    public function getUpgradePath(string $fromVersion, string $toVersion): array
    {
        $versions = $this->getAvailableVersions();
        $fromIndex = array_search($fromVersion, $versions);
        $toIndex = array_search($toVersion, $versions);

        if ($fromIndex === false || $toIndex === false || $fromIndex >= $toIndex) {
            return [];
        }

        return array_slice($versions, $fromIndex, $toIndex - $fromIndex + 1);
    }

    public function getBreakingChanges(string $fromVersion, string $toVersion): array
    {
        return [];
    }

    /**
     * Log message
     */
    protected function log(string $message, string $level = 'info'): void
    {
        // This would integrate with a logging system
        echo "[{$level}] [{$this->getName()}] {$message}\n";
    }
}
