# Modular Upgrader

## 🎉 Version 2.0 - Now with Laravel 13 Support!

A comprehensive, modular upgrade tool for modern web development stacks. Automatically upgrade PHP, Laravel, React, Vue, Tailwind CSS, TypeScript, and JavaScript with intelligent dependency management.

## 🎯 Key Features

- **Modular Architecture** - Each technology is an independent, pluggable module
- **Intelligent Dependencies** - Modules automatically upgrade their dependencies
- **Multi-Framework Support** - Works with Laravel, React, Vue, and more
- **Language Awareness** - Handles JavaScript ↔ TypeScript transitions seamlessly
- **Auto-Detection** - Automatically detects applicable modules in your project
- **Version-by-Version** - Incremental upgrades for safety
- **Dry Run Mode** - Preview changes before applying

## 📦 Available Modules

| Module        | Description                    | Dependencies                 |
| ------------- | ------------------------------ | ---------------------------- |
| `php`         | PHP version upgrades           | None                         |
| `javascript`  | JavaScript/ES version upgrades | None                         |
| `typescript`  | TypeScript upgrades            | `javascript`                 |
| `laravel`     | Laravel framework upgrades     | `php`                        |
| `reactjs`     | React.js upgrades              | `javascript` OR `typescript` |
| `vuejs`       | Vue.js upgrades                | `javascript` OR `typescript` |
| `tailwindcss` | Tailwind CSS upgrades          | None                         |

## 🔄 Dependency Flow

```
Laravel Module
  └─ depends on → PHP Module

React Module
  └─ depends on → JavaScript Module OR TypeScript Module
                       └─ depends on → JavaScript Module

Vue Module
  └─ depends on → JavaScript Module OR TypeScript Module
                       └─ depends on → JavaScript Module

TypeScript Module
  └─ depends on → JavaScript Module
```

## 🚀 Installation

```bash
composer require --dev anandpilania/upgrader
```

Or install globally:

```bash
composer global require anandpilania/upgrader
```

## 📘 Usage

### List Available Modules

```bash
bin/upgrader modules
```

Output:
```
┌────────────┬─────────┬─────────────────────┬──────────────┬─────────────────┐
│ Module     │ Version │ Description         │ Dependencies │ Supported       │
├────────────┼─────────┼─────────────────────┼──────────────┼─────────────────┤
│ php        │ 1.0.0   │ PHP upgrade module  │ None         │ 7.4, 8.0, 8.1..│
│ laravel    │ 1.0.0   │ Laravel upgrades    │ php          │ 8.0, 9.0, 10.0..│
│ reactjs    │ 1.0.0   │ React.js upgrades   │ None         │ 16.0, 17.0, 18.0│
└────────────┴─────────┴─────────────────────┴──────────────┴─────────────────┘
```

### Auto-Detect Project Modules

```bash
bin/upgrader detect
```

Output:
```
Detected Modules
├─ laravel (v9.0 → v11.0 available)
├─ php (v8.0 → v8.2 available)
├─ reactjs (v17.0 → v18.0 available)
└─ typescript (v4.5 → v5.0 available)
```

### Analyze Upgrade Path

```bash
# Analyze specific module
bin/upgrader analyze --module=laravel --target=11.0

# Analyze all detected modules
bin/upgrader analyze
```

### Upgrade a Module

```bash
# Basic upgrade
bin/upgrader upgrade --module=laravel --from=9.0 --to=11.0

# Auto-detect current version
bin/upgrader upgrade --module=laravel --to=11.0

# Upgrade with dependencies (e.g., PHP will be upgraded first)
bin/upgrader upgrade --module=laravel --to=11.0 --with-dependencies

# Dry run (preview changes)
bin/upgrader upgrade --module=laravel --to=11.0 --dry-run
```

## 🎯 Real-World Examples

### Example 1: Upgrade Laravel (with PHP)

```bash
# Laravel module depends on PHP module
# When you upgrade Laravel, it will automatically upgrade PHP if needed

bin/upgrader upgrade --module=laravel --from=9.0 --to=11.0 --with-dependencies
```

**What happens:**
1. Detects Laravel 9 requires PHP 8.0
2. Detects Laravel 11 requires PHP 8.2
3. **Automatically upgrades PHP 8.0 → 8.2 first**
4. Then upgrades Laravel 9.0 → 10.0 → 11.0

### Example 2: Upgrade React with TypeScript

```bash
# If your React project uses TypeScript, both will be upgraded

bin/upgrader upgrade --module=reactjs --from=17.0 --to=18.0 --with-dependencies
```

**What happens:**
1. Detects React uses TypeScript
2. Detects React 18 needs TypeScript 4.5+
3. **Automatically upgrades TypeScript if needed**
4. TypeScript upgrade triggers **JavaScript target upgrade**
5. Then upgrades React 17.0 → 18.0

### Example 3: Full Stack Upgrade

```bash
# Detect everything
bin/upgrader detect

# Upgrade each module
bin/upgrader upgrade --module=php --to=8.2
bin/upgrader upgrade --module=laravel --to=11.0 --with-dependencies
bin/upgrader upgrade --module=tailwindcss --to=3.4
bin/upgrader upgrade --module=vuejs --to=3.4 --with-dependencies
```

## ⚙️ Configuration

Create `.upgrader.yml` in your project root:

```yaml
modules:
  laravel:
    update_frontend: true
    run_tests: true
    backup_database: true

  reactjs:
    migrate_to_hooks: true
    enable_strict_mode: true

  typescript:
    strict_mode: true
    update_types: true

  tailwindcss:
    enable_jit: true
    update_plugins: true
```

Then run:

```bash
bin/upgrader upgrade --module=laravel --config=.upgrader.yml
```

**What happens automatically:**

```
Step 1: Dependency Check
✓ Laravel 13 requires PHP 8.3
✓ Current PHP: 8.2
✓ Upgrading PHP 8.2 → 8.3 first

Step 2: PHP Upgrade
✓ Applied PHP 8.3 transformers
✓ Updated composer.json PHP requirement

Step 3: Laravel Upgrade
✓ Updated composer.json Laravel version
✓ Applied CSRF middleware transformer (VerifyCsrfToken → PreventRequestForgery)
  - Updated 12 files
✓ Applied cache config transformer
  - Added serializable_classes configuration
✓ Applied polymorphic pivot transformer
  - Found 3 pivot models
  - Generated warnings for manual review

Step 4: Frontend Detection
✓ Detected Vue.js 3.2
✓ Vue uses TypeScript
✓ Frontend upgrades available (not applied without --with-dependencies)

Summary:
✓ PHP 8.2 → 8.3
✓ Laravel 12.0 → 13.0
✓ 15 files modified
⚠ 5 manual steps required

Manual Steps:
1. Review config/cache.php - add whitelisted classes if caching objects
2. Check .env for CACHE_PREFIX and SESSION_COOKIE
3. Update JobAttempted event listeners ($exception instead of $exceptionOccurred)
4. Review 3 pivot models - add explicit $table if needed
5. Run: php artisan test
```

## 🏗️ Architecture

### How Modules Work

Each module is self-contained and implements the `UpgradeModuleInterface`:

```php
interface UpgradeModuleInterface {
    // Detect if this module applies to the project
    public function canHandle(string $projectPath): bool;

    // Detect current version
    public function detectCurrentVersion(string $projectPath): ?string;

    // Analyze upgrade compatibility
    public function analyze(string $projectPath, string $targetVersion): array;

    // Perform the upgrade
    public function upgrade(string $from, string $to): array;

    // Declare dependencies on other modules
    public function getDependencies(): array;
}
```

### Dependency Resolution

The `ModuleRegistry` automatically resolves and initializes dependencies:

```php
$registry = new ModuleRegistry();
$registry->register(new PHPModule());
$registry->register(new LaravelModule()); // depends on PHP

// When you upgrade Laravel, PHP is automatically checked and upgraded if needed
```

### Module Communication

Modules can access their dependencies:

```php
class LaravelModule extends AbstractModule {
    protected array $dependencies = ['php'];

    public function upgrade($from, $to): array {
        // Get PHP module instance
        $phpModule = $this->getDependency('php');

        // Check if PHP upgrade is needed
        $requiredPhpVersion = $this->getRequiredPhpVersion($to);
        if ($phpModule) {
            $phpModule->upgrade($current, $requiredPhpVersion);
        }

        // Continue with Laravel upgrade...
    }
}
```

## 🔌 Creating Custom Modules

Create your own upgrade module:

```php
namespace App\Upgraders;

use Upgrader\Core\AbstractModule;

class CustomFrameworkModule extends AbstractModule
{
    protected array $dependencies = ['php', 'javascript'];

    public function getName(): string {
        return 'my-framework';
    }

    public function canHandle(string $projectPath): bool {
        return file_exists($projectPath . '/my-framework.json');
    }

    public function detectCurrentVersion(string $projectPath): ?string {
        // Detection logic
    }

    public function getAvailableVersions(): array {
        return ['1.0', '2.0', '3.0'];
    }

    public function upgrade(string $from, string $to): array {
        // Upgrade logic
    }
}
```

Register your module:

```php
$loader = new ModuleLoader();
$registry = $loader->loadAllModules();
$registry->register(new CustomFrameworkModule());
```

## 📊 Module Details

### PHP Module

Upgrades: `7.4`, `8.0`, `8.1`, `8.2`, `8.3`, `8.4`

**Features:**
- Named arguments support
- Union types migration
- Attributes transformation
- Enum support
- Readonly properties

```bash
bin/upgrader upgrade --module=php --from=8.0 --to=8.2
```

### Laravel Module

Upgrades: `8.0`, `9.0`, `10.0`, `11.0`, `12.0`, `13.0`

**Dependencies:** PHP

**Features:**
- Route namespace removal (9.0)
- Flysystem 3.x migration (9.0)
- Native type declarations (10.0)
- Application structure updates (11.0)
- Request forgery protection updates (13.0)
- Cache serialization security (13.0)
- **Auto-detects and upgrades frontend framework (React/Vue)**

```bash
bin/upgrader upgrade --module=laravel --to=13.0 --with-dependencies
```

### React Module

Upgrades: `16.0`, `16.8`, `17.0`, `18.0`, `19.0`

**Dependencies:** JavaScript OR TypeScript (auto-detected)

**Features:**
- Hooks migration (16.8)
- JSX transform update (17.0)
- Concurrent features (18.0)
- **Auto-detects TypeScript and upgrades if present**

```bash
bin/upgrader upgrade --module=reactjs --to=18.0 --with-dependencies
```

### Vue Module

Upgrades: `2.6`, `2.7`, `3.0`, `3.1`, `3.2`, `3.3`, `3.4`

**Dependencies:** JavaScript OR TypeScript (auto-detected)

**Features:**
- Composition API migration
- Script setup transformation
- Global API updates (3.0)
- **Auto-detects TypeScript and upgrades if present**

```bash
bin/upgrader upgrade --module=vuejs --to=3.4 --with-dependencies
```

### TypeScript Module

Upgrades: `4.0` → `5.4`

**Dependencies:** JavaScript

**Features:**
- Decorators support
- Const type parameters
- **Automatically upgrades JavaScript target (ES2020, ES2022, etc.)**

```bash
bin/upgrader upgrade --module=typescript --to=5.0 --with-dependencies
```

### Tailwind CSS Module

Upgrades: `2.0` → `3.4`

**No Dependencies**

**Features:**
- JIT mode enablement
- Color palette updates
- Purge → Content migration
- Deprecated class updates

```bash
bin/upgrader upgrade --module=tailwindcss --to=3.4
```

## 🧪 Testing

```bash
# Run all tests
composer test

# Test specific module
composer test -- --filter=LaravelModuleTest
```

## 🤝 Contributing

1. Fork the repository
2. Create your module in `modules/your-module/`
3. Implement `UpgradeModuleInterface`
4. Add tests
5. Submit pull request

## 📝 License

MIT License

## 🙏 Credits

Built with ❤️ for the web development community.

---

**Made modular, made simple.**

**Ready to upgrade to Laravel 13? Download and start upgrading!**
