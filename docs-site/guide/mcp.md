---
outline: [2, 3]
---

# AI Model Introspection (MCP)

Model Explorer ships a local [`laravel/mcp`](https://laravel.com/docs/mcp) server, **`model-explorer`**, that lets AI coding agents introspect your Eloquent models the same way the browser UI does — columns, relationships, scopes, accessors, traits, and source snippets — **without scanning your source files**.

Because the tools reuse the package's own inspection services, an agent sees what a source scan misses: trait-provided relations and scopes, database columns, casts, and correctly-attributed source locations.

::: tip Why an MCP server?
A grep over your `app/Models` directory can't tell an agent that a relation is defined in a trait, what a column's real database type is, or which scope accepts which parameters. These tools answer those questions directly and live, so the agent never reasons on stale structure mid-development.
:::

## Requirements

- The `model-explorer` server is registered only when both `model-explorer.enabled` and `model-explorer.mcp.enabled` are true (see [Configuration](#configuration)).
- `laravel/mcp` is installed as a dependency of this package, so no extra install step is required.

## Registering the server with your AI client

Point your MCP-capable client (Claude Code, Cursor, etc.) at the server with the `mcp:start` artisan command:

```json
{
  "mcpServers": {
    "model-explorer": {
      "command": "php",
      "args": ["artisan", "mcp:start", "model-explorer"]
    }
  }
}
```

Run this from your application's root so `php artisan` resolves to your app.

::: tip Using Laravel Boost?
If you use [Laravel Boost](https://laravel.com/docs/boost), `boost:install` copies this package's Boost guidelines into your project, so a Boost-driven agent is told the `model-explorer` server exists and what each tool does. That's text, not registration: Boost has no hook to add a third-party MCP server (like this one) to your client's config on your behalf, so you still need to register `model-explorer` yourself with the client config above — one-time, whether or not you use Boost.
:::

## The tools

The server exposes five tools. Every tool returns compact, structured JSON; each scope, relation, accessor, and member carries a `defined_in` pointer in `path:line` form (relative to your application root) so the agent can jump straight to the definition.

| Tool | Purpose |
|---|---|
| [`list-models`](#list-models) | List every discovered model |
| [`inspect-model`](#inspect-model) | One model's full structure |
| [`find-model`](#find-model) | Find models by structural criteria |
| [`model-source`](#model-source) | Fetch one definition's source snippet |
| [`model-neighbors`](#model-neighbors) | A model's depth-1 relation neighborhood |

Models are referenced by their **fully-qualified class name (FQCN)** or **short class name** — `App\Models\Order` or just `Order`.

### `list-models`

Lists every discovered model with its class, short name, and table. Start here to see what exists.

```json
{
  "models": [
    { "class": "App\\Models\\Post", "name": "Post", "table": "posts" },
    { "class": "App\\Models\\User", "name": "User", "table": "users" }
  ],
  "count": 2
}
```

### `inspect-model`

Returns one model's structure: an overview with section counts, then the sections you ask for. When the model has a **factory** (its resolved factory class actually exists on disk), the overview also carries a `factory` object with the factory's class and a `defined_in` pointer — so the agent can jump straight to the factory to write a test, without this tool parsing the factory itself. Absent when no factory exists.

**Input**

| Parameter | Description |
|---|---|
| `model` | FQCN or short class name (required) |
| `include` | Sections to return: `columns`, `relations`, `scopes`, `accessors`, `traits`, `mass-assignment`, `policy`, `members`, or `all`. Defaults to `columns` + `relations`. The `members` section can be narrowed — see below. |
| `enum_case_limit` | Optional. Max enum cases expanded inline per column; `0` omits them entirely. Overrides the `mcp.enum_case_limit` config default (12) for this call. |

**Output** (default sections)

```json
{
  "class": "App\\Models\\Post",
  "name": "Post",
  "table": "posts",
  "key": "id",
  "counts": { "columns": 6, "relations": 3, "scopes": 2, "accessors": 1, "traits": 2 },
  "factory": { "class": "Database\\Factories\\PostFactory", "defined_in": "database/factories/PostFactory.php:14" },
  "columns": [
    "id: integer PK",
    "author_id: integer FK→User indexed",
    "title: string",
    "status: string cast:Status(Draft=draft, Published=published, Archived=archived)",
    "published_at: datetime nullable cast:datetime"
  ],
  "relations": [
    { "name": "author", "type": "belongsTo", "related": "User", "via": "author_id", "defined_in": "app/Models/Concerns/HasAuthor.php:9" }
  ]
}
```

Columns are rendered as terse strings annotated with `PK`, `FK→{Model}`, `unique`, `nullable`, and `cast:{Type}`, plus an index annotation for columns in a non-unique index: `indexed` when the column leads a single-column index (a lone filter can use it), `indexed(composite-leading)` when it leads a composite index, and `indexed(composite-2of3)` for a non-leading member of a composite (a lone filter on it *cannot* use the index — only a query that also constrains the leading column(s) can). Primary and unique columns are already flagged and omitted here. When a cast is a **PHP enum**, its cases are expanded inline — backed enums as `cast:Enum(Name=value, …)`, pure enums as `cast:Enum(Name, …)` — capped at `mcp.enum_case_limit` cases (default 12) with a ` …+N more` suffix so a wide enum can't blow the response budget.

To trade those cases for tokens on a broad survey, pass **`enum_case_limit`** on the call — an integer cap, or `0` to omit enum cases entirely (columns then show just `cast:Status`). It overrides the [configured default](#configuration) for that one call:

```json
{ "model": "Order", "include": ["columns"], "enum_case_limit": 0 }
```

Relation objects carry extra structural detail when it applies (absent otherwise): `pivot` (the join table) with `pivot_keys` and `pivot_columns` for many-to-many; `morph_type` (the `*_type` column) for polymorphic relations; and `through` (the intermediate model) with `through_key` for has-many/one-through. For example, a `belongsToMany` renders as:

```json
{ "name": "tags", "type": "belongsToMany", "related": "Tag", "pivot": "post_tag", "pivot_keys": ["post_id", "tag_id"], "pivot_columns": ["sort_order"], "defined_in": "app/Models/Post.php:40" }
```

#### The `members` section

`include: ["members"]` (or `all`) adds a **members** section: every member the model actually *defines* — methods, properties, and constants — so the agent can see what a model **does**, not just its columns and relations. It answers "what's on this class, and where does each piece come from?" without opening the file.

Only **first-party** members are listed: anything defined outside a `vendor/` directory. The hundreds of inherited framework methods (`save`, `delete`, `newQuery`, …) are excluded, and a trait-provided member points at the **trait** file, not the model. Each member carries a best-effort `kind` (`relation`, `scope`, `accessor`, `lifecycle`, `business`, `config`, `constant`, …) — a hint, not a contract — plus a `defined_in` pointer. Bodies are **not** included; fetch one on demand with [`model-source`](#model-source).

```json
{
  "counts": { "columns": 6, "relations": 3, "members": 9, "...": "..." },
  "members": {
    "methods": [
      "protected static booted(): void [lifecycle] @ app/Models/Order.php:42",
      "markPaid(Carbon $at): void [business] @ app/Models/Order.php:88",
      "author(): BelongsTo [relation] @ app/Models/Concerns/HasAuthor.php:9"
    ],
    "properties": [
      "$fillable [config] @ app/Models/Order.php:20",
      "const MAX_ITEMS = 50 [constant] @ app/Models/Order.php:12"
    ]
  }
}
```

#### Filtering the `members` section

A class with a wide surface can return hundreds of members — more tokens than an agent wants when it only cares about a few. Two filter forms narrow `include`'s `members` entry instead of requesting the whole section:

- `members:<kind1>,<kind2>` — keep only members whose `kind` matches one of the given values, e.g. `include: ["members:relation,business"]` keeps relations and plain business methods, dropping lifecycle hooks, config properties, etc.
- `members:file=<substring>` — keep only members whose `defined_in` file contains the substring, e.g. `include: ["members:file=HasAuthor.php"]` keeps only members declared in that trait.

Either form still triggers the `members` section; `counts.members` in the overview always reports the **unfiltered** total, so the agent can tell how much was left out.

### `find-model`

Finds models matching structural criteria — answering cross-cutting questions without inspecting every model. Provide at least one filter; filters combine with **AND**.

**Input**

| Filter | Matches models that… |
|---|---|
| `trait` | use the given trait (short name or FQCN) |
| `extends` | extend the given parent class |
| `relatesTo` | have a relation pointing at the given model |
| `hasColumn` | have the given column in their table |
| `definesMember` | define the given method/property/constant (short name), including trait-composed members |

**Output** — each match lists which filters it satisfied:

```json
{
  "models": [
    { "class": "App\\Models\\Post", "name": "Post", "table": "posts", "matched": ["trait: App\\Models\\Concerns\\HasAuthor"] }
  ],
  "count": 1
}
```

Use it for questions like *"which models use `SoftDeletes`"*, *"which models belong to `Team`"*, or *"which models define `toSearchableArray`"*. The last one is the structural analogue of `hasColumn`: it matches against the same first-party member list `members` enumerates, so it catches a method defined in a composed trait that a plain source grep would miss.

### `model-source`

Returns the dedented, **trait-correct** source for one named member. Use the `defined_in` pointers from `inspect-model` to decide what to fetch.

**Input**

| Parameter | Description |
|---|---|
| `model` | FQCN or short class name (required) |
| `name` | The member name, e.g. scope `recent`, relation `author`, or any other member like `markPaid` (required) |
| `kind` | Optional. Narrows the lookup: `scope`, `relation`, `accessor`, or any other `members` kind (`business`, `lifecycle`, `magic`, `method`, `property`, `constant`, `config`). Omit to resolve by name alone. |

**Output**

```json
{
  "code": "public function author(): BelongsTo\n{\n    return $this->belongsTo(User::class, 'author_id');\n}",
  "defined_in": "app/Models/Concerns/HasAuthor.php:9"
}
```

Omitting `kind` searches scopes, relations, accessors, and the wider members list (business methods, lifecycle hooks, properties, constants, …) in that order until `name` matches — so the natural workflow is "enumerate with `members`, then fetch the one body" without knowing the kind in advance. Properties and constants have no reflectable body; their `code` is the single declaration line instead. If `name` doesn't match anything, the error points the agent at `inspect-model`'s `members` section to see what's available.

### `model-neighbors`

Returns a model's **depth-1 relation neighborhood**: a bounded list of relation edges. Answers *"what breaks if I change this model"* — specifically the direction `inspect-model` can't show you, since its `relations` section only covers a model's own (outgoing) relations.

**Input**

| Parameter | Description |
|---|---|
| `model` | FQCN or short class name (required) |
| `direction` | `incoming`, `outgoing`, or `both`. Defaults to `incoming` — "which models point at this one." |
| `depth` | Reserved for future multi-hop traversal. Only `1` (the default) is currently supported; other values error. |
| `limit` | Maximum number of edges to return. Defaults to `50`; excess sets `truncated: true`. |

**Output**

```json
{
  "root": "App\\Models\\Profile",
  "direction": "incoming",
  "edges": [
    { "direction": "incoming", "from": "Order", "to": "Profile", "type": "belongsTo", "name": "profile", "defined_in": "app/Models/Order.php:40" }
  ],
  "count": 1,
  "truncated": false
}
```

`outgoing` edges are the root's own relations — the same data `inspect-model` already shows. `incoming` edges are the actual new capability: every other discovered model with a relation pointing at the root, found by scanning the same data the [browser graph](/guide/relationship-graph) renders, not by re-reading every model's source. With `direction: "both"`, edges from both directions merge into one list, each tagged with its own `direction`.

## Configuration

The MCP server is configured under the `mcp` key of `config/model-explorer.php`:

```php
'mcp' => [
    'enabled' => env('MODEL_EXPLORER_MCP', true),

    'cache' => [
        'enabled' => env('MODEL_EXPLORER_MCP_CACHE', false),
    ],

    // Max enum cases `inspect-model` expands inline per column; 0 omits them.
    'enum_case_limit' => env('MODEL_EXPLORER_MCP_ENUM_CASES', 12),

    'allow_undiscovered' => env('MODEL_EXPLORER_MCP_ALLOW_UNDISCOVERED', false),
],
```

### Live by default

The tools **read live** by default — every call reflects the current state of your model code, so an agent never sees stale structure during active development. This is independent of the UI's [caching](/guide/configuration) option (`model-explorer.cache.enabled`); enabling that does **not** cache the MCP surface.

Set `MODEL_EXPLORER_MCP_CACHE=true` only if you want to trade freshness for speed on a very large model set:

```env
MODEL_EXPLORER_MCP_CACHE=true
```

### Enum-case verbosity

Enum-cast columns expand their cases inline (`cast:Status(Draft=draft, …)`) — high value for writing correct code, but a cost when an agent inspects many models at once. `mcp.enum_case_limit` caps how many cases each column expands; set it to `0` to omit enum cases entirely across the whole surface:

```env
MODEL_EXPLORER_MCP_ENUM_CASES=0
```

This is the deployment-wide default. An individual `inspect-model` call can still override it with the [`enum_case_limit`](#inspect-model) parameter — e.g. keep the default on but pass `0` for a broad, low-token survey.

### Inspecting vendor / undiscovered models

By default `inspect-model`, `model-source`, and `model-neighbors` only resolve models in your configured `model_paths`. Pass a valid model's FQCN that lives elsewhere — say a package's `Spatie\Mailcoach\…\Subscriber` — and the tool reports that no discovered model matches, with a hint.

Set `allow_undiscovered` to let those tools resolve **any** class that resolves to an Eloquent model, even outside `model_paths`:

```env
MODEL_EXPLORER_MCP_ALLOW_UNDISCOVERED=true
```

This only applies when the agent supplies a **fully-qualified** class name; short names and the `list-models` / `find-model` results stay bounded to the discovered set. Off by default. Note that `model-neighbors`'s `incoming` direction always scans only the discovered set regardless of this setting — an undiscovered root's `outgoing` relations resolve fine, but other undiscovered models pointing *at* it can't be found, since they were never scanned in the first place.

### Disabling the server

The server registers only when the package and its MCP feature are both enabled. Either of these turns it off:

```env
MODEL_EXPLORER_MCP=false      # disable just the MCP server
MODEL_EXPLORER_ENABLED=false  # disable the whole package (UI + MCP)
```

::: warning
These tools expose your application's model *structure and source* to the connected agent. They never read or return live database rows — only schema, relations, scopes, accessors, traits, members, and source snippets.
:::
