# PHPStan Custom Rules

This directory contains custom PHPStan rules for enforcing project-specific coding standards and architectural decisions.

## Current Rules

### ForbiddenMethodsRule

**Purpose:** Prevents usage of methods that bypass security measures or violate architectural decisions.

**File:** `app/PHPStan/Rules/ForbiddenMethodsRule.php`

**Currently Forbidden Methods:**

- `hasPermissionTo()` - Must use `can()` to ensure `Gate::before` is called

## Adding New Forbidden Methods

To add a new forbidden method, edit the `$forbiddenMethods` array in `ForbiddenMethodsRule.php`:

```php
private array $forbiddenMethods = [
    'hasPermissionTo' => [
        'message' => 'Method hasPermissionTo() is forbidden. Use can() method instead to ensure Gate::before is called.',
        'identifier' => 'laravel.hasPermissionTo.forbidden'
    ],
    // Add your new forbidden method here:
    'yourForbiddenMethod' => [
        'message' => 'Clear explanation of why it is forbidden and what to use instead.',
        'identifier' => 'unique.identifier.for.your.rule'
    ],
];
```

## Creating New Custom Rules

1. Create a new rule class in this directory
2. Implement the `Rule<NodeType>` interface
3. Register the rule in `phpstan.neon`:

```neon
services:
    -
        class: App\PHPStan\Rules\YourNewRule
        tags:
            - phpstan.rules.rule
```

4. Create unit tests in `tests/Unit/PHPStan/Rules/`
5. Add the `covers()` annotation when test scope should be explicit

## Testing Custom Rules

All custom rules should have comprehensive unit tests:

```bash
# Test all PHPStan rules
./vendor/bin/pest tests/Unit/PHPStan/

# Test specific rule
./vendor/bin/pest tests/Unit/PHPStan/Rules/ForbiddenMethodsRuleTest.php
```

## Running PHPStan

```bash
# Run PHPStan analysis
./vendor/bin/phpstan

# Run via composer script
composer test:sca
```

## Documentation

For more information about creating PHPStan rules, see:

- [PHPStan Custom Rules Documentation](https://phpstan.org/developing-extensions/rules)
- [PHPStan AST Explorer](https://phpstan.org/developing-extensions/ast) for understanding PHP AST nodes
