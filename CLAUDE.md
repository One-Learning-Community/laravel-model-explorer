This is a Laravel Package designed to provide an interface for developers to explore their Laravel Models.

This package offers a user-friendly interface that allows developers to easily navigate and understand the structure of
their Laravel Models. It provides a comprehensive overview of model relationships, attributes, and methods, making it
easier to debug and maintain complex applications.

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

- **Model slugs in URLs**: class names are base64url-encoded: `btoa(className).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '')`. Decoded in PHP with `base64_decode(strtr($model, '-_', '+/'))`.
- **Controller pattern**: all API controllers follow `ModelsController` — constructor-inject services, decode slug, guard with 404, wrap DB work in `withinSafeRead()` (RecordsController) or try/catch.
- **`withinSafeRead()`** (RecordsController only): wraps DB reads in `Model::withoutEvents()` + a rolled-back transaction to prevent accidental writes from accessors or observers.
- **Accessor values are lazy**: `RecordsController::show()` returns only raw `getAttributes()` values. Accessor/virtual attributes are resolved on demand via the `/attributes` and `/attributes/{name}` endpoints.
- **IDE linter warnings** on `$className::query()` / `$className::find()` are false positives — the linter doesn't know `$className` is a model class string. Ignore them.
- **DaisyUI night theme** + Tailwind v4. Use existing badge colours for relation types (`badge-info`, `badge-primary`, `badge-secondary`, `badge-accent`, `badge-warning`, `badge-error`).
- **Vue Router reuse**: `ModelRecord.vue` handles same-component navigation via a `watch` on `{ model, field, value }` — `onMounted` alone is not sufficient.
