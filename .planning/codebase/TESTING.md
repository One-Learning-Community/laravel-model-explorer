# Testing Patterns

**Analysis Date:** 2026-03-20

## Test Framework

**Runner:**
- Pest PHP v4.0
- Config: No explicit `pest.xml` — configuration inherited from `composer.json` scripts
- Built on PHPUnit v11 foundation

**Assertion Library:**
- Pest's fluent assertion syntax: `expect()->toBeTrue()`, `->assertOk()`, `->assertForbidden()`
- HTTP testing helpers from Pest Laravel plugin: `$this->get()`, `->assertHeader()`

**Run Commands:**
```bash
vendor/bin/pest              # Run all tests
vendor/bin/pest --coverage   # Coverage report
composer test                # Alias for vendor/bin/pest (from composer.json)
composer test-coverage       # Alias for vendor/bin/pest --coverage
```

## Test File Organization

**Location:**
- Tests are co-located in `tests/` directory
- Subdirectories: `tests/Feature/` for feature tests, root for example/unit tests
- Test classes not separated by unit/feature folder structure

**Naming:**
- Feature tests: `RouteAuthorizationTest.php`
- Example tests: `ExampleTest.php`
- Architecture tests: `ArchTest.php`
- All files end in `.php` extension

**Structure:**
```
tests/
├── ExampleTest.php          # Basic example test
├── Feature/
│   └── RouteAuthorizationTest.php    # HTTP routing and authorization
├── ArchTest.php             # Architecture and static analysis rules
├── TestCase.php             # Base test class extending Orchestra\Testbench
└── Pest.php                 # Test setup configuration file
```

## Test Structure

**Suite Organization:**
```php
// Pest uses closure-based tests (not classes)
it('allows access in a local environment', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->get('/_model-explorer')
        ->assertOk();
});

// Uses shared TestCase setup via Pest.php
```

**Patterns:**

**Setup:**
- Uses `TestCase` base class from `OneLearningCommunity\LaravelModelExplorer\Tests\TestCase`
- `Pest.php` applies TestCase globally: `uses(TestCase::class)->in(__DIR__);`
- Orchestra Testbench for package testing with `extends Orchestra` base

**Teardown:**
- No explicit teardown patterns detected
- Laravel Pest plugin handles automatic cleanup

**Assertion:**
- HTTP assertions: `->assertOk()`, `->assertForbidden()`, `->assertNotFound()`
- Header assertions: `->assertHeader('Content-Type', 'application/javascript')`
- Fluent style: `->get()->assertOk()->assertHeader(...)`

## Test Structure Example

```php
it('allows access in a local environment', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->get('/_model-explorer')
        ->assertOk();
});

it('denies access in a non-local environment without a gate override', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->get('/_model-explorer')
        ->assertForbidden();
});
```

## Mocking

**Framework:**
- Laravel's built-in mocking for gates and environment detection
- No explicit mock library dependency in composer.json

**Patterns:**
```php
// Environment mocking
app()->detectEnvironment(fn () => 'local');
app()->detectEnvironment(fn () => 'production');

// Gate mocking
Gate::define('viewModelExplorer', fn ($user = null) => true);

// Configuration mocking
config()->set('model-explorer.enabled', false);
config()->set('database.default', 'testing');
config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
```

**What to Mock:**
- Environment detection for testing environment-specific gates
- Gate definitions for testing authorization override scenarios
- Configuration values for feature toggle testing

**What NOT to Mock:**
- HTTP requests — use actual test HTTP client
- View rendering — assert on response content directly
- File system for security testing — use real path resolution validation

## Fixtures and Factories

**Test Data:**
```php
// Factory configuration in TestCase setup
Factory::guessFactoryNamesUsing(
    fn (string $modelName) => 'OneLearningCommunity\\LaravelModelExplorer\\Database\\Factories\\'.class_basename($modelName).'Factory'
);
```

**Location:**
- Factories: `database/factories/ModelFactory.php` (contains template, currently unused)
- No actual factory implementations in use yet

## Coverage

**Requirements:**
- No explicit coverage requirement configured
- Coverage available via `composer test-coverage`

**View Coverage:**
```bash
composer test-coverage
# Generates coverage report in build/ directory
```

## Test Types

**Feature Tests:**
- Location: `tests/Feature/RouteAuthorizationTest.php`
- Scope: HTTP routing, middleware, authorization gates
- Approach: Makes actual HTTP requests using Pest's `$this->get()` helper
- Tests routes with different environments (local, production)
- Tests asset serving and security (path traversal, file extension validation)

Example feature test:
```php
it('allows access in a local environment', function () {
    app()->detectEnvironment(fn () => 'local');
    $this->get('/_model-explorer')->assertOk();
});

it('serves assets without requiring authorization', function () {
    app()->detectEnvironment(fn () => 'production');
    $this->get('/_model-explorer/assets/app.js')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/javascript');
});
```

**Unit Tests:**
- Not explicitly used in this package
- Could be added for controller logic or middleware

**Architecture Tests:**
- Location: `tests/ArchTest.php`
- Framework: Pest's architecture testing via `arch()` function
- Scope: Code quality rules and static analysis

Example arch test:
```php
arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->each->not->toBeUsed();
```

**Example Tests:**
- Location: `tests/ExampleTest.php`
- Purpose: Basic scaffolding test to verify test infrastructure works
- Simple assertion: `expect(true)->toBeTrue()`

## Common Patterns

**HTTP Testing:**
```php
$this->get('/_model-explorer')->assertOk();
$this->get('/_model-explorer/assets/app.js')->assertHeader('Content-Type', 'application/javascript');
$this->get('/_model-explorer/assets/../composer.json')->assertNotFound();
```

**Environment Configuration Testing:**
```php
app()->detectEnvironment(fn () => 'local');
// Test passes for local
app()->detectEnvironment(fn () => 'production');
// Test checks production behavior
```

**Authorization Testing:**
```php
// Default gate (denies in production)
$this->get('/_model-explorer')->assertForbidden();

// Override gate
Gate::define('viewModelExplorer', fn ($user = null) => true);
$this->get('/_model-explorer')->assertOk();
```

**Configuration Testing:**
```php
config()->set('model-explorer.enabled', false);
$this->get('/_model-explorer')->assertNotFound();

config()->set('model-explorer.path', '/_custom-path');
$this->get('/_custom-path')->assertOk();
```

## Test Coverage Areas

**Route Authorization:**
- Local environment access
- Production environment denial
- Gate override behavior
- Feature flag (enabled/disabled)
- Custom path configuration

**Asset Serving:**
- JavaScript content type
- Allowed file extensions (js, css, woff, woff2, ttf, svg, png, ico, map)
- Path traversal prevention (`../` attempts)
- Disallowed extensions (composer.json, etc.)

**Static Analysis:**
- No debugging functions used
- Code quality rules

## Test Infrastructure

**TestCase Base Class (`tests/TestCase.php`):**
- Extends `Orchestra\Testbench\TestCase` for package testing
- Registers `LaravelModelExplorerServiceProvider`
- Sets test database configuration
- Sets up factory name resolution
- Generates random app key for testing

**Pest Setup (`tests/Pest.php`):**
- Applies `TestCase` globally to all tests in directory
- Single line: `uses(TestCase::class)->in(__DIR__);`

---

*Testing analysis: 2026-03-20*
