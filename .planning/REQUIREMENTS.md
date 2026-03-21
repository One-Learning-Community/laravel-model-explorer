# Requirements: Laravel Model Explorer

**Defined:** 2026-03-20
**Core Value:** Developers can understand the full structure of any Eloquent model — its attributes, casts, and relationships — without reading code.

## v1 Requirements

### Foundation (Existing)

- [x] **FOUND-01**: Package assets are served via HTTP route with no vendor:publish required
- [x] **FOUND-02**: Asset controller protects against path traversal and restricts to allowed extensions
- [x] **FOUND-03**: Package is gated by a `viewModelExplorer` Laravel Gate, defaulting to local-env-only
- [x] **FOUND-04**: A hard kill switch (`MODEL_EXPLORER_ENABLED=false`) returns 404 regardless of gate
- [x] **FOUND-05**: Vue 3 SPA mounts in an isolated Blade shell with no host app frontend dependencies
- [x] **FOUND-06**: Package is configurable via `config/model-explorer.php` (path, middleware, model paths)

### Model Discovery

- [x] **DISC-01**: Package scans all directories listed in `config('model-explorer.model_paths')` for Eloquent models
- [x] **DISC-02**: Discovery safely handles non-model PHP classes and files without crashing
- [x] **DISC-03**: Discovery identifies a model's database table name

### Attribute Introspection

- [x] **ATTR-01**: Developer can view a model's fillable and guarded attributes
- [x] **ATTR-02**: Developer can view a model's hidden attributes
- [x] **ATTR-03**: Developer can view a model's casts with their target types
- [x] **ATTR-04**: Developer can view a model's appended attributes
- [x] **ATTR-05**: Developer can see whether a model uses timestamps and the column names

### Relationship Introspection

- [x] **REL-01**: Package detects relationship methods on a model via reflection
- [x] **REL-02**: Developer can view the type of each relationship (HasMany, BelongsTo, etc.)
- [x] **REL-03**: Developer can view the related model class for each relationship
- [x] **REL-04**: Developer can view the foreign key and local key for each relationship

### API Layer

- [x] **API-01**: `GET /_model-explorer/api/models` returns a list of all discovered models as JSON
- [x] **API-02**: `GET /_model-explorer/api/models/{model}` returns full detail for a single model as JSON
- [x] **API-03**: API endpoints are protected by the same `Authorize` middleware as the SPA
- [x] **API-04**: API returns appropriate error responses for unknown models

### Vue UI — Model List

- [x] **UI-01**: Developer can view a searchable/filterable list of all discovered models
- [x] **UI-02**: Model list shows each model's class name and database table
- [x] **UI-03**: Developer can navigate from the list to a model detail view

### Vue UI — Model Detail

- [x] **UI-04**: Developer can view all attributes for a model (fillable, hidden, casts, appends)
- [x] **UI-05**: Developer can view all relationships for a model with type, related model, and keys
- [x] **UI-06**: Developer can navigate from a relationship to the related model's detail view

### Relationship Graph

- [ ] **GRAPH-01**: Developer can view a visual graph of model relationships
- [ ] **GRAPH-02**: Graph nodes represent models; edges represent relationships with type labels
- [ ] **GRAPH-03**: Developer can click a graph node to navigate to that model's detail view

## v2 Requirements

### Live Data Browser

- **DATA-01**: Developer can browse paginated records for any model
- **DATA-02**: Developer can filter records by any column value
- **DATA-03**: Live data browsing respects the same authorization gate

### Documentation & DX

- **DOX-01**: README.md documents installation, configuration, and gate override
- **DOX-02**: CHANGELOG.md contains meaningful entries for each release

## Out of Scope

| Feature | Reason |
|---------|--------|
| Web-server-served static assets | PHP-served by design for developer tool; not a production UI |
| Customizable/extensible UI | Read-only introspection tool, not a component library |
| Real-time data watching | Complexity not justified for v1 |
| Inertia/Livewire integration | Self-contained SPA by design (ADR-003) |
| Model editing / data mutation | Read-only tool; mutations are out of scope for safety |
| Multi-tenancy / team scoping | Not required for a local developer tool |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| FOUND-01 | Phase 1 | Complete |
| FOUND-02 | Phase 1 | Complete |
| FOUND-03 | Phase 1 | Complete |
| FOUND-04 | Phase 1 | Complete |
| FOUND-05 | Phase 1 | Complete |
| FOUND-06 | Phase 1 | Complete |
| DISC-01 | Phase 2 | Complete |
| DISC-02 | Phase 2 | Complete |
| DISC-03 | Phase 2 | Complete |
| ATTR-01 | Phase 2 | Complete |
| ATTR-02 | Phase 2 | Complete |
| ATTR-03 | Phase 2 | Complete |
| ATTR-04 | Phase 2 | Complete |
| ATTR-05 | Phase 2 | Complete |
| REL-01 | Phase 3 | Complete |
| REL-02 | Phase 3 | Complete |
| REL-03 | Phase 3 | Complete |
| REL-04 | Phase 3 | Complete |
| API-01 | Phase 4 | Complete |
| API-02 | Phase 4 | Complete |
| API-03 | Phase 4 | Complete |
| API-04 | Phase 4 | Complete |
| UI-01 | Phase 5 | Complete |
| UI-02 | Phase 5 | Complete |
| UI-03 | Phase 5 | Complete |
| UI-04 | Phase 5 | Complete |
| UI-05 | Phase 5 | Complete |
| UI-06 | Phase 5 | Complete |
| GRAPH-01 | Phase 6 | Pending |
| GRAPH-02 | Phase 6 | Pending |
| GRAPH-03 | Phase 6 | Pending |

**Coverage:**
- v1 requirements: 31 total (28 complete, 3 pending)
- Mapped to phases: 31
- Unmapped: 0 ✓

---
*Requirements defined: 2026-03-20*
*Last updated: 2026-03-20 after roadmap creation; 2026-03-20 — REL-01–04 and API-01–04 marked complete*
