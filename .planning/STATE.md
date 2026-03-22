---
gsd_state_version: 1.0
milestone: v1.0
status: active
last_updated: "2026-03-22"
---

# Project State

## What's Built (All 6 Phases Complete + Post-Release Polish)

All originally planned phases are done. The package is a fully working developer tool:

- **Model discovery** — scans configured paths, finds all Eloquent models
- **Model inspection** — attributes (DB columns + virtual), casts, fillable/hidden/guarded, timestamps, traits, scopes (with source snippets + PHPDoc descriptions), relations (typed + source-scanned, with foreign/local keys, snippets, descriptions), accessor snippets
- **API layer** — `GET /api/models`, `GET /api/models/{model}`, `GET /api/graph`
- **Vue SPA** — Model list (searchable), Model detail (section nav, relation badges, snippet modal), Relationship graph (force-directed SVG, pan/zoom)
- **Record lookup** — page at `/models/:model/record`:
  - Find any record by PK or unique field
  - Attributes table with value formatting (dates, JSON pretty-print), copy-to-clipboard, expand/truncate toggle, filter by name/value
  - Accessor/virtual attributes listed separately, resolved lazily (individual or batch)
  - Relations expandable on demand (to-one or paginated to-many)
  - Breadcrumb trail navigation history across drill-downs
  - All DB reads wrapped in `Model::withoutEvents()` + rolled-back transaction

## Post-Release Polish (this session, 2026-03-22)

### ModelDetail improvements (commits `8494250`)
- **Virtual attribute source grouping** — attrs now grouped by `defined_in` (model-first, then traits/parents), matching scopes and relations
- **Group separator polish** — `bg-base-200/40` wash + `badge-neutral badge-sm` for class name; reads as a section divider
- **Section reorder** — Columns → Virtual Attrs → Relations → Scopes → Traits
- **Consistent section headings** — `text-xs` removed; all h2s now same weight/size
- Backend: `accessorSnippets` carry `defined_in`; exposed per-attribute in API

### PHPDoc descriptions (commit `b0d51c8`)
- Scopes, relations, and virtual attrs all show PHPDoc summary line inline below their name
- `SourceExtractor.forMethod()` now includes the docblock in extracted code snippets
- `ScopeData` and `RelationData` DTOs carry `description` and (RelationData) `snippet`

### Accessor Model/Collection rendering (commit `f8e1fd4`)
- Virtual accessor returning an Eloquent **Model** → rendered as a to-one record table + "View #N →" drill link
- Virtual accessor returning a **Collection** → rendered as a to-many column table (capped at 15, shows total), scrollable in-place (`overflow-auto max-h-64`) so the outer table doesn't expand
- `knownAttributes()` uses `ModelInfo` instead of `getAppends()` — fixes lookup of non-appended virtual accessors

## Current File Map (Key Files)

```
src/Http/Controllers/Api/
  ModelsController.php        GET /api/models, /api/models/{model}
  GraphController.php         GET /api/graph
  RecordsController.php       GET /api/models/{model}/record + /relations/{rel} + /attributes + /attributes/{attr}
src/Services/
  ModelInspector.php          inspects model: attributes, relations, scopes, traits, snippets, defined_in
  SourceExtractor.php         extracts source snippets + PHPDoc summary from ReflectionMethod

resources/js/pages/
  ModelList.vue               searchable grid of all models
  ModelDetail.vue             full model structure; grouped by source; section nav; snippet modal
  ModelRecord.vue             record lookup + drill-down + breadcrumb trail; accessor Model/Collection rendering
  ModelGraph.vue              SVG force-directed graph
```

## Possible Next Work

- **Package tagging** — cut a `v1.0.0` git tag and publish to Packagist
- **Changelog update** — add post-release polish entries to CHANGELOG.md
- **Accessor pagination** — to-many accessor results are capped at 15; could add a paginated endpoint
- **Record lookup for related accessor models** — the to-one/to-many drill links work but navigate to a new record lookup; could show inline

## Known Issues / Notes

- **Pre-existing test failure**: 403/production-environment tests fail with a Mockery error across all API test files (`askQuestion` not expected on `OutputStyle`). Not related to our code — don't investigate.
- **IDE linter warnings** on `$className::query()` / `$className::find()` are false positives — linter can't resolve dynamic class strings. Ignore.
- **Non-DB accessor side effects** (HTTP calls, queue pushes, cache writes) are NOT prevented by `withinSafeRead()` — only DB writes are rolled back.

## How to Run Tests

```bash
./vendor/bin/pest                                        # all tests
./vendor/bin/pest tests/Feature/Api/RecordsApiTest.php  # single file
./vendor/bin/pest --filter "resolves a to-one"          # by name
```

## How to Build Frontend

```bash
npm run build   # required after any resources/js or resources/css change
```
