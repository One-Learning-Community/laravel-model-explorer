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
If you use [Laravel Boost](https://laravel.com/docs/boost), `boost:install` automatically advertises these tools to your agent — the package ships Boost guidelines describing each one. No manual client config needed.
:::

## The tools

The server exposes four tools. Every tool returns compact, structured JSON; each scope, relation, accessor, and member carries a `defined_in` pointer in `path:line` form (relative to your application root) so the agent can jump straight to the definition.

| Tool | Purpose |
|---|---|
| [`list-models`](#list-models) | List every discovered model |
| [`inspect-model`](#inspect-model) | One model's full structure |
| [`find-model`](#find-model) | Find models by structural criteria |
| [`model-source`](#model-source) | Fetch one definition's source snippet |

::: tip Looking for the relationship graph?
Earlier versions exposed a `relationship-graph` tool that returned the entire graph. It was removed because a whole-application graph overflows an agent's response budget on real codebases — scoped questions are better answered by `find-model` (`relatesTo`) and `inspect-model`. The force-directed graph remains available in the [browser UI](/guide/relationship-graph). See ADR-012.
:::

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

Returns one model's structure: an overview with section counts, then the sections you ask for.

**Input**

| Parameter | Description |
|---|---|
| `model` | FQCN or short class name (required) |
| `include` | Sections to return: `columns`, `relations`, `scopes`, `accessors`, `traits`, `mass-assignment`, `policy`, `members`, or `all`. Defaults to `columns` + `relations`. The `members` section can be narrowed — see below. |

**Output** (default sections)

```json
{
  "class": "App\\Models\\Post",
  "name": "Post",
  "table": "posts",
  "key": "id",
  "counts": { "columns": 6, "relations": 3, "scopes": 2, "accessors": 1, "traits": 2 },
  "columns": [
    "id: integer PK",
    "author_id: integer FK→User",
    "title: string",
    "published_at: datetime nullable cast:datetime"
  ],
  "relations": [
    { "name": "author", "type": "belongsTo", "related": "User", "via": "author_id", "defined_in": "app/Models/Concerns/HasAuthor.php:9" }
  ]
}
```

Columns are rendered as terse strings annotated with `PK`, `FK→{Model}`, `unique`, `nullable`, and `cast:{Type}`.

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

## Configuration

The MCP server is configured under the `mcp` key of `config/model-explorer.php`:

```php
'mcp' => [
    'enabled' => env('MODEL_EXPLORER_MCP', true),

    'cache' => [
        'enabled' => env('MODEL_EXPLORER_MCP_CACHE', false),
    ],

    'allow_undiscovered' => env('MODEL_EXPLORER_MCP_ALLOW_UNDISCOVERED', false),
],
```

### Live by default

The tools **read live** by default — every call reflects the current state of your model code, so an agent never sees stale structure during active development. This is independent of the UI's [caching](/guide/configuration) option (`model-explorer.cache.enabled`); enabling that does **not** cache the MCP surface.

Set `MODEL_EXPLORER_MCP_CACHE=true` only if you want to trade freshness for speed on a very large model set:

```env
MODEL_EXPLORER_MCP_CACHE=true
```

### Inspecting vendor / undiscovered models

By default `inspect-model` and `model-source` only resolve models in your configured `model_paths`. Pass a valid model's FQCN that lives elsewhere — say a package's `Spatie\Mailcoach\…\Subscriber` — and the tool reports that no discovered model matches, with a hint.

Set `allow_undiscovered` to let those tools introspect **any** class that resolves to an Eloquent model, even outside `model_paths`:

```env
MODEL_EXPLORER_MCP_ALLOW_UNDISCOVERED=true
```

This only applies when the agent supplies a **fully-qualified** class name; short names and the `list-models` / `find-model` results stay bounded to the discovered set. Off by default.

### Disabling the server

The server registers only when the package and its MCP feature are both enabled. Either of these turns it off:

```env
MODEL_EXPLORER_MCP=false      # disable just the MCP server
MODEL_EXPLORER_ENABLED=false  # disable the whole package (UI + MCP)
```

::: warning
These tools expose your application's model *structure and source* to the connected agent. They never read or return live database rows — only schema, relations, scopes, accessors, traits, members, and source snippets.
:::
