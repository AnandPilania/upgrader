# Laravel 13 Upgrade Guide

## Overview

This guide covers the specific changes needed when upgrading from Laravel 12 to Laravel 13 using the Modular Upgrader tool.

## Requirements

### PHP Version
Laravel 13 requires **PHP 8.3** or higher.

The upgrader will automatically upgrade PHP from 8.2 → 8.3 when you run:

```bash
upgrader upgrade --module=laravel --from=12.0 --to=13.0 --with-dependencies
```

## High Impact Changes

### 1. Request Forgery Protection (CSRF Middleware)

**Impact: High**

The CSRF middleware has been renamed from `VerifyCsrfToken` to `PreventRequestForgery`.

#### What the Upgrader Does:
- ✅ Automatically updates all use statements
- ✅ Updates class references in route files
- ✅ Updates test files that exclude the middleware

#### Manual Steps Required:
```php
// Before (Laravel 12)
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;

->withoutMiddleware([VerifyCsrfToken::class]);

// After (Laravel 13)
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

->withoutMiddleware([PreventRequestForgery::class]);
```

The upgrader handles this automatically via the `PreventRequestForgeryTransformer`.

### 2. Cache Serializable Classes

**Impact: Medium**

New security feature to prevent PHP deserialization attacks.

#### What the Upgrader Does:
- ✅ Adds `serializable_classes` configuration to `config/cache.php`
- ✅ Sets default to `false` (most secure)

#### Manual Steps Required:

If your application caches PHP objects, you must whitelist them:

```php
// config/cache.php
'serializable_classes' => [
    App\Data\CachedDashboardStats::class,
    App\Support\CachedPricingSnapshot::class,
],
```

**Warning:** If you previously cached arbitrary objects and don't whitelist them, they will fail to unserialize after upgrading.

## Medium Impact Changes

### 3. Cache Prefixes and Session Cookie Names

**Impact: Low to Medium**

Default naming conventions have changed:

```php
// Laravel 12
Str::slug(env('APP_NAME', 'laravel'), '_').'_cache_';
Str::slug(env('APP_NAME', 'laravel'), '_').'_session';

// Laravel 13
Str::slug(env('APP_NAME', 'laravel')).'-cache-';
Str::snake(env('APP_NAME', 'laravel')).'_session';
```

#### Manual Steps:
Set explicit values in `.env` to maintain consistency:

```bash
CACHE_PREFIX=myapp_cache_
REDIS_PREFIX=myapp_database_
SESSION_COOKIE=myapp_session
```

### 4. Container::call and Nullable Defaults

**Impact: Low**

```php
$container->call(function (?Carbon $date = null) {
    return $date;
});

// Laravel 12: Returns Carbon instance
// Laravel 13: Returns null
```

Review method injection code that relies on nullable class parameters.

## Low Impact Changes

### 5. Polymorphic Pivot Table Names

Custom pivot models now use **pluralized** table names by default.

#### What the Upgrader Does:
- ✅ Detects custom pivot models
- ✅ Warns if `$table` property is not explicitly set

#### Manual Steps:
```php
class RoleUser extends MorphPivot
{
    // Explicitly define table name if needed
    protected $table = 'role_user'; // instead of 'role_users'
}
```

### 6. Queue Event Changes

The `JobAttempted` event now provides `$exception` instead of `$exceptionOccurred`:

```php
// Laravel 12
if ($event->exceptionOccurred) { }

// Laravel 13
if ($event->exception) { }
```

### 7. Pagination View Names

Bootstrap 3 pagination views renamed:

```php
// Laravel 12
pagination::default
pagination::simple-default

// Laravel 13
pagination::bootstrap-3
pagination::simple-bootstrap-3
```

## Automated vs Manual Changes

### Automated by Upgrader

✅ **Fully Automated:**
- CSRF middleware class renames
- Cache configuration additions
- PHP version upgrade (8.2 → 8.3)
- Composer dependency updates
- Import statement updates

⚠️ **Partially Automated (warnings provided):**
- Polymorphic pivot models
- Cache prefix changes
- Session cookie naming

❌ **Manual Review Required:**
- Serializable classes whitelist
- Container nullable parameter usage
- Queue event listener updates

## Step-by-Step Upgrade Process

### 1. Analyze Your Project

```bash
upgrader analyze --module=laravel --target=13.0
```

This will show:
- Breaking changes affecting your code
- Required PHP version upgrade
- Frontend framework compatibility

### 2. Review the Analysis

Check the output for:
- Number of files using `VerifyCsrfToken`
- Pivot models without explicit table names
- Custom cache serialization usage

### 3. Run the Upgrade

```bash
# With automatic dependency upgrades (recommended)
upgrader upgrade --module=laravel --from=12.0 --to=13.0 --with-dependencies

# Dry run first (preview changes)
upgrader upgrade --module=laravel --from=12.0 --to=13.0 --dry-run
```

### 4. Handle Manual Steps

After the automated upgrade:

1. **Review config/cache.php**
   - Add whitelisted classes if you cache objects
   
2. **Update .env if needed**
   - Set explicit CACHE_PREFIX and SESSION_COOKIE

3. **Test queue listeners**
   - Update JobAttempted event handlers

4. **Review pivot models**
   - Add explicit $table properties where needed

5. **Run tests**
   ```bash
   php artisan test
   ```

## Common Issues and Solutions

### Issue 1: Cache Deserialization Errors

**Error:** `Unserialize failed: Class not in allowed classes list`

**Solution:**
```php
// config/cache.php
'serializable_classes' => [
    YourCachedClass::class,
],
```

### Issue 2: Session Not Persisting

**Error:** Users getting logged out after upgrade

**Solution:**
```bash
# .env
SESSION_COOKIE=original_session_name
```

### Issue 3: CSRF Test Failures

**Error:** `Class VerifyCsrfToken not found`

**Solution:**
```php
// Update test files
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

$this->withoutMiddleware([PreventRequestForgery::class]);
```

## Testing Checklist

After upgrading, verify:

- [ ] Application loads without errors
- [ ] Authentication works (login/logout)
- [ ] Forms submit successfully (CSRF protection)
- [ ] Cached data loads correctly
- [ ] Queued jobs process
- [ ] Tests pass
- [ ] Database migrations run
- [ ] API endpoints respond

## Rollback Plan

If issues occur:

1. **Git rollback:**
   ```bash
   git reset --hard HEAD~1
   ```

2. **Restore from backup:**
   The upgrader creates automatic backups in `.laravel-upgrader-backups/`

3. **Restore cached data:**
   If cache issues occur, clear and rebuild:
   ```bash
   php artisan cache:clear
   php artisan config:cache
   ```

## Official Resources

- [Laravel 13 Upgrade Guide](https://laravel.com/docs/13.x/upgrade)
- [Laravel 13 Release Notes](https://laravel.com/docs/13.x/releases)
- [GitHub Comparison](https://github.com/laravel/laravel/compare/12.x...13.x)

## Need Help?

If you encounter issues:

1. Run with verbose output:
   ```bash
   upgrader upgrade --module=laravel --to=13.0 -vvv
   ```

2. Check the upgrade report:
   ```bash
   cat upgrade-report.md
   ```

3. Review manual steps:
   The upgrader lists all manual steps required after automated changes.

---

**Estimated Total Upgrade Time: 30-60 minutes**
- Automated changes: 10 minutes
- Manual review: 20-30 minutes
- Testing: 20-30 minutes
