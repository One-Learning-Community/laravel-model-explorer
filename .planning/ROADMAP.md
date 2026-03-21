# Roadmap: Laravel Model Explorer

## Overview

Phase 1 (Foundation) is complete — asset serving, authorization, and the Vue SPA shell are all working. Phases 2 through 6 build the actual explorer: a PHP introspection engine that scans models and surfaces their attributes and relationships (Phases 2–3), a JSON API that exposes that data (Phase 4), a Vue UI that consumes it (Phase 5), and a visual relationship graph (Phase 6).

## Phases

- [x] **Phase 1: Foundation** - Route-served assets, gate authorization, Vue SPA shell, package configuration
- [x] **Phase 2: Model Discovery Engine** - Scan configured paths, resolve Eloquent classes, extract all attribute metadata (completed 2026-03-21)
- [ ] **Phase 3: Relationship Introspection** - Detect relationship methods via reflection, extract type, related model, and keys
- [ ] **Phase 4: API Layer** - JSON endpoints for model list and model detail, behind existing authorization middleware
- [ ] **Phase 5: Vue UI — Model List and Detail** - Searchable model list, attribute/relationship detail view, in-app navigation
- [ ] **Phase 6: Relationship Graph** - Visual graph of model relationships with clickable nodes

## Phase Details

### Phase 1: Foundation
**Goal**: The package is installable, serves a protected SPA shell, and is configurable — ready for real features.
**Depends on**: Nothing (first phase)
**Requirements**: FOUND-01, FOUND-02, FOUND-03, FOUND-04, FOUND-05, FOUND-06
**Success Criteria** (what must be TRUE):
  1. Assets are served via PHP route without any vendor:publish step
  2. The SPA is inaccessible outside local environment unless the gate is overridden
  3. Setting `MODEL_EXPLORER_ENABLED=false` returns 404 for all package routes
  4. Path prefix and model scan paths are configurable via `config/model-explorer.php`
**Plans**: Complete

Plans:
- [x] 01-01: Asset controller with path traversal protection and extension allowlist
- [x] 01-02: Gate-based authorization middleware with local-env default and kill switch
- [x] 01-03: Vue 3 SPA shell in isolated Blade template
- [x] 01-04: Package service provider with configuration publishing and route registration

### Phase 2: Model Discovery Engine
**Goal**: Developers have a PHP service that finds every Eloquent model in configured paths and extracts complete attribute metadata.
**Depends on**: Phase 1
**Requirements**: DISC-01, DISC-02, DISC-03, ATTR-01, ATTR-02, ATTR-03, ATTR-04, ATTR-05
**Success Criteria** (what must be TRUE):
  1. All Eloquent model classes in `config('model-explorer.model_paths')` are discovered without manual registration
  2. Non-model PHP files and classes in scan paths are silently skipped without errors
  3. Each discovered model exposes its database table name
  4. Each discovered model exposes its fillable, guarded, and hidden attributes
  5. Each discovered model exposes its casts, appended attributes, timestamp configuration, and timestamp column names
**Plans**: 3 plans

Plans:
- [ ] 02-01-PLAN.md — Workbench fixture models and test file stubs (Wave 0)
- [ ] 02-02-PLAN.md — ModelData DTO and ModelDiscovery filesystem scanner (Wave 1)
- [ ] 02-03-PLAN.md — ModelInspector attribute extractor and ServiceProvider singleton bindings (Wave 2)

### Phase 3: Relationship Introspection
**Goal**: The discovery engine also surfaces all relationship methods on a model, with enough metadata to understand the connection without reading code.
**Depends on**: Phase 2
**Requirements**: REL-01, REL-02, REL-03, REL-04
**Success Criteria** (what must be TRUE):
  1. Relationship methods on a model are detected automatically via PHP reflection
  2. Each relationship's type (HasMany, BelongsTo, HasOne, BelongsToMany, etc.) is identified
  3. Each relationship's related model class is identified
  4. Each relationship's foreign key and local key are extracted where applicable
**Plans**: TBD

### Phase 4: API Layer
**Goal**: The Vue SPA can fetch structured model data from JSON endpoints protected by the same authorization gate.
**Depends on**: Phase 3
**Requirements**: API-01, API-02, API-03, API-04
**Success Criteria** (what must be TRUE):
  1. `GET /_model-explorer/api/models` returns a JSON list of all discovered models with class name and table
  2. `GET /_model-explorer/api/models/{model}` returns full attribute and relationship detail for that model
  3. Both endpoints return 403 when the authorization gate denies access
  4. Requesting an unknown model slug returns a structured 404 JSON response
**Plans**: TBD

### Phase 5: Vue UI — Model List and Detail
**Goal**: Developers can open the explorer in a browser, search models, and read the full structure of any model without touching a terminal or code file.
**Depends on**: Phase 4
**Requirements**: UI-01, UI-02, UI-03, UI-04, UI-05, UI-06
**Success Criteria** (what must be TRUE):
  1. The model list is searchable and shows each model's class name and database table
  2. Clicking a model navigates to its detail view without a full page reload
  3. The detail view shows all attributes — fillable, hidden, casts, and appends
  4. The detail view shows all relationships with type, related model, and key information
  5. Clicking a related model name in the detail view navigates to that model's detail view
**Plans**: TBD

### Phase 6: Relationship Graph
**Goal**: Developers can see the entire model relationship landscape as a visual graph and click into any model from it.
**Depends on**: Phase 5
**Requirements**: GRAPH-01, GRAPH-02, GRAPH-03
**Success Criteria** (what must be TRUE):
  1. A visual graph renders with one node per discovered model
  2. Edges between nodes represent relationships and are labelled with the relationship type
  3. Clicking a node navigates to that model's detail view
**Plans**: TBD

## Progress

**Execution Order:** 1 → 2 → 3 → 4 → 5 → 6

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. Foundation | 4/4 | Complete | 2026-03-20 |
| 2. Model Discovery Engine | 3/3 | Complete   | 2026-03-21 |
| 3. Relationship Introspection | 0/TBD | Not started | - |
| 4. API Layer | 0/TBD | Not started | - |
| 5. Vue UI — Model List and Detail | 0/TBD | Not started | - |
| 6. Relationship Graph | 0/TBD | Not started | - |
