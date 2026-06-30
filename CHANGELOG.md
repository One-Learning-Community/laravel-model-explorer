# Changelog

All notable changes to `laravel-model-explorer` will be documented in this file.

## Unreleased

## v0.3.1 - 2026-06-30

### Bug fixes

- **Relations no longer silently dropped when a relation method throws** — `RelationFinder` discovered relations by invoking each method on a blank model and discarding any that threw (e.g. a `whereHas`/constraint closure that dereferences the query builder, or a relation built against unset attributes). This made the reported relation set depend on runtime/DB state rather than the model's structure. A relation that has been statically identified is now reported even when invocation fails: the type is recovered from the declared return type (or the relation primitive in the source for untyped methods) and the related model is parsed from the `X::class` argument.
- **Cached MCP results now invalidate on source changes** — the MCP `inspect-model`/`find-model` cache key now includes the model file's mtime, and `list-models`/`relationship-graph` (and the web list/graph endpoints) include a fingerprint over all model-path files. Previously these keys were keyed only by class name, so enabling `MODEL_EXPLORER_MCP_CACHE` (or `MODEL_EXPLORER_CACHE`) served stale metadata after a model was edited until the cache was manually cleared. Introduces a shared `SourceFingerprint` service (the per-model detail page already had this behaviour).
- **Accurate inspection from the long-lived MCP server without a restart** — the `mcp:start` server is a persistent PHP process, and PHP cannot reload a class it has already loaded, so editing a model's structure after the server booted previously yielded stale reflection until reconnect. `inspect-model`/`find-model` now route through a `FreshModelInspector`: a class is inspected in-process while its file is unchanged, but once the file changes after the process loaded it, the inspection runs in a fresh `model-explorer:inspect` subprocess (a new, hidden worker command) that sees the current source. The result is returned as a serialized `ModelData` payload. No manual server reset required; the subprocess cost is paid only for a model edited within the server's lifetime.

**Full Changelog**: https://github.com/One-Learning-Community/laravel-model-explorer/compare/v0.3.0...v0.3.1

## v0.3.0 - 2026-06-30

### Added

- **AI Model Introspection (MCP)** — a local [`laravel/mcp`](https://laravel.com/docs/mcp) server (`model-explorer`) that lets AI coding agents introspect your Eloquent models without scanning source. Five tools: `list-models`, `inspect-model` (opt-in sections via `include`), `relationship-graph`, `find-model` (filter by `trait`/`extends`/`relatesTo`/`hasColumn`), and `model-source` (trait-correct snippets). Reads live by default; opt-in caching via `MODEL_EXPLORER_MCP_CACHE`. If you use [Laravel Boost](https://laravel.com/docs/boost), `boost:install` advertises the tools to your agent automatically. See ADR-011 and the [MCP guide](https://one-learning-community.github.io/laravel-model-explorer/guide/mcp).
- **`excluded_models` config** — hide specific model classes (even inside a scanned path) by FQCN or `*` wildcard; useful for suppressing third-party models from Telescope, Passport, Horizon, etc.
- **FK column links** — foreign-key badges in the Columns table now link to the related model's detail page when that model is in the discovered set.
- **Optional caching** — set `MODEL_EXPLORER_CACHE=true` to cache model discovery and inspection (helpful for apps with many models). Detail pages auto-refresh when the model file changes; clear everything with `php artisan model-explorer:clear`.
- **Configurable page size** — `per_page` config (`MODEL_EXPLORER_PER_PAGE`) controls how many related records are shown per page and caps collection-returning accessor values (previously hardcoded to 15).

### Bug fixes

- **Multi-connection safe reads** — `withinSafeRead()` now opens its rolled-back transaction on the inspected model's own database connection instead of always the default one, so accessor/observer writes are rolled back for models on a non-default connection.
- **Resilient model list** — a model that throws on instantiation is now skipped in the model list instead of breaking the entire endpoint.

### Other changes

- Docs corrected to state PHP 8.3+ (matching the `composer.json` constraint and continued Laravel 11 support).
- Published a VitePress documentation site with an MCP guide, and trimmed the Composer dist archive (`.gitattributes`) so it no longer ships the docs site or frontend build toolchain.

**Full Changelog**: https://github.com/One-Learning-Community/laravel-model-explorer/compare/v0.2.1...v0.3.0

## v0.2.1 Release - 2026-03-25

Resolve errors with models using some third party packages for advanced relations.

**Full Changelog**: https://github.com/One-Learning-Community/laravel-model-explorer/compare/v0.2.0...v0.2.1

## Initial Published Release - 2026-03-24

**Full Changelog**: https://github.com/One-Learning-Community/laravel-model-explorer/commits/v0.2.0

## v0.2.0 - 2026-03-24

### What's new

- **Dark / light mode toggle** — theme auto-detected from `prefers-color-scheme` on first visit; toggle button in the navbar persists the choice to `localStorage`; no flash on load
- **FK column identification** — foreign key columns are now labelled with an `FK` badge in the Columns table, derived from `BelongsTo` and `MorphTo` relation metadata; the related class is shown as a tooltip
- **Composite primary key hint** — models with a composite primary key now show a warning in the record browser explaining that relation drilling may not work correctly
- **Policy badge** — when a policy is registered for a model via `Gate::policy()`, the model detail header shows a `policy: PolicyName` badge with the fully-qualified class name on hover
- **Graph reset view** — a "Reset view" button restores the initial centred pan and zoom on the relationship graph
- **Search enhancements** — press `/` or `Cmd+K` / `Ctrl+K` to focus the navbar search from anywhere; search now also matches against table names; keyboard-selected item is visually distinct
- **Package version in footer** — the installed package version is shown in a subtle footer on all pages
- **`key_name` in API response** — `GET /api/models/{model}` now includes `key_name` (string or array for composite keys)

### Bug fixes

- Scroll-spy listeners no longer accumulate when navigating between models
- Relations tab is omitted from the section nav when a model has no relations
- `AssetController` now aborts immediately if the `public/` directory cannot be resolved, preventing a vacuous path-traversal check
- `ModelDiscovery::resolveBaseNamespace()` dead method removed

### Other changes

- Compiled assets (`public/`) are no longer committed; they are generated during the build/publish process
- CI workflow now runs `npm ci && npm run build` before the test suite, and triggers on frontend file changes
- Trait badges use `badge-neutral` (solid fill) instead of `badge-ghost` for visibility in both themes
- README expanded with requirements, `MODEL_EXPLORER_ENABLED` env var documentation, and a multi-directory `model_paths` example
- ADR-009 added: recommended updates before publishing

## v0.1.0 - 2026-03-22

### What's new

- Model discovery — scans configured paths to find all Eloquent models
- Model detail view — DB columns, casts, fillable/hidden/guarded, relations with type badges and foreign keys, scopes with source snippets, traits, and accessor snippets
- Record lookup — find any record by primary key or unique field; browse raw attributes, lazy-loaded accessor values, and expandable relations with drill-down navigation
- Breadcrumb trail — tracks drill-down navigation history through related records
- Attribute filter — search attributes by name or formatted value
- Relationship graph — interactive force-directed SVG graph of all model relationships
- Authorization via `viewModelExplorer` gate (defaults to `local` environment only)
- All DB reads wrapped in rolled-back transactions with `Model::withoutEvents()` to prevent accidental writes
