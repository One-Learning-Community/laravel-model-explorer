# Changelog

All notable changes to `laravel-model-explorer` will be documented in this file.

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
