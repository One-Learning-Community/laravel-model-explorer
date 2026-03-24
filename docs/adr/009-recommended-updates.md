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

#### Config: exclude specific model classes
The package currently supports only path-based exclusions via `model_paths`. Consumers frequently want to suppress noise from third-party models shipped by packages such as Telescope, Passport, or Horizon. An `excluded_models` config key accepting an array of fully-qualified class names (or wildcard namespace prefixes) would cover this without requiring consumers to manipulate `model_paths`.

#### ~~FK column identification~~ ✓ Done — v0.2.0 (partial)
FK columns are now identified in the Columns table with an `FK` badge derived from `BelongsTo`/`MorphTo` relation metadata. The tooltip shows the related class name.

#### FK column links in Columns table
The next step: render FK column names as links to the related model's detail page when the related class exists in the discovered set.

### Lower Priority

#### ~~Keyboard shortcut for search~~ ✓ Done — v0.2.0
`/` and `Cmd+K`/`Ctrl+K` focus the navbar search from anywhere in the app. Search also now matches table names in addition to class names.

#### ~~Model policy display~~ ✓ Done — v0.2.0
When a policy is registered via `Gate::policy()`, a `policy: PolicyName` badge appears in the model detail header with the fully-qualified class name as a tooltip. Uses `Gate::policies()` — no instantiation.

#### ~~Package version in the UI~~ ✓ Done — v0.2.0
`Composer\InstalledVersions::getPrettyVersion()` embedded in the Blade shell as `window.modelExplorerVersion`; rendered as a subtle footer across all pages.

## Consequences

Items still outstanding: per-class model exclusions (`excluded_models` config) and FK column links (navigation from a FK badge to the related model's detail page). Both are independently implementable.
