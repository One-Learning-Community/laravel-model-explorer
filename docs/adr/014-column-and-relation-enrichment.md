# ADR-014: Column & Relation Enrichment (enum cases, index flag, relation detail)

- **Status:** Accepted
- **Date:** 2026-07-04
- **Related:** ADR-004 (spatie/model-info for columns), ADR-011/012/013 (MCP surface)

## Context

The introspection surface described a model's columns and relations at a level that
still forced an agent (or a developer reading the Model Detail page) back to the source
for three recurring questions:

1. **What are the valid values of an enum-cast column?** The surface showed
   `status: string cast:StatusEnum` but not the enum's cases, so generated code guessed
   the values.
2. **Is a column cheap to filter on?** Primary and unique columns were flagged, but plain
   (non-unique) indexes were invisible.
3. **How do I actually query or migrate this relation?** Relations showed
   name/type/related/foreign-key, but not the many-to-many pivot table and its columns,
   the polymorphic `*_type` column, or the intermediate model of a has-\*-through.

All three are *structure* (schema shape and code shape), not database rows, so they sit
inside the MCP safety boundary (ADR-011) and are equally useful in the browser UI.

## Decision

Enrich the shared `ModelData` / `RelationData` DTOs — one source of truth feeding **both**
the MCP tools (`inspect-model`) and the browser Model Detail page.

### Carrying the data

`ModelData->attributes` is a collection of spatie's fixed `Attribute` objects with no room
for new fields. Rather than replace them, follow the existing `accessorSnippets` pattern:
**parallel side-maps keyed by column name.** Relations use our own `RelationData`, so those
fields are added directly.

| Enrichment | Producer (`ModelInspector`) | Carrier |
|---|---|---|
| Enum cases | reflect the cast class: `enum_exists()` + `::cases()` (no DB) | `ModelData->enumCasts` |
| index role | the model connection's `Schema::getIndexes()`; skip primary/unique | `ModelData->indexedColumns` (role label per column) |
| Relation detail | `extractRelationMeta()` — instantiate the live relation once | new nullable fields on `RelationData` |

Every producer is wrapped in `try/catch` and degrades to "no annotation" on failure,
consistent with the resilient list/graph philosophy.

### Surfacing the data

- **MCP** (`CompactPresenter`): column strings gain the index annotation (`indexed` /
  `indexed(composite-leading)` / `indexed(composite-Nof M)`) and inline
  `cast:Enum(Name=value, …)` (capped at `mcp.enum_case_limit` cases, default 12, then
  ` …+N more`; `0` omits enum cases entirely). Relation objects gain compact `pivot` /
  `pivot_keys` / `pivot_columns` / `morph_type` / `through` / `through_key` keys, omitted when
  not applicable.
- **Browser API** (`ModelsController::serialize`): each attribute gains `enum_cases`, an
  `indexed` boolean (true only when usable by a lone filter), and an `index_role` string;
  each relation gains the snake_case pivot/morph/through fields.
- **UI**: `ColumnsTable.vue` renders enum-case chips, an `indexed` badge, and a muted
  `composite N/M` badge for non-leading composite members; `RelationsTable.vue` renders a
  muted detail sub-line under the relation name.

### Index annotation reflects usability, not mere membership

A composite index `(a, b, c)` can only serve a lone filter on its **leading** column `a`;
`b`/`c` require the leading column(s) to also be constrained. Marking every member column a
flat `indexed` over-reports "cheap to filter." So the role distinguishes a single-column
index (`''`), a composite's leading column (`composite-leading`), and a non-leading member
(`composite-Nof M`). The API's `indexed` boolean is true only for the first two; the MCP
string and the UI badge carry the full role.

### Relation detail is best-effort

`extractRelationMeta()` gathers detail from the instantiated relation. Relations that
cannot be built against a blank model (a constraint closure that blows up) leave the new
fields null — exactly as `foreignKey`/`localKey` already behaved. Getters are chosen for
cross-version stability: the has-\*-through intermediate model is read via `getParent()`
(the through model is the relation's base parent), not the version-fragile
`getThroughParent()`.

## Consequences

**Positive**
- Agents write valid enum values, index-aware queries, and correct pivot/morph/through
  code without opening the source.
- One computation path; the subprocess "fresh" inspector (ADR pattern) serializes the whole
  `ModelData`, so the new fields cross the process boundary with no extra work.

**Trade-offs**
- The `indexed` flag reads the database schema (`getIndexes`) — one extra query per model
  inspect. Columns already require a DB connection, so no new assumption; on a driver that
  can't report indexes it silently degrades.
- Enum expansion is capped in the MCP surface to protect the token budget, via
  `mcp.enum_case_limit` (default 12; `0` disables it entirely). An `inspect-model` call may
  override the cap per-request with an `enum_case_limit` parameter — the operator sets the
  deployment default, the agent can trade cases for tokens on a broad survey. The browser API
  and `counts` are unaffected (the UI receives the full, uncapped case list).

## Alternatives considered

- **A dedicated `ColumnData` DTO** wrapping each spatie `Attribute` plus the new fields —
  cleaner object model but an invasive change touching every column consumer. Rejected as
  over-scoped; the side-map matches the existing `accessorSnippets` precedent.
- **Composite-index leading-column awareness** and a **separate `indexes` section** — more
  detail than the "is this cheap to filter?" question needs. Deferred (YAGNI).
- **MCP-only enrichment** — would have diverged the two views of the same model. Rejected in
  favour of shared DTOs.
