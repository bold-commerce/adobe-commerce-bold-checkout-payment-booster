# Coding Standards

## Overview

The module follows **PSR-12** and **Magento 2 coding standards** with strict type enforcement and static analysis.

**Critical**: The module supports **PHP 7.2 - 8.5** to maintain compatibility with multiple Magento 2 and Adobe Commerce versions. Code must be backward compatible with PHP 7.2.

---

## PHP Version Compatibility

### Supported Versions

**Requirement**: `php: >=7.2 <8.6` (from `composer.json`)

**Why This Matters**: The module must work on Magento 2.3.x (PHP 7.2+) through Magento 2.4.x (PHP 8.1-8.3+).

### Allowed PHP Features

✅ **Can Use** (PHP 7.0+):
- Scalar type hints (`int`, `string`, `bool`, `float`)
- Return type hints
- `void` return type
- Null coalescing operator (`??`)
- Spaceship operator (`<=>`)

✅ **Can Use** (PHP 7.1+):
- Nullable types (`?Type`)
- `iterable` type
- Class constant visibility

✅ **Can Use** (PHP 7.2+):
- `object` type hint
- Parameter type widening

❌ **Cannot Use** (PHP 7.4+):
- **Typed properties** (`private Type $property`)
- Arrow functions (`fn() =>`)
- Null coalescing assignment (`??=`)

❌ **Cannot Use** (PHP 8.0+):
- `mixed` type
- Union types (`Type1|Type2`)
- Named arguments
- Attributes
- Constructor property promotion

❌ **Cannot Use** (PHP 8.1+):
- `never` return type
- Readonly properties
- Enums
- Intersection types

### Backward Compatibility Rule

**Always code to the minimum supported version (PHP 7.2)** to ensure the module works on all supported Magento installations.

---

## PHP Code Standards

### PSR-12

**Standard**: PHP-FIG PSR-12 coding style

**Key Requirements**:
- 4 spaces indentation (no tabs)
- Opening braces on same line (methods, classes)
- One statement per line
- Single blank line after namespace
- Visibility keywords on all properties/methods

**Validation**: `.phpcs.xml` configuration

### Magento 2 Standards

**Standards Applied**:
- Magento2 (via phpcs ruleset)
- PSR-12 (via phpcs ruleset)
- MEQP2 (Magento Extension Quality Program)

**Key Patterns**:
- Service contracts (interfaces in `Api/`)
- Dependency injection (constructor injection)
- Plugin pattern (interceptors)
- Observer pattern (event-driven)
- Repository pattern (data access)

---

## Type System

### Strict Types

**Required**: All PHP files must declare strict types

```php
<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model;
```

**Why**: Prevents type coercion bugs, enforces type safety

### Type Hints

**Required**: All parameters and return types must be typed

```php
public function execute(Quote $quote, int $websiteId): bool
{
    // Implementation
}
```

**Allowed Types**:
- Scalar types: `int`, `float`, `string`, `bool`
- Classes/interfaces: Fully qualified or imported
- Arrays: `array` (with DocBlock detail)
- Nullable: `?Type` syntax (PHP 7.1+)
- Object: `object` (PHP 7.2+)
- Iterable: `iterable` (PHP 7.1+)

**Not Allowed** (backward compatibility):
- `mixed` type (requires PHP 8.0+)
- Union types like `string|int` (requires PHP 8.0+)
- Typed properties (requires PHP 7.4+)

### No Type Hints Exceptions

**When to skip**:
- Magento parent class method doesn't have types (compatibility)
- Third-party interface implementation without types

---

## Static Analysis

### PHPStan

**Configuration**: `phpstan.neon`

**Level**: Level 7 (strict)

**Command**: `vendor/bin/phpstan analyse`

**Requirements**:
- No undefined variables
- No incorrect type usage
- No dead code
- Correct return types
- Property initialization

**Excluded**:
- Generated code (`/dev/`)
- Test files (none yet)

---

## DocBlock Standards

### Required DocBlocks

**Classes**:
```php
/**
 * Brief description of class purpose
 *
 * Longer description if needed
 */
class ClassName
{
}
```

**Methods**:
```php
/**
 * Brief description of what method does
 *
 * @param Type $param Parameter description
 * @param int $count Number of items
 * @return bool Success indicator
 * @throws LocalizedException When validation fails
 */
public function execute(Type $param, int $count): bool
{
}
```

**Properties**:
```php
/**
 * @var ConfigInterface Configuration manager
 */
private $config;
```

**Note**: Do NOT use typed properties (`private Type $property`) as they require PHP 7.4+. Use `@var` DocBlocks instead.

### DocBlock Details

**Arrays**: Specify structure in DocBlock
```php
/**
 * @param array<string, mixed> $data Data array
 * @return array{id: int, name: string}
 */
```

**Nullable**: Indicate with `?Type` in hint and `@param`
```php
/**
 * @param string|null $value Optional value
 */
public function process(?string $value): void
```

---

## Naming Conventions

### Classes

**Pattern**: PascalCase
```
CartManagementApi
HydrateOrderFromQuote
UpdatePayments
```

### Methods

**Pattern**: camelCase
```
execute()
getApiUrl()
isAuthorized()
canInvoice()
```

### Variables

**Pattern**: camelCase
```
$cartId
$publicOrderId
$sharedSecret
```

### Constants

**Pattern**: SCREAMING_SNAKE_CASE
```
const API_VERSION = 'v1';
const DEFAULT_TIMEOUT = 30;
```

---

## Code Organization

### Single Responsibility

**Pattern**: One class, one purpose

**Good**:
- `InvoiceOrder.php` - Only creates invoices
- `RefundOrder.php` - Only creates refunds

**Bad**:
- `OrderManager.php` - Handles everything (too broad)

### Dependency Injection

**Pattern**: Constructor injection, no `ObjectManager`

**Good**:
```php
public function __construct(
    ConfigInterface $config,
    BoldClientInterface $client
) {
    $this->config = $config;
    $this->client = $client;
}
```

**Bad**:
```php
$config = ObjectManager::getInstance()->get(Config::class); // Never do this
```

### Interfaces First

**Pattern**: Program to interfaces, not implementations

**Preference**: Use `ConfigInterface`, not `Config`

**Mapping**: Done in `etc/di.xml`

---

## File Organization

### Directory Structure

**Pattern**: Magento 2 structure

```
Api/           # Interfaces only
Model/         # Implementations
Block/         # View blocks
Controller/    # Controllers
Observer/      # Event observers
Plugin/        # Interceptors
etc/           # Configuration
view/          # Templates/layouts/JS
```

### File Naming

**Pattern**: Match class name exactly

- Class: `CartManagementApi`
- File: `CartManagementApi.php`

**Case-sensitive**: Must match exactly

---

## Error Handling

### Exceptions

**Pattern**: Use appropriate Magento exceptions

```php
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\AuthorizationException;

if (!$quote) {
    throw new NoSuchEntityException(__('Quote not found'));
}
```

### Error Messages

**Pattern**: Use `__()` for translation

```php
throw new LocalizedException(__('Invalid cart ID: %1', $cartId));
```

---

## Best Practices

### Avoid

❌ ObjectManager directly
❌ Direct database queries (use repositories)
❌ Global state
❌ Static methods (except factories)
❌ Magic numbers (use constants)
❌ Deep nesting (max 3-4 levels)
❌ Long methods (> 50 lines)

### Prefer

✅ Dependency injection
✅ Service contracts
✅ Repository pattern
✅ Early returns
✅ Guard clauses
✅ Descriptive names
✅ Small, focused methods

---

## Code Review Checklist

### Before Committing

- [ ] **PHP 7.2 compatible** (no typed properties, no `mixed`, no union types)
- [ ] `declare(strict_types=1)` at top of file
- [ ] All types hinted (params and returns, but NOT properties)
- [ ] DocBlocks on all classes/methods/properties
- [ ] No ObjectManager usage
- [ ] Following Magento 2 patterns
- [ ] PSR-12 compliant
- [ ] PHPStan level 7 passing
- [ ] No linter errors

### PHP Compatibility Check

Run on PHP 7.2 environment if possible, or carefully review:
- No typed properties
- No PHP 7.4+ or 8.0+ features
- Code works on minimum supported version

---

## Validation Tools

### PHPCS (Code Sniffer)

**Config**: `.phpcs.xml`

**Command**: `vendor/bin/phpcs`

**Standards**: PSR12, Magento2

### PHPStan

**Config**: `phpstan.neon`

**Command**: `vendor/bin/phpstan analyse`

**Level**: 7

### IDE Integration

**Recommended**: Configure IDE to use `.phpcs.xml` and `phpstan.neon`

---

## Summary

### Key Standards

1. **PHP 7.2+ Compatibility** - Code to minimum version
2. **PSR-12** - Code style
3. **Strict Types** - All files (`declare(strict_types=1)`)
4. **Type Hints** - All params/returns (no typed properties!)
5. **PHPStan Level 7** - No type errors
6. **DocBlocks** - All classes/methods
7. **Magento 2 Patterns** - Service contracts, DI, plugins, observers

### Quick Reference

- PHP version: >=7.2 (backward compatible)
- Indentation: 4 spaces
- Type hints: Required (but NO typed properties)
- Strict types: Required
- DocBlocks: Required (especially for properties)
- DI: Constructor injection
- Exceptions: Use Magento exceptions
- Messages: Use `__()`

### PHP Compatibility Reminders

- ❌ NO typed properties (`private Type $property`)
- ❌ NO `mixed` type
- ❌ NO union types (`Type1|Type2`)
- ❌ NO arrow functions
- ✅ YES nullable types (`?Type`)
- ✅ YES scalar type hints
- ✅ YES return type hints

### Validation

```bash
vendor/bin/phpcs                    # Check code style
vendor/bin/phpstan analyse          # Check types
```

---

## Local Development Commands

### Check PHPCS (Code Style)

**From `/var/www/html` (inside Warden shell):**

**Run PHPCS on entire module (errors only):**

```bash
cd /var/www/html
find app/code/Bold/CheckoutPaymentBooster -name "*.php" -not -path "*/vendor/*" -not -path "*/.idea/*" | xargs vendor/bin/phpcs -n --standard=app/code/Bold/CheckoutPaymentBooster/.phpcs.xml 2>&1 | grep -v "DEPRECATED: "
```

**Run PHPCS on changed files only (errors only):**

```bash
cd /var/www/html
git -C app/code/Bold/CheckoutPaymentBooster diff --name-only HEAD~1 HEAD | grep '\.php$' | sed 's|^|app/code/Bold/CheckoutPaymentBooster/|' | xargs vendor/bin/phpcs -n --standard=app/code/Bold/CheckoutPaymentBooster/.phpcs.xml 2>&1 | grep -v "DEPRECATED: "
```

### Run PHPStan (Type Analysis)

**From `/var/www/html` (inside Warden shell):**

**Run PHPStan on entire module (same as pipeline):**

```bash
cd /var/www/html
vendor/bin/phpstan analyse --configuration=app/code/Bold/CheckoutPaymentBooster/phpstan.neon app/code/Bold/CheckoutPaymentBooster
```

**Run PHPStan on changed files only:**

```bash
cd /var/www/html
git -C app/code/Bold/CheckoutPaymentBooster diff --name-only HEAD~1 HEAD | grep '\.php$' | sed 's|^|app/code/Bold/CheckoutPaymentBooster/|' | xargs vendor/bin/phpstan analyse --configuration=app/code/Bold/CheckoutPaymentBooster/phpstan.neon
```

**Note:** PHPStan errors must be fixed (unlike PHPCS warnings). The module uses level 7 (strict) with a baseline file for known issues.
