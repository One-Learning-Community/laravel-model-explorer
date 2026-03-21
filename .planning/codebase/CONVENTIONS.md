# Coding Conventions

**Analysis Date:** 2026-03-20

## Naming Patterns

**Files:**
- Classes use PascalCase: `ModelExplorerController.php`, `AssetController.php`, `Authorize.php`
- Configuration files use snake_case: `model-explorer.php`
- Factory files follow pattern: `ModelFactory.php`

**Functions:**
- Public methods use camelCase: `configurePackage()`, `packageBooted()`, `__invoke()`
- Protected methods use camelCase with visibility: `getPackageProviders()`, `getEnvironmentSetUp()`
- Private constants use SCREAMING_SNAKE_CASE: `ALLOWED_EXTENSIONS`

**Variables:**
- Local variables use camelCase: `$publicPath`, `$assetPath`, `$extension`
- Configuration keys use kebab-case: `model-explorer.enabled`, `model-explorer.path`, `model-explorer.middleware`

**Types:**
- Class names use PascalCase with vendor namespace: `OneLearningCommunity\LaravelModelExplorer\Http\Controllers\ModelExplorerController`
- Interface names use PascalCase: `Illuminate\Contracts\View\View`

## Code Style

**Formatting:**
- PHPStan is used for static analysis at level 5
- Laravel Pint is configured for automatic code formatting (no `pint.json` in package root — inherited from workspace)
- Code uses PSR-12 style conventions with Laravel enhancements

**Linting:**
- PHPStan configuration: `phpstan.neon.dist` at level 5
- Paths analyzed: `src/`, `config/`, `database/`
- Debugging functions banned: `dd()`, `dump()`, `ray()` (enforced via ArchTest)

## Import Organization

**Order:**
1. `namespace` declaration
2. Blank line
3. `use` statements (alphabetically ordered)
4. Blank line before class definition

**Pattern:**
```php
<?php

namespace OneLearningCommunity\LaravelModelExplorer\Http\Controllers;

use Illuminate\Contracts\View\View;

class ModelExplorerController
{
    // ...
}
```

**Path Aliases:**
- No path aliases detected in configuration
- Uses fully qualified namespaces throughout

## Error Handling

**Patterns:**
- Uses Laravel's `abort()` helper for HTTP errors: `abort(404)`, `abort(403)`
- No try-catch blocks in application code; relies on framework exception handling
- Security: Path traversal prevention using `realpath()` and `str_starts_with()` validation

Example from `AssetController`:
```php
if (! $assetPath || ! str_starts_with($assetPath, $publicPath)) {
    abort(404);
}
```

## Logging

**Framework:** Not detected in source code

**Patterns:**
- No explicit logging calls found in source code
- Relies on Laravel's default logging infrastructure

## Comments

**When to Comment:**
- Configuration files have block comments explaining each setting
- Complex security/validation logic is explained inline

**PHPDoc/TSDoc:**
- PHPDoc used for type hints on complex structures
- Array shape types documented: `/** @var string[] */` for typed arrays
- Facade classes use `@see` docblock referencing implementation: `/** @see LaravelModelExplorer */`

Example from `AssetController`:
```php
/** @var string[] */
private const ALLOWED_EXTENSIONS = ['js', 'css', 'woff', 'woff2', 'ttf', 'svg', 'png', 'ico', 'map'];
```

## Function Design

**Size:**
- Small, focused functions: `__invoke()` methods have single responsibility
- Service provider methods (`configurePackage()`, `packageBooted()`) follow Laravel conventions for class size

**Parameters:**
- Optional parameters use nullable types: `public function __invoke(string $path): BinaryFileResponse`
- No required parameters in closures that handle nullable users: `function ($user = null): bool`

**Return Values:**
- Explicit return types always declared: `: View`, `: BinaryFileResponse`, `: Response`, `: void`, `: bool`, `: string`
- Controllers return view/response objects directly
- No implicit null returns

## Module Design

**Exports:**
- Service providers export via `configurePackage()` method using Spatie's package tools
- Facades provide static access: `ModelExplorer::class` resolves to `LaravelModelExplorer`
- Controllers use `__invoke()` for single-action pattern

**Barrel Files:**
- Not used in this package
- Direct imports from specific classes preferred

## Configuration Pattern

**Convention:**
- Configuration arrays use simple key-value pattern: `'enabled'`, `'path'`, `'middleware'`, `'model_paths'`
- Environment variable defaults using `env()` in config only: `env('MODEL_EXPLORER_ENABLED', true)`
- No `env()` calls in application code — only in `config/` files

Example:
```php
'enabled' => env('MODEL_EXPLORER_ENABLED', true),
'path' => env('MODEL_EXPLORER_PATH', '_model-explorer'),
```

## Constructor Pattern

**Convention:**
- Service providers use dependency injection through constructor if needed
- Controllers use no constructor parameters
- Gates defined in service provider using closure: `Gate::define('viewModelExplorer', function ($user = null): bool { ... })`

---

*Convention analysis: 2026-03-20*
