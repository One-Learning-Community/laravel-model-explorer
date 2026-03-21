# Laravel Model Explorer

## What This Is

A Laravel package that gives developers a browser-based interface to explore their Eloquent models — attributes, casts, relationships, and eventually live data. It ships as a self-contained Vue 3 SPA served directly by PHP routes, requiring zero frontend tooling from the host application and no `vendor:publish` step.

## Core Value

Developers can understand the full structure of any Eloquent model — its attributes, casts, and relationships — without reading code.

## Requirements

### Validated

- ✓ Route-served asset delivery with path traversal protection and extension allowlist — Phase 1
- ✓ Gate-based authorization (`viewModelExplorer`) with local-env-only default and kill switch — Phase 1
- ✓ Self-contained Vue 3 SPA mounted in a minimal Blade shell, no host app frontend dependencies — Phase 1
- ✓ Configurable path prefix, middleware stack, and model scan paths via `config/model-explorer.php` — Phase 1
- ✓ Test coverage for routing, authorization, and asset security — Phase 1

### Active

- [ ] Model discovery engine — scan configured paths, resolve Eloquent model classes safely
- [ ] Attribute introspection — extract table name, fillable, hidden, casts, appends, timestamps config
- [ ] Relationship introspection — detect relationship methods via reflection, extract type, related model, foreign/local keys
- [ ] JSON API endpoints — model list and model detail, behind the same authorization middleware
- [ ] Vue UI: searchable model list and detail view showing attributes and relationships
- [ ] Relationship graph — visual graph of model relationships
- [ ] Live data browser — paginated table of actual model records with column filtering (stretch)

### Out of Scope

- Web-server-served assets (Nginx/Apache) — PHP-served by design for a developer tool; acceptable trade-off per ADR-001
- Customizable/extensible UI components — read-only introspection tool, not a component library
- Real-time data watching or subscriptions — out of scope for v1
- Inertia/Livewire integration — self-contained SPA by design per ADR-003

## Context

- Built as a Spatie Laravel package skeleton; `configure.php` scaffold script and README/CHANGELOG placeholders should be cleaned up before first public release.
- The `LaravelModelExplorer` main class and `ModelExplorer` facade are currently empty — will be populated when the model discovery engine is built.
- The Vue app is a placeholder UI; Vue Router and state management (Pinia) will be introduced when the model list/detail UI is built.
- ADRs live in `docs/adr/` and document the three core architectural decisions.

## Constraints

- **Tech stack**: PHP ^8.4, Laravel 11/12/13, Vue 3 + Vite 6 — no changes without discussion
- **Asset pipeline**: Compiled assets (`public/app.js`, `public/app.css`) must be committed to the repo — consumers don't need Node.js
- **Isolation**: No modifications to host application's `package.json`, `vite.config.js`, or frontend stack
- **Compatibility**: Package must work in any Laravel app regardless of its frontend approach (Blade, Inertia, Livewire, API-only)

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Route-served assets (ADR-001) | Avoids vendor:publish friction; suitable for a developer tool | — Pending |
| Gate-based authorization (ADR-002) | Flexible, uses Laravel idioms consumers already know; secure by default | — Pending |
| Self-contained Vue 3 SPA (ADR-003) | Works across all Laravel apps regardless of frontend stack | — Pending |
| Phases 2–4 before UI (Phase 5) | Backend introspection engine and API must exist before UI can display real data | — Pending |
| Vue Router + Pinia deferred to Phase 5 | Not needed until multi-view UI; avoids premature complexity | — Pending |

---
*Last updated: 2026-03-20 after initialization*
