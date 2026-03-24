# Laravel Model Explorer — Developer Context

A Laravel package that gives developers a browser-based interface to explore their Eloquent models. Zero setup beyond Composer install — no `vendor:publish`, no frontend tooling required by the host app.

## What This Package Does

- **Model list** (`/`) — searchable grid of all discovered models with short name, table, and quick stats
- **Model detail** (`/models/{slug}`) — full structure: DB columns with types/casts, relations (type pills, icons, source attribution), local scopes (parameter signatures and source snippets), virtual/accessor attributes, and traits
- **Record browser** (`/models/{slug}/record`) — look up any record by primary key or unique field; drill into relations and accessor values; breadcrumb trail for navigation chains
- **Relationship graph** (`/graph`) — force-directed SVG graph of all model relationships; pan/drag/zoom; click a node to navigate to its detail view

## Tech Stack

| Layer | Technology |
|---|---|
| PHP | 8.4+, Laravel 11–13 |
| Frontend | Vue 3, Vite 6, Vue Router |
| UI | DaisyUI v5 + Tailwind v4, fixed **night** theme |
| Icons | lucide-vue-next |
| Syntax highlighting | vite-plugin-prismjs-plus (language: php; plugin: line-numbers; theme: tomorrow) |
| Key runtime dep | spatie/laravel-model-info v2 (column introspection) |

## Documentation

Architectural decisions are recorded in `docs/adr/`:

| ADR | Decision |
|---|---|
| [001](docs/adr/001-route-served-assets.md) | Assets served via PHP route — no `vendor:publish` |
| [002](docs/adr/002-gate-based-authorization.md) | Gate `viewModelExplorer` with local-env default + `enabled` kill switch |
| [003](docs/adr/003-self-contained-vue3-spa.md) | Self-contained Vue 3 + Vite SPA; compiled assets committed to repo |
| [004](docs/adr/004-spatie-model-info-for-column-introspection.md) | `spatie/laravel-model-info` v2 for DB column data |
| [005](docs/adr/005-reflection-source-scanning-for-relation-discovery.md) | Two-pass relation detection: typed return hints + regex source scanning |
| [006](docs/adr/006-within-safe-read-for-live-record-browsing.md) | `withinSafeRead()` — `withoutEvents()` + rolled-back transaction for safe DB reads |
| [007](docs/adr/007-lazy-accessor-resolution.md) | Accessor values resolved lazily via separate API endpoints |
| [008](docs/adr/008-base64url-model-slugs.md) | Model class names base64url-encoded as URL slugs |

## Testing

Tests use **Pest** (not PHPUnit classes directly) — follow the existing `it('...', function () {})` style.

Run all tests:
```
./vendor/bin/pest
```

Run a specific file:
```
./vendor/bin/pest tests/Feature/Api/RecordsApiTest.php
```

Filter by name:
```
./vendor/bin/pest --filter "resolves a to-one relation"
```

**Known pre-existing failure:** The 403/production-environment tests fail with a Mockery error (`askQuestion` not expected) across all API test files. This is a pre-existing issue unrelated to any changes — do not spend time debugging it.

## Package Structure

```
src/
  Http/Controllers/
    ModelExplorerController.php     # Serves the SPA Blade shell
    AssetController.php             # Serves compiled JS/CSS (no auth)
    Api/
      ModelsController.php          # GET /api/models, /api/models/{model}
      GraphController.php           # GET /api/graph
      RecordsController.php         # GET /api/models/{model}/record (and sub-routes)
  Services/
    ModelDiscovery.php              # Finds all Eloquent model classes in configured paths
    ModelInspector.php              # Inspects a model: attributes, relations, scopes, traits
    RelationFinder.php              # Discovers relation methods (typed + source-scanned)
    SourceExtractor.php             # Extracts source code snippets from ReflectionMethod
  Data/
    ModelData.php                   # DTO: full model structure
    RelationData.php                # DTO: relation metadata
    ScopeData.php                   # DTO: scope metadata
  Middleware/
    Authorize.php                   # Gate check (viewModelExplorer) + enabled check
  LaravelModelExplorerServiceProvider.php

routes/web.php                      # All routes registered under config('model-explorer.path')
config/model-explorer.php           # enabled, path, middleware, model_paths, excluded_trait_prefixes
resources/
  js/
    app.js                          # Vue app entry
    router.js                       # Vue Router (ModelList, ModelDetail, ModelRecord, ModelGraph)
    pages/
      ModelList.vue                 # Grid of all models with search
      ModelDetail.vue               # Full model structure: columns, relations, scopes, traits
      ModelRecord.vue               # Record lookup by PK/unique field; drill into relations/accessors
      ModelGraph.vue                # Force-directed SVG graph of model relationships
  css/app.css
  views/app.blade.php               # HTML shell, mounts #app, sets window.modelExplorerBasePath
workbench/
  app/Models/                       # Test models: Post, User, BasePost, ExtendedPost, etc.
  database/migrations/              # Test schema
tests/Feature/
  ModelInspectorTest.php
  ModelDiscoveryTest.php
  Api/
    ModelsApiTest.php
    GraphApiTest.php
    RecordsApiTest.php
```

## Frontend Build

After any change to `resources/js/` or `resources/css/`:
```
npm run build
```

During development you can use `npm run dev` for watch mode, but the package serves compiled assets from `public/` so a build is needed for changes to be picked up when testing via the browser.

## Key Conventions

- **Model slugs in URLs**: class names are base64url-encoded — JS: `btoa(className).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '')` / PHP: `base64_decode(strtr($slug, '-_', '+/'))`. See ADR-008.
- **Controller pattern**: all API controllers follow `ModelsController` — constructor-inject services, decode slug, guard with 404, wrap DB work in `withinSafeRead()` (RecordsController) or try/catch.
- **`withinSafeRead()`** (RecordsController only): wraps DB reads in `Model::withoutEvents()` + a rolled-back transaction to prevent accidental writes from accessors or observers. See ADR-006.
- **Accessor values are lazy**: `RecordsController::show()` returns only raw `getAttributes()` values. Accessor/virtual attributes are resolved on demand via the `/attributes` and `/attributes/{name}` endpoints. See ADR-007.
- **Source attribution**: use `ReflectionClass::getTraits()` walk — NOT `ReflectionMethod::getDeclaringClass()` — to determine whether a method originates from a trait vs. a parent class. `getDeclaringClass()` returns the *using* class when a trait is involved.
- **IDE linter warnings** on `$className::query()` / `$className::find()` are false positives — the linter doesn't know `$className` is a model class string. Ignore them.
- **Relation badge colours** (DaisyUI): `badge-info` = HasOne/HasMany, `badge-secondary` = BelongsTo, `badge-accent` = BelongsToMany, `badge-warning` = MorphTo/MorphOne/MorphMany, `badge-error` = MorphToMany/MorphedByMany.
- **Vue Router reuse**: `ModelRecord.vue` handles same-component navigation via a `watch` on `{ model, field, value }` — `onMounted` alone is not sufficient.
- **Prism.js**: `manual: true` is required in vite-plugin-prismjs-plus config; always call `Prism.highlightElement()` manually after opening a snippet modal so the line-numbers plugin injects DOM nodes correctly.
