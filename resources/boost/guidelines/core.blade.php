## Model Explorer for Laravel — Model Introspection

This app ships the `model-explorer` MCP server. When you need to understand the
application's Eloquent models, prefer these tools over reading model source files —
they resolve trait-provided members and database columns that a source scan misses.

### Tools

- `list-models` — list every model (class, short name, table). Start here.
- `inspect-model` — one model's structure. Returns an overview with section counts,
  then the sections you ask for via `include` (`columns`, `relations`, `scopes`,
  `accessors`, `traits`, `mass-assignment`, `policy`, `members`, or `all`; default
  columns + relations). Pass the model as a short name (`Order`) or FQCN. Each scope/
  relation/accessor includes a `defined_in` `path:line` pointer. `members` lists every
  first-party method/property/constant (names + signatures + pointers, no bodies) with
  a best-effort `kind` — use it to see what a model *does*, then `model-source` a body.
  On a large class, narrow it with `include: ["members:relation,business"]` (kind
  filter) or `include: ["members:file=Order.php"]` (declaring-file filter) instead of
  pulling the whole surface.
- `find-model` — find models by `trait`, `extends`, `relatesTo`, `hasColumn`, or
  `definesMember` (filters AND together). Use for cross-cutting questions like "which
  models use SoftDeletes", "which models belong to Team", or "which models define
  toSearchableArray" — `definesMember` matches trait-composed members too.
- `model-source` — fetch the dedented, trait-correct source for one named member
  (`model`, `name`, optional `kind`). Any member works, not just `scope`/`relation`/
  `accessor` — omit `kind` to resolve by name alone across scopes, relations,
  accessors, and the wider members list (business methods, lifecycle hooks,
  properties, constants, …). Use the `defined_in` pointers from `inspect-model` to
  decide what to fetch.
- `model-neighbors` — a model's depth-1 relation neighborhood (`model`, optional
  `direction`/`limit`). `direction` defaults to `incoming`: "which models point at
  this one" — the blast-radius question `inspect-model`'s own relations section
  can't answer, since that only shows outgoing relations. Use it before changing a
  model's structure to see what else depends on it. Bounded by `limit` (default 50)
  with a `truncated` flag; not a whole-graph dump.

### When NOT to use these tools

These tools answer **what is defined on a model and where it comes from** — structure,
not usage. They cannot tell you where a model or method is *referenced or called*. For
that — call sites, blast radius beyond Eloquent relations, anything in non-PHP files
(Blade, Vue, JS, config), or code that doesn't parse yet — use a text search such as
grep. Rule of thumb: asking about *the thing* → model-explorer; asking about *who
references the thing* → grep. (`model-neighbors` is the one apparent exception, and it
isn't: its "what depends on this" answer is strictly model-to-model relation edges, not
code references.)

These tools read live, so results always reflect the current model code.
