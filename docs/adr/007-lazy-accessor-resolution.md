# ADR-007: Lazy Accessor Resolution via Separate API Endpoints

- **Status:** Accepted
- **Date:** 2026-03-20

## Context

Eloquent accessor attributes (`$appends`, old-style `getFooAttribute()`, and new-style `foo(): Attribute`) are computed values. Their cost and safety vary widely:

- Some are trivial (formatting a date string).
- Some trigger additional queries (loading a nested relation).
- Some perform HTTP calls or filesystem reads.
- Some throw exceptions for specific records (e.g., an accessor that assumes a related model always exists).

Loading all accessors eagerly on `RecordsController::show()` would make the endpoint slow for models with expensive accessors, and brittle for models where any accessor can throw. A single throwing accessor would make the entire record inaccessible.

## Decision

`RecordsController::show()` returns only the model's raw stored attribute values via `Model::getAttributes()`. No accessor logic is evaluated.

Accessor and virtual attributes are resolved lazily via two additional endpoints:

- `GET /api/models/{model}/record/{id}/attributes` — evaluates all accessor attributes for the record. Each accessor is evaluated independently inside a try/catch; errors are returned as `{ error: "..." }` rather than aborting the response.
- `GET /api/models/{model}/record/{id}/attributes/{name}` — evaluates a single named accessor.

`ModelRecord.vue` calls the bulk endpoint lazily when the user expands the "Accessors" panel. Individual accessors can also be re-fetched independently.

All accessor resolution endpoints are wrapped in `withinSafeRead()` (see ADR-006).

## Consequences

**Positive:**
- Fast initial record load regardless of accessor complexity or count.
- A failing accessor is isolated — it surfaces as a per-attribute error, not a 500 for the entire record view.
- Aligns with the general principle of not evaluating side-effectful code unless the user explicitly requests it.

**Negative:**
- Extra API round-trips: one for the record, one for accessors (or more if individual accessors are refetched).
- The UI must handle a loading state for the accessor panel and per-attribute error display.
- Virtual attributes (those only in `$appends`, with no corresponding DB column) are not visible in the raw `getAttributes()` response — they are only surfaced via the accessor endpoints. The UI must communicate this clearly.
