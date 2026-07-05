# Column & Relation Enrichment — Design

- **Date:** 2026-07-04
- **Status:** Approved, in implementation
- **Related:** ADR-004 (spatie/model-info), ADR-011/012/013 (MCP surface)

## Summary

Three enrichments to the shared model introspection surface, benefiting **both** the
MCP tools (`inspect-model` column/relation output) and the browser Model Detail page:

1. **Enum cast expansion** — when a column casts to a PHP enum, surface its cases as
   `Name=value` pairs.
2. **Index awareness** — flag columns that participate in a non-unique database index.
3. **Richer relation detail** — pivot table + pivot keys + pivot columns (many-to-many),
   the morph type column (polymorphic), and the intermediate model + keys (through).

All three stay on the **structure-not-rows** side of the MCP safety boundary: they expose
schema and code shape, never live database rows.

## Architecture

`ModelData->attributes` is a `Collection` of spatie's fixed `Attribute` objects, which
have no room for new fields. Rather than replace them, we follow the existing
`accessorSnippets` pattern: **parallel side-maps on `ModelData`, keyed by column name.**
Relations use our own `RelationData` DTO, so those fields are added directly.

| Enrichment | Producer (in `ModelInspector`) | Carried on `ModelData` / `RelationData` |
|---|---|---|
| Enum cases | reflect the cast class (`enum_exists` + `::cases()`) | `enumCasts: array<string, list<array{name:string, value:string\|int\|null}>>` |
| `indexed` flag | `Schema::getIndexes($table)` | `indexedColumns: array<string, bool>` |
| Relation detail | `extractKeys()` (already invokes the live relation) | new nullable fields on `RelationData` |

Every producer wraps its work in `try/catch` and degrades to "no annotation" on failure,
consistent with the resilient list/graph philosophy (`summarize()` / `GraphController`).

### Data flow

```
ModelInspector::inspect()
  ├─ enumCasts      → ModelData (side-map)   ─┐
  ├─ indexedColumns → ModelData (side-map)   ─┤→ CompactPresenter (MCP JSON)
  └─ RelationData.{pivot*, morphType, through*}┘→ ModelsController::serialize (browser API)
                                                 → ColumnsTable.vue / RelationsTable.vue (UI)
```

## Feature 1 — Enum cast expansion

**Producer.** For each column whose `cast` resolves to an existing enum
(`enum_exists($cast)`), collect `::cases()`. Backed enums yield `{name, value}`;
pure/unbacked enums yield `{name, value: null}`. Store under `enumCasts[column]`.

**MCP output** (`CompactPresenter::columns`). Append to the column string:
`status: string cast:Status(Draft=draft, Published=published, Archived=archived)`.
Cap at **12 cases**, then ` …+N more` to protect the token budget. Pure enums render
names only: `cast:Priority(Low, High)`.

**Browser UI** (`ColumnsTable.vue`). Under the Cast cell (or a sub-row), render the cases
as small muted `Name=value` chips.

**API** (`ModelsController::serialize`). Add `enum_cases` to each attribute entry (array of
`{name, value}` or `null`).

## Feature 2 — Index awareness

**Producer.** Call `Schema::getIndexes($table)` (Laravel 11+). For every index that is
**not** `primary` and **not** `unique` (those columns are already flagged and implicitly
indexed), mark each of its columns `true` in `indexedColumns`. Wrap in `try/catch` — exotic
drivers or a missing connection degrade to no flag.

**MCP output.** Append ` indexed` to the column string after the existing annotations:
`author_id: integer FK→User indexed`.

**Browser UI.** Add an `indexed` badge (`badge-ghost badge-xs`) alongside `unique`.

**API.** Add `indexed: bool` to each attribute entry.

## Feature 3 — Richer relation detail

Extend `RelationData` with nullable fields, populated in `extractKeys()` from the live
relation instance (best-effort — the fallback path for un-instantiable relations leaves them
null, exactly as it already leaves `foreignKey`/`localKey` null):

| Field | Source | Applies to |
|---|---|---|
| `pivotTable` | `$rel->getTable()` | BelongsToMany, MorphToMany |
| `pivotForeignKey` | `$rel->getForeignPivotKeyName()` | BelongsToMany, MorphToMany |
| `pivotRelatedKey` | `$rel->getRelatedPivotKeyName()` | BelongsToMany, MorphToMany |
| `pivotColumns` | `$rel->getPivotColumns()` minus the two default keys | BelongsToMany, MorphToMany |
| `morphType` | `$rel->getMorphType()` | MorphTo, MorphOne, MorphMany, MorphToMany |
| `throughModel` | `get_class($rel->getParent())` / through parent | HasOneThrough, HasManyThrough |
| `throughForeignKey`, `throughLocalKey` | through relation key getters | HasOneThrough, HasManyThrough |

**MCP output** (`CompactPresenter::relations`). Add the fields that are present to each
relation object under compact keys, e.g. `pivot: "role_user"`, `pivot_keys: ["role_id","user_id"]`,
`pivot_columns: ["expires_at"]`, `morph_type: "commentable_type"`, `through: "Mechanic"`.
Omit absent fields (existing `array_filter` idiom).

**Browser UI** (`RelationsTable.vue`). Render pivot/morph/through detail as a muted sub-line
under the relation method name, so the table columns stay stable.

**API.** Add the new fields (snake_case) to each relation entry.

## Workbench fixtures (for tests)

Add to `workbench/` — a new migration + models/enums as needed:

- A **backed enum** (e.g. `Workbench\App\Enums\PostStatus: string`) cast on a new `posts.status`
  column, plus a **pure enum** on another column to exercise the unbacked path.
- A **non-unique index** (`->index()`) on a column, a **unique** column, and confirm PK is skipped.
- A **`belongsToMany`** with `->withPivot(...)` (e.g. `Post` ↔ `Tag` via a `post_tag` pivot with
  an extra `sort_order` column).
- A **morph** relation (e.g. `Comment` `morphTo commentable`, `Post morphMany comments`).
- A **`hasManyThrough`** (e.g. `Country hasManyThrough Post through User`) — or reuse existing
  models if a clean chain exists.

## Testing

Pest, following existing style. New/extended coverage:

- `ModelInspectorTest` — `enumCasts`, `indexedColumns`, and the new `RelationData` fields are
  populated correctly; graceful degradation when a relation can't instantiate / index lookup fails.
- `Api/ModelsApiTest` — `enum_cases`, `indexed`, and relation detail appear in the detail JSON.
- MCP presenter coverage (extend the existing MCP tool tests) — column strings carry
  `cast:Enum(...)` and `indexed`; relation objects carry pivot/morph/through keys; enum cap works.

Run: `./vendor/bin/pest`. Frontend: `npm run build` after each `resources/js` change.

## Out of scope (YAGNI)

- Composite-index leading-column awareness (chose the simple boolean flag).
- A separate `indexes` section on `inspect-model`.
- Enum expansion via a separate on-demand endpoint (inline is enough).
- Multi-hop relation paths (`model-neighbors` depth) — separate future idea.

## Commit plan

One commit per feature onto `main` (no push): spec → enum → index → relation detail → docs
(ADR-014 + `mcp.md` + `model-detail.md` + CHANGELOG).
