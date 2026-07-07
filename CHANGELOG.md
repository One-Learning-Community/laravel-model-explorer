# Changelog

All notable changes to `laravel-model-explorer` will be documented in this file.

## Unreleased

### Changed

- **`model-source` self-corrects the "member" vs "name" mistake** — the tool's member argument is `name`, but the description's "one defined member" phrasing led agents to guess `member`, which failed with an unhelpful "The name field is required." The tool now rejects unrecognized parameters with an error that names the offending key and lists the accepted set (`Unknown parameter [member]. Accepted parameters: model (required), name (required), kind (optional). The member to fetch goes in "name"…`), and the `name.required` message explicitly calls out that the parameter is `name` not `member`. The always-loaded `#[Description]` names its parameters up front (`model + name, optional kind`) and now recommends `model-source` over grepping the source for `getXAttribute`/`scopeX`/relation bodies, since it resolves through the real trait/parent chain.

## v0.5.1 - 2026-07-05

### Changed

- **MCP `defined_in` pointers now carry the full line range (`path:start-end`)** — every scope/relation/accessor/member pointer from `inspect-model`, `model-source`, and `model-neighbors` reports the snippet's start *and* end line (e.g. `app/Models/Post.php:42-58`) instead of just the start line, so an agent can do a targeted read of exactly the method it wants to edit without guessing where it ends. Single-line members (properties, constants) collapse to `path:line`, and a pointer with no known line stays a bare `path`. The range is the snippet's true extent, including any preceding PHPDoc block. Sourced from `Reflection` end lines already available during the scan — no extra work per model.

## v0.5.0 - 2026-07-05

### Added

- **Factory awareness (MCP + UI)** — when a model exposes a factory, `inspect-model` includes a `factory` object in its overview (class + a `defined_in` `path:line` pointer) and the Model Detail page shows a `factory:` badge. Resolved through `Model::factory()`, so it honors a `$factory` property, a `#[UseFactory]` attribute, a custom `newFactory()`, and package factory traits (`HasPackageFactory`) — not just the name convention. Models without `HasFactory` report nothing, and if the resolved factory class is absent the detection degrades to null (never wrong); the class and path come from reflection on the real factory instance. This is the quick "where do I read the factory to write a test?" pointer — it does not parse the factory's definition or states. See ADR-014.
- **Enum casts are expanded into their cases (MCP + UI)** — a column that casts to a PHP enum now surfaces the enum's cases so an agent or developer sees the valid values without opening the enum. Backed enums render `Name=value`, pure enums render names only. In `inspect-model` the cases appear inline in the column string (`status: string cast:Status(Draft=draft, …)`, capped with a ` …+N more` suffix); the Model Detail page renders them as chips under the cast. The MCP cap is configurable via `mcp.enum_case_limit` (`MODEL_EXPLORER_MCP_ENUM_CASES`, default 12; `0` omits enum cases entirely), and an individual `inspect-model` call can override it with an `enum_case_limit` parameter — trade cases for tokens on a broad multi-model survey. See ADR-014.
- **Non-unique indexed columns are flagged, with composite-index awareness (MCP + UI)** — columns backed by a non-unique index are annotated so a lone-filter's cost is honest: `indexed` for a single-column index or a composite's leading column, and `indexed(composite-Nof M)` for a non-leading composite member (which a lone filter can't use). The Columns table shows an `indexed` badge or a muted `composite N/M` badge; the API exposes an `indexed` boolean (true only when usable alone) plus an `index_role` string. Primary and unique columns are skipped. Read from the model connection's `Schema::getIndexes()`; degrades silently on drivers that can't report indexes. See ADR-014.
- **Richer relation detail — pivot, morph type, through (MCP + UI)** — relations now carry the structural detail needed to write a query or migration against them: the pivot table, both pivot keys, and `withPivot()` extra columns for many-to-many; the `*_type` column for polymorphic relations; and the intermediate model plus origin key for has-many/one-through. `inspect-model` adds compact `pivot`/`pivot_keys`/`pivot_columns`/`morph_type`/`through`/`through_key` keys (omitted when not applicable); the Model Detail page shows a muted detail sub-line under each relation. Best-effort — relations that can't instantiate against a blank model leave the fields empty. See ADR-014.

### Changed

- **Promoted MCP to the lead feature in the README and docs home page** — the intro, feature list, and docs-site hero now lead with AI model introspection instead of burying it under the browser UI. The README's MCP section moved up to sit right after `Usage`, ahead of `Authorization`/`Configuration`, since it's independent of the HTTP gate.

### Fixed

- **Corrected the "Using Laravel Boost?" note on the MCP docs page** — it previously implied `boost:install` registers the `model-explorer` MCP server with your AI client ("no manual client config needed"), which is wrong: Boost only ships text guidelines telling an already-connected agent the server exists. There is no Boost hook to auto-register a third-party MCP server, so the client config step is always required, Boost or not. This was already correctly stated in ADR-011; only the docs site had drifted from it.

**Full Changelog**: https://github.com/One-Learning-Community/laravel-model-explorer/compare/v0.4.1...v0.5.0

## v0.4.1 - 2026-06-30

### Changed

- **Clarified the MCP tool surface's scope in agent-facing guidance** — the Boost guidelines, the server's `Instructions`, `find-model`'s description, and `model-neighbors`' description now explicitly state that these tools answer what is *defined* on a model and where, not where it's *referenced or called*. Points agents at a text search (e.g. grep) for call sites, usage beyond Eloquent relations, or anything in non-PHP files — including clarifying that `model-neighbors`' edges are model-to-model relation edges, not code references.

**Full Changelog**: https://github.com/One-Learning-Community/laravel-model-explorer/compare/v0.4.0...v0.4.1

## v0.4.0 - 2026-06-30

### Added

- **`model-source` resolves any member, not just scope/relation/accessor (MCP)** — `kind` is now optional and unrestricted. Omit it to resolve `name` by searching scopes, relations, accessors, and the wider `members` list (business methods, lifecycle hooks, properties, constants, …) in that order; pass it to narrow the search instead of constraining it to three kinds. Completes the "enumerate with `members`, then fetch the one body" workflow for any member, including plain business methods that `members` could enumerate but `model-source` previously couldn't fetch. See ADR-012.
- **`inspect-model`'s `members` section can be filtered (MCP)** — `include: ["members:relation,business"]` narrows to the given kinds; `include: ["members:file=Order.php"]` narrows to a declaring-file substring. `counts.members` keeps reporting the unfiltered total. Fixes the token-cost inversion of returning a noisy class's entire surface (hundreds of members) when only a few were wanted. See ADR-012.
- **`find-model` gains a `definesMember` filter (MCP)** — the structural analogue of `hasColumn`, but over the first-party members surface: "which models define `toSearchableArray`?" is now a first-class query that matches trait-composed members, instead of a source grep that would miss them. See ADR-012.
- **`model-neighbors` (MCP)** — a new tool returning a model's depth-1 relation neighborhood: `direction` (defaults to `incoming` — "which models point at this one," the blast-radius question `inspect-model`'s own relations section can't answer), `depth` (reserved for future multi-hop, only `1` validates today), and `limit` (default 50, with a `truncated` flag). This is the scoped graph return ADR-012 sanctioned when it retired the whole-graph `relationship-graph` tool — a bounded neighborhood instead of a dump, reusing `GraphBuilder` rather than re-reading every model's source. See ADR-013.

**Full Changelog**: https://github.com/One-Learning-Community/laravel-model-explorer/compare/v0.3.2...v0.4.0

## v0.3.2 - 2026-06-30

### Added

- **`inspect-model` `members` section (MCP)** — `include: ["members"]` (or `all`) now returns every member a model *defines* — methods, properties, and constants — each with a best-effort `kind` (`relation`/`scope`/`accessor`/`lifecycle`/`business`/`config`/`constant`/…) and a trait-correct `defined_in` pointer. Names, signatures, and pointers only; fetch a body with `model-source`. Only first-party members are listed (anything outside `vendor/`), so inherited framework methods never drown the result. Closes the "structure only, no behaviour" gap from the ADR-011 audit. See ADR-012.
- **Inspect models outside `model_paths` (MCP)** — set `MODEL_EXPLORER_MCP_ALLOW_UNDISCOVERED=true` to let `inspect-model` / `model-source` introspect any Eloquent model by fully-qualified class name, including a vendor package's model. Off by default; short names and `list-models`/`find-model` stay bounded to the discovered set. See ADR-012.

### Changed

- **Removed the `relationship-graph` MCP tool** — a whole-application graph overflows an agent's response budget at real scale (the audited app produced ~88 KB), and no audited task needed it that `find-model`/`inspect-model` did not serve better. The force-directed graph remains in the browser UI; only the agent-facing tool was retired. Scoped questions are answered by `find-model` (`relatesTo`) and `inspect-model`. See ADR-012. **(Breaking, pre-1.0:** the MCP tool set drops from five tools to four.)

**Full Changelog**: https://github.com/One-Learning-Community/laravel-model-explorer/compare/v0.3.1...v0.3.2

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
