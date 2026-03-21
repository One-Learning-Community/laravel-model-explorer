# Architecture

**Analysis Date:** 2026-03-20

## Pattern Overview

**Overall:** Laravel Package with Single-Page Application (SPA) Frontend

**Key Characteristics:**
- Modular Laravel package architecture leveraging Spatie Package Tools
- Vue 3 + Vite SPA frontend compiled to static assets
- Two-tier routing: asset serving (public) and SPA content (protected)
- Gate-based authorization with environment-aware defaults
- Asset-safe static file serving with path traversal protection

## Layers

**Service Provider Layer:**
- Purpose: Package initialization and configuration registration
- Location: `src/LaravelModelExplorerServiceProvider.php`
- Contains: Configuration publishing, gate registration, route registration
- Depends on: Illuminate contracts, Spatie Package Tools
- Used by: Laravel's service container during bootstrap

**HTTP Layer:**
- Purpose: Request handling for both UI and assets
- Location: `src/Http/Controllers/`, `src/Http/Middleware/`
- Contains: Controllers for SPA rendering and asset delivery, authorization middleware
- Depends on: Illuminate routing, gates, views, file response utilities
- Used by: Routes defined in `routes/web.php`

**Frontend Layer:**
- Purpose: User interface and client-side routing
- Location: `resources/js/`, `resources/views/`, `resources/css/`
- Contains: Vue 3 components, blade template shell, CSS styles
- Depends on: Vue 3, Vite build system
- Used by: Browser rendering of SPA

**Configuration Layer:**
- Purpose: Package customization and behavior control
- Location: `config/model-explorer.php`
- Contains: Enable/disable flag, URL path prefix, middleware list, model scan paths
- Depends on: Laravel env() function and config() helper
- Used by: Controllers, routes, and authorization middleware

## Data Flow

**Initial Page Load (HTTP GET):**

1. Client requests `/_model-explorer/` (or configured path)
2. Laravel router matches `routes/web.php` - SPA catch-all route
3. Authorize middleware validates: enabled flag and gate permission
4. ModelExplorerController returns `view('model-explorer::app')`
5. Blade template (`resources/views/app.blade.php`) renders HTML shell with asset URLs
6. Browser loads Vue app.js and app.css from `/_model-explorer/assets/`
7. AssetController serves compiled assets with immutable cache headers

**Asset Serving (HTTP GET /_model-explorer/assets/*):**

1. Client requests asset (app.js, app.css, fonts, etc.)
2. Asset route matches before SPA catch-all (registered first)
3. AssetController validates path security (no traversal) and extension (whitelist)
4. Returns file with immutable cache headers (max-age=31536000)
5. Asset requests skip authorization middleware for performance

**Authorization Flow:**

1. Authorize middleware checks `config('model-explorer.enabled', true)`
   - If disabled: abort(404)
2. Middleware checks `Gate::check('viewModelExplorer')`
   - If denied: abort(403)
3. Default gate: allows only in local environment
4. Can be overridden in AuthServiceProvider for custom rules

## Key Abstractions

**Package Configuration:**
- Purpose: Externalize package behavior without code changes
- Examples: `config/model-explorer.php`
- Pattern: Config values accessed via `config('model-explorer.key')` throughout package

**Route Groups with Middleware:**
- Purpose: Apply consistent middleware and namespacing to related routes
- Examples: Asset route group, SPA route group
- Pattern: Defined in `routes/web.php` with prefixes, middleware, and name groups

**Gate-Based Authorization:**
- Purpose: Flexible, application-wide authorization without hardcoding
- Examples: `Gate::check('viewModelExplorer')` in Authorize middleware
- Pattern: Default gate in ServiceProvider, override via AuthServiceProvider

**Asset Static Serving:**
- Purpose: Efficiently serve compiled frontend assets with security
- Examples: AssetController path validation and extension whitelist
- Pattern: Allowlist-based validation with realpath() for security

**Vue SPA Shell:**
- Purpose: Single HTML entry point with client-side routing
- Examples: `resources/views/app.blade.php`, `resources/js/app.js`
- Pattern: Blade template renders minimal HTML, Vue app mounts on #app div

## Entry Points

**Package Registration:**
- Location: `src/LaravelModelExplorerServiceProvider.php`
- Triggers: Laravel service container discovery
- Responsibilities: Publish config/routes/views, register gate, boot-time setup

**Web Route Entry:**
- Location: `routes/web.php`
- Triggers: HTTP requests to configured path prefix
- Responsibilities: Route requests to controller or asset handler, apply middleware

**Frontend Entry:**
- Location: `resources/js/app.js`
- Triggers: Browser loads HTML and executes script
- Responsibilities: Create Vue app instance, mount to #app element

**Asset Entry:**
- Location: `src/Http/Controllers/AssetController.php`
- Triggers: HTTP GET to `/_model-explorer/assets/*`
- Responsibilities: Validate and serve compiled frontend assets

## Error Handling

**Strategy:** HTTP status codes with minimal response body (404/403 aborts)

**Patterns:**
- Path traversal attempts: abort(404) via AssetController realpath validation
- Disallowed file extensions: abort(404) via AssetController allowlist check
- Package disabled: abort(404) in Authorize middleware
- Unauthorized access: abort(403) in Authorize middleware
- Non-existent assets: abort(404) via AssetController file existence check

## Cross-Cutting Concerns

**Logging:** No explicit logging layer; relies on Laravel's default request logging

**Validation:** Path and extension validation in AssetController for security

**Authentication:** No authentication required; uses gate-based authorization for access control

---

*Architecture analysis: 2026-03-20*
