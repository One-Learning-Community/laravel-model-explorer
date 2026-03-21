# External Integrations

**Analysis Date:** 2026-03-20

## APIs & External Services

**GitHub API (Optional):**
- Used during package setup/configuration via `configure.php` script
- Endpoint: `api.github.com/orgs/{org}` and `api.github.com` (public endpoints)
- Authentication: None (public API, no tokens required)
- Purpose: Auto-detect GitHub organization information during package initialization
- Not used at runtime in production

**No Runtime External APIs:**
- Package does not integrate with external APIs at runtime
- All functionality is self-contained within Laravel host application

## Data Storage

**Databases:**
- None required - Package is read-only inspection tool
- No migrations created
- Does not persist any data
- Uses host application's Eloquent models for introspection only

**File Storage:**
- Local filesystem only
- Static assets stored in `public/` directory
- Assets compiled to:
  - `public/app.js` - Vue 3 SPA bundle
  - `public/app.css` - Compiled styles
  - Additional asset files (fonts, maps) as needed by Vite build

**Caching:**
- HTTP caching via Asset controller
  - Cache-Control header: `public, max-age=31536000, immutable` (1 year)
  - Applied to all served assets (`.js`, `.css`, font files, etc.)
- No application-level caching layer configured

## Authentication & Identity

**Auth Provider:**
- None external - Uses Laravel's built-in gate system
- Implementation: `Illuminate\Support\Facades\Gate`
  - Gate name: `viewModelExplorer`
  - Default implementation: Local environment check only
  - Middleware: `OneLearningCommunity\LaravelModelExplorer\Http\Middleware\Authorize`

**Authorization:**
- Gate-based access control in `src/Http/Middleware/Authorize.php`
- Aborts with 403 Forbidden if gate check fails
- Aborts with 404 if package is disabled via config

## Monitoring & Observability

**Error Tracking:**
- None built-in
- Relies on host application's error handling
- Can optionally integrate with Laravel Ray via `spatie/laravel-ray` package (dev dependency)

**Logs:**
- No package-specific logging
- Uses Laravel's default logging when enabled on host application
- Errors propagate to host application's error handler

## CI/CD & Deployment

**Hosting:**
- Package runs as integrated Laravel package
- No standalone hosting required
- Deployed as part of host Laravel application

**CI Pipeline:**
- GitHub Actions workflows available:
  - PHP Code Style (Laravel Pint) - If enabled
  - PHPStan static analysis - If enabled
  - Dependabot auto-merge - If enabled
  - Update changelog workflow - If enabled
- Tests run via Pest: `composer test`
- Coverage available via: `composer test-coverage`

## Environment Configuration

**Required env vars:**
- None required - Package works with defaults
- Optional configuration variables (via `config/model-explorer.php`):
  - `MODEL_EXPLORER_ENABLED` - Boolean, default true
  - `MODEL_EXPLORER_PATH` - String, default `_model-explorer`

**Secrets location:**
- Package contains no secrets
- No API keys, credentials, or sensitive configuration
- All configuration is public/non-sensitive

## Webhooks & Callbacks

**Incoming:**
- None - Package is read-only developer tool

**Outgoing:**
- None - Package does not initiate external requests at runtime

## Host Application Integration

**Route Registration:**
- Web routes registered via service provider in `routes/web.php`
- Routes prefixed with configurable path (default: `_model-explorer`)
- Two route groups:
  1. Asset routes (`/{path?}`) - Public, no auth (static assets)
  2. SPA routes (`/{any?}`) - Protected by Authorize middleware

**Configuration Publishing:**
- Package config published to host app's `config/model-explorer.php`
- Published on install via Spatie package tools

**View Publishing:**
- Package views published to host app's `resources/views/vendor/model-explorer/`
- SPA shell template: `resources/views/app.blade.php`

**Model Discovery:**
- Scans configurable model paths (default: `app/Models`)
- Configuration: `config('model-explorer.model_paths')` array
- Can be customized to include other directories in host application

---

*Integration audit: 2026-03-20*
