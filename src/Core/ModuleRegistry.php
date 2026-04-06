<?php

declare(strict_types=1);

namespace Upgrader\Core;

use RuntimeException;

/**
 * Registry for managing all available modules
 */
class ModuleRegistry
{
    /** @var array<string, ModuleInterface> */
    private array $modules = [];

    /** @var array<string, bool> */
    private array $initialized = [];

    /**
     * Register a module
     */
    public function register(ModuleInterface $module): void
    {
        $this->modules[$module->getName()] = $module;
        $this->initialized[$module->getName()] = false;

        if (method_exists($module, 'setRegistry')) {
            $module->setRegistry($this);
        }
    }

    /**
     * Get a module by name
     */
    public function getModule(string $name): ?ModuleInterface
    {
        return $this->modules[$name] ?? null;
    }

    /**
     * Check if module exists
     */
    public function hasModule(string $name): bool
    {
        return isset($this->modules[$name]);
    }

    /**
     * Get all registered modules
     */
    public function getAll(): array
    {
        return $this->modules;
    }

    /**
     * Get modules by type/interface
     */
    public function getModulesByType(string $interface): array
    {
        return array_filter($this->modules, fn ($module) => $module instanceof $interface);
    }

    /**
     * Initialize a module and its dependencies
     */
    public function initializeModule(string $name, array $config = []): bool
    {
        if ($this->initialized[$name] ?? false) {
            return true;
        }

        $module = $this->getModule($name);
        if (! $module) {
            return false;
        }

        // Initialize dependencies first
        foreach ($module->getDependencies() as $dependency) {
            if (! $this->initializeModule($dependency, $config[$dependency] ?? [])) {
                throw new RuntimeException("Failed to initialize dependency: {$dependency}");
            }
        }

        // Initialize the module
        $module->initialize($config[$name] ?? []);
        $this->initialized[$name] = true;

        return true;
    }

    /**
     * Detect all applicable modules for a project
     */
    public function detectModules(string $projectPath): array
    {
        $detected = [];

        foreach ($this->modules as $name => $module) {
            if ($module->canHandle($projectPath)) {
                $detected[$name] = [
                    'module' => $module,
                    'current_version' => $module->detectCurrentVersion($projectPath),
                    'available_versions' => $module->getAvailableVersions(),
                ];
            }
        }

        return $detected;
    }

    /**
     * Get dependency graph
     */
    public function getDependencyGraph(): array
    {
        $graph = [];

        foreach ($this->modules as $name => $module) {
            $graph[$name] = $module->getDependencies();
        }

        return $graph;
    }

    /**
     * Validate module dependencies
     */
    public function validateDependencies(): array
    {
        $errors = [];

        foreach ($this->modules as $name => $module) {
            foreach ($module->getDependencies() as $dependency) {
                if (! $this->hasModule($dependency)) {
                    $errors[] = "Module '{$name}' depends on '{$dependency}' which is not registered";
                }
            }
        }

        return $errors;
    }

    /**
     * Get modules in dependency order (topological sort)
     */
    public function getModulesInOrder(): array
    {
        $graph = $this->getDependencyGraph();
        $sorted = [];
        $visited = [];

        $visit = function ($name) use (&$visit, &$sorted, &$visited, $graph) {
            if (isset($visited[$name])) {
                return;
            }

            $visited[$name] = true;

            foreach ($graph[$name] ?? [] as $dependency) {
                $visit($dependency);
            }

            $sorted[] = $name;
        };

        foreach (array_keys($graph) as $name) {
            $visit($name);
        }

        return array_map(fn ($name) => $this->modules[$name], $sorted);
    }
}
