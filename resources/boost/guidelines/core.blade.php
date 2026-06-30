## Model Explorer for Laravel — Model Introspection

This app ships the `model-explorer` MCP server. When you need to understand the
application's Eloquent models, prefer these tools over reading model source files —
they resolve trait-provided members and database columns that a source scan misses.

### Tools

- `list-models` — list every model (class, short name, table). Start here.
- `inspect-model` — one model's structure. Returns an overview with section counts,
  then the sections you ask for via `include` (`columns`, `relations`, `scopes`,
  `accessors`, `traits`, `mass-assignment`, `policy`, or `all`; default columns +
  relations). Pass the model as a short name (`Order`) or FQCN. Each scope/relation/
  accessor includes a `defined_in` `path:line` pointer.
- `relationship-graph` — all models as nodes + edges; use it to see how models connect.
- `find-model` — find models by `trait`, `extends`, `relatesTo`, or `hasColumn`
  (filters AND together). Use for cross-cutting questions like "which models use
  SoftDeletes" or "which models belong to Team".
- `model-source` — fetch the dedented, trait-correct source for one `scope`,
  `relation`, or `accessor` (`model`, `kind`, `name`). Use the `defined_in` pointers
  from `inspect-model` to decide what to fetch.

These tools read live, so results always reflect the current model code.
