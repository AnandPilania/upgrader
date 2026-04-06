# Module Architecture

## Overview

The Modular Upgrader uses a plugin-based architecture where each technology (PHP, Laravel, React, Vue, etc.) is an independent module that can declare dependencies on other modules.

## Core Components

### 1. ModuleInterface

Base interface that all modules must implement:

```php
interface ModuleInterface {
    public function getName(): string;
    public function getVersion(): string;
    public function getDescription(): string;
    public function getDependencies(): array;
    public function canHandle(string $projectPath): bool;
    public function detectCurrentVersion(string $projectPath): ?string;
    public function getAvailableVersions(): array;
    public function initialize(array $config): void;
}
```

### 2. UpgradeModuleInterface

Extends ModuleInterface with upgrade capabilities:

```php
interface UpgradeModuleInterface extends ModuleInterface {
    public function analyze(string $projectPath, string $target): array;
    public function upgrade(string $from, string $to): array;
    public function getTransformers(string $from, string $to): array;
    public function canUpgrade(string $from, string $to): bool;
    public function getUpgradePath(string $from, string $to): array;
}
```

### 3. AbstractModule

Base class providing common functionality:

```php
abstract class AbstractModule implements UpgradeModuleInterface {
    protected array $config = [];
    protected array $dependencies = [];
    protected ?ModuleRegistry $registry = null;
    
    protected function getDependency(string $name): ?ModuleInterface;
    public function checkDependencies(): bool;
    protected function getConfig(string $key, mixed $default = null);
}
```

### 4. ModuleRegistry

Central registry managing all modules:

```php
class ModuleRegistry {
    public function register(ModuleInterface $module): void;
    public function getModule(string $name): ?ModuleInterface;
    public function detectModules(string $projectPath): array;
    public function getDependencyGraph(): array;
    public function validateDependencies(): array;
    public function getModulesInOrder(): array; // Topological sort
}
```

## Module Lifecycle

### 1. Registration

```php
$registry = new ModuleRegistry();
$registry->register(new PHPModule());
$registry->register(new LaravelModule()); // Depends on PHP
$registry->register(new TypeScriptModule()); // Depends on JavaScript
```

### 2. Detection

```php
$detected = $registry->detectModules('/path/to/project');

// Returns:
// [
//     'laravel' => [
//         'module' => LaravelModule instance,
//         'current_version' => '9.0',
//         'available_versions' => ['8.0', '9.0', '10.0', '11.0']
//     ],
//     'php' => [...],
//     ...
// ]
```

### 3. Initialization

```php
$config = [
    'laravel' => [
        'update_frontend' => true,
        'run_tests' => true
    ],
    'php' => [
        'strict_types' => false
    ]
];

$registry->initializeModule('laravel', $config);
// Automatically initializes PHP module first (dependency)
```

### 4. Upgrade

```php
$orchestrator = new UpgradeOrchestrator($registry, $io);
$result = $orchestrator->upgrade(
    'laravel',      // Module name
    '/path',        // Project path
    '9.0',          // From version
    '11.0',         // To version
    [
        'with_dependencies' => true,  // Upgrade PHP first
        'dry_run' => false
    ]
);
```

## Dependency Resolution

### Automatic Dependency Upgrades

When upgrading a module with `--with-dependencies`, the system:

1. **Recursively resolves** all dependencies
2. **Orders upgrades** based on dependency graph (topological sort)
3. **Checks versions** for each dependency
4. **Upgrades dependencies first**, then the requested module

Example:

```
Laravel 9 → 11 upgrade with dependencies:

1. Laravel requires PHP
2. Laravel 11 requires PHP 8.2
3. Current PHP is 8.0
4. Upgrade order:
   a. PHP 8.0 → 8.2 (dependency)
   b. Laravel 9 → 10 → 11 (main module)
```

### Conditional Dependencies

React and Vue modules use conditional dependencies:

```php
class ReactJSModule extends AbstractModule {
    public function getDependencies(): array {
        // Dependencies determined at runtime
        return [];
    }
    
    public function upgrade(...) {
        // Detect if project uses TypeScript or JavaScript
        $useTypeScript = $this->usesTypeScript($projectPath);
        
        // Get appropriate language module
        $langModule = $useTypeScript 
            ? $this->registry->getModule('typescript')
            : $this->registry->getModule('javascript');
            
        // Upgrade language if needed
        if ($langModule) {
            $langModule->upgrade(...);
        }
    }
}
```

## Module Communication

### Accessing Dependencies

Modules can access their dependencies through the registry:

```php
class LaravelModule extends AbstractModule {
    protected array $dependencies = ['php'];
    
    public function upgrade($from, $to): array {
        // Get PHP module
        $phpModule = $this->getDependency('php');
        
        if ($phpModule) {
            $currentPhp = $phpModule->detectCurrentVersion($path);
            $requiredPhp = $this->getRequiredPhpVersion($to);
            
            if ($currentPhp !== $requiredPhp) {
                // Trigger PHP upgrade
                $phpModule->upgrade($currentPhp, $requiredPhp);
            }
        }
        
        // Continue with Laravel upgrade
        $this->applyTransformers(...);
    }
}
```

### Sharing Information

Modules can share analysis results:

```php
$analysis = $laravelModule->analyze($path, '11.0');

// Returns:
// [
//     'php_upgrade_required' => true,
//     'php_analysis' => [
//         'current_version' => '8.0',
//         'target_version' => '8.2',
//         'breaking_changes' => [...]
//     ],
//     'frontend_framework' => 'reactjs',
//     'breaking_changes' => [...]
// ]
```

## Transformers

Each module uses transformers for version-specific changes:

```php
interface TransformerInterface {
    public function getName(): string;
    public function getPriority(): int;
    public function shouldRun(string $projectPath): bool;
    public function transform(string $projectPath): array;
    public function getManualSteps(): array;
}
```

### Example Transformer

```php
class RouteNamespaceTransformer implements TransformerInterface {
    public function getName(): string {
        return 'Route Namespace Removal';
    }
    
    public function getPriority(): int {
        return 100; // Higher runs first
    }
    
    public function shouldRun(string $path): bool {
        $file = $path . '/app/Providers/RouteServiceProvider.php';
        if (!file_exists($file)) return false;
        
        $content = file_get_contents($file);
        return str_contains($content, '$namespace');
    }
    
    public function transform(string $path): array {
        // Perform transformation
        return [
            'success' => true,
            'message' => 'Removed route namespace',
            'files_changed' => ['RouteServiceProvider.php']
        ];
    }
    
    public function getManualSteps(): array {
        return [
            'Update route files to use fully qualified class names'
        ];
    }
}
```

## Module Directory Structure

```
modules/
├── php/
│   ├── src/
│   │   ├── PHPModule.php
│   │   └── Transformers/
│   │       ├── PHP80/
│   │       ├── PHP81/
│   │       └── PHP82/
│   └── composer.json
│
├── laravel/
│   ├── src/
│   │   ├── LaravelModule.php
│   │   └── Transformers/
│   │       ├── Laravel9/
│   │       ├── Laravel10/
│   │       └── Laravel11/
│   └── composer.json
│
├── reactjs/
│   ├── src/
│   │   ├── ReactJSModule.php
│   │   └── Transformers/
│   │       ├── React168/
│   │       ├── React17/
│   │       └── React18/
│   └── composer.json
│
└── [other modules...]
```

## Creating New Modules

### Step 1: Create Module Class

```php
namespace Upgrader\Modules\MyFramework;

use Upgrader\Core\AbstractModule;

class MyFrameworkModule extends AbstractModule {
    protected array $dependencies = ['php', 'javascript'];
    
    public function getName(): string {
        return 'my-framework';
    }
    
    public function canHandle(string $path): bool {
        return file_exists($path . '/my-framework.json');
    }
    
    // Implement other required methods...
}
```

### Step 2: Create Transformers

```php
namespace Upgrader\Modules\MyFramework\Transformers\V2;

use Upgrader\Core\TransformerInterface;

class NewAPITransformer implements TransformerInterface {
    // Implement transformation logic
}
```

### Step 3: Register Module

```php
// In ModuleLoader.php
public function loadAllModules(): ModuleRegistry {
    $registry = new ModuleRegistry();
    
    // ... existing modules
    
    $registry->register(new MyFrameworkModule());
    
    return $registry;
}
```

## Dependency Graph Visualization

```
                    ┌─────────────┐
                    │ JavaScript  │ (base)
                    └──────┬──────┘
                           │
                    ┌──────▼──────┐
                    │ TypeScript  │
                    └──────┬──────┘
                           │
                 ┌─────────┴─────────┐
                 │                   │
          ┌──────▼──────┐     ┌─────▼──────┐
          │   React     │     │    Vue     │
          └─────────────┘     └────────────┘


    ┌─────────┐
    │   PHP   │ (base)
    └────┬────┘
         │
    ┌────▼────┐
    │ Laravel │
    └─────────┘


    ┌──────────────┐
    │ TailwindCSS  │ (independent)
    └──────────────┘
```

## Best Practices

### 1. Single Responsibility

Each module handles one technology:
- ✅ PHPModule handles PHP upgrades
- ✅ LaravelModule handles Laravel upgrades
- ❌ Don't mix responsibilities

### 2. Declare Dependencies Clearly

```php
class LaravelModule extends AbstractModule {
    // Clear dependency declaration
    protected array $dependencies = ['php'];
}
```

### 3. Graceful Degradation

Modules should work even if optional dependencies are missing:

```php
public function upgrade(...) {
    $phpModule = $this->getDependency('php');
    
    if ($phpModule) {
        // Upgrade PHP if available
    } else {
        // Continue without PHP upgrade, log warning
        $this->log('PHP module not available', 'warning');
    }
}
```

### 4. Version Detection

Always implement robust version detection:

```php
public function detectCurrentVersion(string $path): ?string {
    // Try multiple methods
    $version = $this->detectFromComposer($path)
        ?? $this->detectFromLockFile($path)
        ?? $this->detectFromFrameworkFile($path);
        
    return $version;
}
```

### 5. Clear Error Messages

```php
if (!$module->canHandle($path)) {
    throw new \RuntimeException(
        "Module '{$module->getName()}' cannot handle this project. " .
        "Make sure the project contains the required files."
    );
}
```

## Testing Modules

```php
class LaravelModuleTest extends TestCase {
    public function test_detects_laravel_project() {
        $module = new LaravelModule();
        $this->assertTrue($module->canHandle('/path/to/laravel'));
    }
    
    public function test_detects_current_version() {
        $module = new LaravelModule();
        $version = $module->detectCurrentVersion('/path/to/laravel');
        $this->assertEquals('9.0', $version);
    }
    
    public function test_declares_php_dependency() {
        $module = new LaravelModule();
        $this->assertContains('php', $module->getDependencies());
    }
}
```

---

**The modular architecture makes it easy to add new frameworks and technologies while maintaining clean separation of concerns.**
