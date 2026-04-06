<?php

namespace Upgrader\Services;

use Upgrader\Core\ModuleRegistry;
use Upgrader\Modules\PHP\PHPModule;
use Upgrader\Modules\Laravel\LaravelModule;
use Upgrader\Modules\ReactJS\ReactJSModule;
use Upgrader\Modules\VueJS\VueJSModule;
use Upgrader\Modules\TailwindCSS\TailwindCSSModule;
use Upgrader\Modules\TypeScript\TypeScriptModule;
use Upgrader\Modules\JavaScript\JavaScriptModule;

/**
 * Service for loading and registering all available modules
 */
class ModuleLoader
{
    /**
     * Load all available modules into registry
     */
    public function loadAllModules(): ModuleRegistry
    {
        $registry = new ModuleRegistry();

        // Register base language modules (no dependencies)
        $registry->register(new PHPModule());
        $registry->register(new JavaScriptModule());

        // Register language modules with dependencies
        $registry->register(new TypeScriptModule());

        // Register framework modules
        $registry->register(new LaravelModule());
        $registry->register(new ReactJSModule());
        $registry->register(new VueJSModule());

        // Register CSS framework modules
        $registry->register(new TailwindCSSModule());

        return $registry;
    }

    /**
     * Load specific modules by name
     */
    public function loadModules(array $moduleNames): ModuleRegistry
    {
        $registry = new ModuleRegistry();
        $allModules = $this->getAllAvailableModules();

        foreach ($moduleNames as $name) {
            if (isset($allModules[$name])) {
                $registry->register($allModules[$name]);
            }
        }

        return $registry;
    }

    /**
     * Get all available module instances
     */
    private function getAllAvailableModules(): array
    {
        return [
            'php' => new PHPModule(),
            'javascript' => new JavaScriptModule(),
            'typescript' => new TypeScriptModule(),
            'laravel' => new LaravelModule(),
            'reactjs' => new ReactJSModule(),
            'vuejs' => new VueJSModule(),
            'tailwindcss' => new TailwindCSSModule(),
        ];
    }
}
