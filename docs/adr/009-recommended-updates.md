# ADR-009: Recommended Updates Before Publishing

- **Status:** Accepted (in progress)
- **Date:** 2026-03-24

## Context

A review of the package prior to public release identified a set of improvements that are not blocking correctness but would meaningfully improve usability, discoverability, and compatibility for external consumers. This ADR records them in priority order so they can be tracked and executed as discrete work items.

## Recommended Updates

### High Priority

#### ~~README / installation docs~~ ✓ Done — v0.2.0
README expanded with requirements section, zero-setup callout, `MODEL_EXPLORER_ENABLED` env var documentation, and a multi-directory `model_paths` example.

#### ~~Composite primary key hint in record browser~~ ✓ Done — v0.2.0
`key_name` (string or array) is now included in the `/api/models/{model}` response. `ModelRecord.vue` shows a warning banner when `key_name` is an array, explaining that lookup works but relation drilling may not function correctly.

### Medium Priority

#### ~~Dark / light mode auto-respect~~ ✓ Done — v0.2.0
Hardcoded `data-theme="night"` removed. Theme is now auto-detected from `prefers-color-scheme` on first load via an inline flash-prevention script; a sun/moon toggle in the navbar persists the choice to `localStorage`. Both `light` and `night` DaisyUI themes are compiled in.

#### ~~"Reset view" button on relationship graph~~ ✓ Done — v0.2.0
"Reset view" button added to the graph header. Stores the initial centred pan position on load and restores it along with `zoom = 1`.

#### ~~Config: exclude specific model classes~~ ✓ Done — v0.3.0
An `excluded_models` config key now suppresses noise from third-party models (Telescope, Passport, Horizon, etc.) without manipulating `model_paths`. Each entry is matched against the fully-qualified class name via `Str::is()` and may use `*` as a wildcard (e.g. `Laravel\Telescope\*`); leading backslashes are ignored. Filtering happens in `ModelDiscovery::discoverAll()`.

#### ~~FK column identification~~ ✓ Done — v0.2.0 (partial)
FK columns are now identified in the Columns table with an `FK` badge derived from `BelongsTo`/`MorphTo` relation metadata. The tooltip shows the related class name.

#### ~~FK column links in Columns table~~ ✓ Done — v0.3.0
FK badges in the Columns table now link to the related model's detail page when the related class exists in the discovered set. `ModelDetail.vue` fetches the model list once and passes the set of known classes to `ColumnsTable.vue`; an FK whose related model is not discovered (or is polymorphic) renders as a plain, non-linked badge.

### Lower Priority

#### ~~Keyboard shortcut for search~~ ✓ Done — v0.2.0
`/` and `Cmd+K`/`Ctrl+K` focus the navbar search from anywhere in the app. Search also now matches table names in addition to class names.

#### ~~Model policy display~~ ✓ Done — v0.2.0
When a policy is registered via `Gate::policy()`, a `policy: PolicyName` badge appears in the model detail header with the fully-qualified class name as a tooltip. Uses `Gate::policies()` — no instantiation.

#### ~~Package version in the UI~~ ✓ Done — v0.2.0
`Composer\InstalledVersions::getPrettyVersion()` embedded in the Blade shell as `window.modelExplorerVersion`; rendered as a subtle footer across all pages.

## Consequences

All items recorded in this ADR have now shipped. The remaining v0.2.0 follow-ups — per-class model exclusions (`excluded_models` config) and FK column links — landed in v0.3.0, along with multi-connection safe reads (`withinSafeRead()` now opens its rolled-back transaction on the inspected model's own connection).

## Deferred / Future

#### Record browse mode
The record browser is currently lookup-only: you must already know a primary key or
unique-field value to find a record (`RecordsController::show()` does a single-field
`where(...)->first()`). A paginated "browse latest N records" view would let developers
explore a model's data without knowing a key up front, reusing the existing
`withinSafeRead()` + `paginate()` plumbing and the `per_page` config. Deferred — not yet
scheduled.
