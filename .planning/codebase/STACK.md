# Technology Stack

**Analysis Date:** 2026-03-20

## Languages

**Primary:**
- PHP 8.4+ - Core package implementation, service providers, controllers, middleware
- JavaScript (ES2020+) - Frontend build tooling configuration
- Vue 3 - Single-page application frontend components

**Secondary:**
- Blade - Server-side view template engine (minimal usage, only for SPA shell)
- CSS 3 - Styling (basic reset and layout)

## Runtime

**Environment:**
- Laravel 11-13 compatible (requires Illuminate ^11.0||^12.0||^13.0)
- PHP 8.4+ (enforced via Composer constraint)

**Package Manager:**
- Composer for PHP dependencies (primary)
- NPM/Node.js for JavaScript build tooling
- Lockfiles: `composer.lock`, `package-lock.json` (both present)

## Frameworks

**Core:**
- Laravel Framework (v11-13) - Web framework for PHP
  - Illuminate/Contracts (v11-13) - Service container and facades
  - Illuminate/Support - Utility classes and helpers
- Vue 3 (v3.5+) - Progressive JavaScript framework for SPA

**Build/Dev:**
- Vite 6.0+ - Modern frontend build tool and dev server
- @vitejs/plugin-vue 5.2+ - Vue 3 plugin for Vite

**Package Tools:**
- spatie/laravel-package-tools (v1.16+) - Package scaffolding and configuration utilities

## Key Dependencies

**Critical:**
- spatie/laravel-package-tools (v1.16+) - Provides PackageServiceProvider base class and package configuration framework used in `LaravelModelExplorerServiceProvider`
- illuminate/contracts (v11.0||^12.0||^13.0) - Type hints and contracts for Laravel facades (Gate, View, etc.)

**Testing & Quality:**
- pestphp/pest (v4.0) - Testing framework (primary test runner)
- pestphp/pest-plugin-laravel (v4.0) - Laravel integration for Pest
- pestphp/pest-plugin-arch (v4.0) - Architecture testing
- orchestra/testbench (v10.0.0||^9.0.0) - Laravel package testing utilities
- larastan/larastan (v3.0) - Static analysis for Laravel code
- phpstan/phpstan-phpunit (v2.0) - PHPUnit rules for PHPStan
- phpstan/phpstan-deprecation-rules (v2.0) - Deprecation detection
- phpstan/extension-installer (v1.4) - PHPStan extension management
- laravel/pint (v1.14+) - Laravel code formatter/fixer
- nunomaduro/collision (v8.8+) - Error/exception rendering
- spatie/laravel-ray (v1.35+) - Debugging tool integration

## Configuration

**Environment:**
- Environment variables via Laravel's `.env` file (if used)
- Configuration file: `config/model-explorer.php`
  - `MODEL_EXPLORER_ENABLED` - Toggle package availability (default: true)
  - `MODEL_EXPLORER_PATH` - URL prefix for explorer routes (default: `_model-explorer`)

**Build:**
- `vite.config.js` - Vite configuration
  - Input: `resources/js/app.js`
  - Output: `public/` directory
  - CSS and JavaScript are output with content hashing for immutable caching

**Code Quality:**
- `phpstan.neon.dist` - Static analysis level 5, covers src/config/database directories
- `pint.json` - Code formatting (if present)
- `.editorconfig` - Editor configuration (UTF-8, LF line endings, 4-space indent)

## Package Bootstrap

**Service Provider:**
- `LaravelModelExplorerServiceProvider` extends `Spatie\LaravelPackageTools\PackageServiceProvider`
- Configures package via `configurePackage()` method:
  - Package name: `model-explorer`
  - Publishes config file
  - Publishes views
  - Registers web routes
- Auto-registers gate via `packageBooted()` hook:
  - Gate key: `viewModelExplorer`
  - Default access: local environment only

**Package Discovery:**
- Uses Laravel's automatic package discovery
- Registered in composer.json `extra.laravel.providers`
- Facade alias: `ModelExplorer` (points to `OneLearningCommunity\LaravelModelExplorer\Facades\ModelExplorer`)

## Platform Requirements

**Development:**
- Node.js with npm (for Vite dev server and asset building)
- PHP 8.4+ CLI
- Composer

**Production:**
- PHP 8.4+ runtime
- No database required (read-only inspection of host application's models)
- Integrated into Laravel application served via standard web server

---

*Stack analysis: 2026-03-20*
