---
gsd_state_version: 1.0
milestone: v1.0
status: active
last_updated: "2026-03-22"
---

# Project State

## What's Built (All 6 Phases Complete)

All originally planned phases are done. The package is a fully working developer tool:

- **Model discovery** — scans configured paths, finds all Eloquent models
- **Model inspection** — attributes (DB columns + virtual), casts, fillable/hidden/guarded, timestamps, traits, scopes (with source snippets), relations (typed + source-scanned, with foreign/local keys), accessor snippets
- **API layer** — `GET /api/models`, `GET /api/models/{model}`, `GET /api/graph`
- **Vue SPA** — Model list (searchable), Model detail (section nav, relation badges, snippet modal), Relationship graph (force-directed SVG, pan/zoom)
- **Record lookup** (added 2026-03-22, commit `0c197be`) — new page at `/models/:model/record`:
  - Find any record by PK or unique field
  - Attributes table shows raw `getAttributes()` values only — no accessor side effects
  - Accessor/virtual attributes listed separately, resolved lazily (individual or batch)
  - Relations expandable on demand (to-one or paginated to-many)
  - "View record" links drill into related models — full navigation chain
  - All DB reads wrapped in `Model::withoutEvents()` + rolled-back transaction

## Current File Map (Key Files)

```
src/Http/Controllers/Api/
  ModelsController.php        GET /api/models, /api/models/{model}
  GraphController.php         GET /api/graph
  RecordsController.php       GET /api/models/{model}/record + /relations/{rel} + /attributes + /attributes/{attr}

resources/js/pages/
  ModelList.vue               searchable grid of all models
  ModelDetail.vue             full model structure; has "Look up record" button in header
  ModelRecord.vue             record lookup + drill-down; watch() handles Vue Router component reuse
  ModelGraph.vue              SVG force-directed graph
```

## Possible Next Work

Things discussed or likely to come up:

1. **UX polish on record lookup** — value formatting (dates, JSON pretty-print), copy-to-clipboard, truncation toggle for long values
2. **Search within record attributes** — filter the attributes table by name/value
3. **Relation record count badges** — show total count on each relation before loading
4. **Breadcrumb / drill-down history** — track navigation path (Post#5 → User#1 → ...) so the user can go back
5. **Model list record count** — show row count per model on the ModelList cards
6. **Package release prep** — remove `configure.php` scaffold script, review README, tag v1.0

## Known Issues / Notes

- **Pre-existing test failure**: 403/production-environment tests fail with a Mockery error across all API test files (`askQuestion` not expected on `OutputStyle`). Not related to our code — don't investigate.
- **IDE linter warnings** on `$className::query()` / `$className::find()` are false positives — linter can't resolve dynamic class strings. Ignore.
- **Non-DB accessor side effects** (HTTP calls, queue pushes, cache writes) are NOT prevented by `withinSafeRead()` — only DB writes are rolled back. Worth noting to users.
- ROADMAP.md and earlier STATE.md content are outdated (reflect early GSD phase tracking) — this file is the source of truth.

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
