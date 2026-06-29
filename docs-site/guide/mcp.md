# AI Model Introspection (MCP)

Model Explorer ships a local [`laravel/mcp`](https://laravel.com/docs/mcp) server, **`model-explorer`**, that lets AI coding agents introspect your Eloquent models the same way the browser UI does — columns, relationships, scopes, accessors, traits, and source snippets — **without scanning your source files**.

Because the tools reuse the package's own inspection services, an agent sees what a source scan misses: trait-provided relations and scopes, database columns, casts, and correctly-attributed source locations.

::: tip Why an MCP server?
A grep over your `app/Models` directory can't tell an agent that a relation is defined in a trait, what a column's real database type is, or which scope accepts which parameters. These tools answer those questions directly and live, so the agent never reasons on stale structure mid-development.
:::

## Requirements

- The `model-explorer` server is registered only when both `enabled` and `mcp.enabled` are true (see [Configuration](#configuration)).
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

The server exposes five tools. Every tool returns compact, structured JSON; each scope, relation, and accessor carries a `defined_in` pointer in `path:line` form (relative to your application root) so the agent can jump straight to the definition.

| Tool | Purpose |
|---|---|
| [`list-models`](#list-models) | List every discovered model |
| [`inspect-model`](#inspect-model) | One model's full structure |
| [`relationship-graph`](#relationship-graph) | All models as nodes + edges |
| [`find-model`](#find-model) | Find models by structural criteria |
| [`model-source`](#model-source) | Fetch one definition's source snippet |

Models are referenced by **fully-qualified class name or short class name** (`App\Models\Order` or `Order`) — not the base64url slugs the HTTP API uses.

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
| `include` | Sections to return: `columns`, `relations`, `scopes`, `accessors`, `traits`, `mass-assignment`, `policy`, or `all`. Defaults to `columns` + `relations`. |

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

### `relationship-graph`

Returns the whole relationship graph as nodes and edges — the same data behind the browser's [Relationship Graph](/guide/relationship-graph), shaped for an agent.

```json
{
  "nodes": [
    { "class": "App\\Models\\Post", "name": "Post", "table": "posts" }
  ],
  "edges": [
    { "from": "Post", "to": "User", "type": "belongsTo", "name": "author" }
  ]
}
```

### `find-model`

Finds models matching structural criteria — answering cross-cutting questions without inspecting every model. Provide at least one filter; filters combine with **AND**.

**Input**

| Filter | Matches models that… |
|---|---|
| `trait` | use the given trait (short name or FQCN) |
| `extends` | extend the given parent class |
| `relatesTo` | have a relation pointing at the given model |
| `hasColumn` | have the given column in their table |

**Output** — each match lists which filters it satisfied:

```json
{
  "models": [
    { "class": "App\\Models\\Post", "name": "Post", "table": "posts", "matched": ["trait: App\\Models\\Concerns\\HasAuthor"] }
  ],
  "count": 1
}
```

Use it for questions like *"which models use `SoftDeletes`"* or *"which models belong to `Team`"*.

### `model-source`

Returns the dedented, **trait-correct** source for one `scope`, `relation`, or `accessor`. Use the `defined_in` pointers from `inspect-model` to decide what to fetch.

**Input**

| Parameter | Description |
|---|---|
| `model` | FQCN or short class name (required) |
| `kind` | One of `scope`, `relation`, `accessor` (required) |
| `name` | The definition name, e.g. scope `recent`, relation `author` (required) |

**Output**

```json
{
  "code": "public function author(): BelongsTo\n{\n    return $this->belongsTo(User::class, 'author_id');\n}",
  "defined_in": "app/Models/Concerns/HasAuthor.php:9"
}
```

If the name doesn't exist for that kind, the error lists the available names so the agent can correct itself.

## Configuration

The MCP server is configured under the `mcp` key of `config/model-explorer.php`:

```php
'mcp' => [
    'enabled' => env('MODEL_EXPLORER_MCP', true),

    'cache' => [
        'enabled' => env('MODEL_EXPLORER_MCP_CACHE', false),
    ],
],
```

### Live by default

The tools **read live** by default — every call reflects the current state of your model code, so an agent never sees stale structure during active development. This is independent of the UI's [caching](/guide/configuration) option (`model-explorer.cache.enabled`); enabling that does **not** cache the MCP surface.

Set `MODEL_EXPLORER_MCP_CACHE=true` only if you want to trade freshness for speed on a very large model set:

```env
MODEL_EXPLORER_MCP_CACHE=true
```

### Disabling the server

The server registers only when the package and its MCP feature are both enabled. Either of these turns it off:

```env
MODEL_EXPLORER_MCP=false      # disable just the MCP server
MODEL_EXPLORER_ENABLED=false  # disable the whole package (UI + MCP)
```

::: warning
These tools expose your application's model *structure and source* to the connected agent. They never read or return live database rows — only schema, relations, scopes, accessors, traits, and source snippets.
:::
