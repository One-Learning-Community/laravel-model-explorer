# ADR-011: MCP Server for AI Model Introspection

- **Status:** Accepted — implementation pending
- **Date:** 2026-06-29

## Context

The package already introspects Eloquent models for a **human** audience: a Vue SPA backed by a JSON API surfaces columns, relations, scopes, accessors, and traits. The same introspection is valuable to an **AI** coding agent, which today has to read and pattern-match model source files (and the traits they pull in) to answer questions like "what relations does `Order` have?" or "which models use `SoftDeletes`?". That source scanning is token-expensive, error-prone for trait-provided members, and redundant with work this package already does.

[Laravel Boost](https://laravel.com/docs/13.x/boost) is the de-facto way to give agents Laravel-specific context. It ships an MCP (Model Context Protocol) server exposing tools such as *Application Info* and *Database Schema*. We investigated whether this package could contribute its own live tools **into** Boost's MCP server.

**Finding:** Boost's third-party extension surface is **text only** — packages may ship AI *guidelines* (`resources/boost/guidelines/core.blade.php`) and *skills* (`resources/boost/skills/{name}/SKILL.md`), both auto-loaded on `boost:install`. There is **no** hook for a package to register live MCP *tools* into the `laravel-boost` server; that is the open feature proposal [laravel/boost#522](https://github.com/laravel/boost/issues/522), not a shipped capability.

However, Boost is built on top of [`laravel/mcp`](https://laravel.com/docs/13.x/mcp), and that package lets *any* Laravel package register its own MCP server (`Mcp::local('name', Server::class)`) with `Tool` classes. Such a server appears in the user's MCP client as a **separate entry alongside** `laravel-boost`.

### Alternatives considered (delivery mechanism)

1. **Register tools into Boost's server** — not supported today (see above).
2. **Ship our own `laravel/mcp` local server** — supported and idiomatic; a dedicated `model-explorer` server sits next to `laravel-boost`. Works whether or not Boost is installed.
3. **AI-friendly Artisan commands** — no MCP; the agent shells out to `artisan` and parses stdout. Zero new runtime deps, but the agent has to know to do it and there is no tool schema to guide it.
4. **Boost guidelines / skills only** — text that points the agent at the existing HTTP API or new commands. No live, schema-described tools.

### Alternatives considered (record data)

A live `find-record` tool (lookup by PK/unique field, drilling relations/accessors via `withinSafeRead()`) was evaluated and **rejected for this iteration**. It would expose real database rows to the agent and require extracting the record-read logic out of `RecordsController`. The introspection goal is fully served by static structure + source metadata, so all five tools below are pure metadata and source — **no live DB rows**.

## Decision

Ship a **dedicated `laravel/mcp` local MCP server** (`model-explorer`) exposing five model-introspection tools, **plus Boost guidelines** so Boost-driven agents discover it. This delivers live, schema-described introspection that works with or without Boost, while using the only Boost-native package hook that actually exists.

### Delivery & registration

- `laravel/mcp` is added to `require` (the package is itself a developer tool; this makes the server work out of the box).
- A `ModelExplorerServer` (extends `Laravel\Mcp\Server`) is registered as a **local stdio server** via `Mcp::local('model-explorer', ModelExplorerServer::class)` from the package service provider (the package's own `routes/ai.php`).
- Registration is **conditional**: the server registers only when `model-explorer.enabled` **and** `model-explorer.mcp.enabled` are both true.
- Users wire it into their AI client once:

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

- `resources/boost/guidelines/core.blade.php` is shipped so that, when a consumer runs `boost:install`, the agent is told the `model-explorer` server exists, what each tool does, and when to prefer it over reading source.

### Tool surface

All tools are thin adapters: they resolve input, call the **existing** services (`ModelDiscovery`, `ModelInspector`, `ExplorerCache`), and pass results to a single `CompactPresenter` that emits AI-optimized JSON. No introspection logic lives in the tools.

| Tool | Params | Returns |
|---|---|---|
| `list-models` | — | every discovered model: `{ class, name, table }` (resilient — a broken model is skipped, never fails the call) |
| `inspect-model` | `model` (FQCN or short name, required); `include[]` (optional) | overview header + section **counts** (always); each requested section; `defined_in: "path:line"` on every scope/relation/accessor |
| `relationship-graph` | — | `{ nodes: [{class, name, table}], edges: [{from, to, type, name}] }` |
| `find-model` | `trait`, `extends`, `relatesTo`, `hasColumn` (all optional, **AND**-combined) | matching model summaries, each with a `matched` note (e.g. `"relatesTo: belongsTo Team"`) |
| `model-source` | `model` (FQCN/short); `kind` (`scope`\|`relation`\|`accessor`); `name` | one dedented, **trait-correct** snippet + `doc_summary` + `defined_in` |

**Input resolution.** `model` accepts a fully-qualified class name *or* a short class name (`"App\\Models\\Order"` or `"Order"`), resolved against the discovered set. This deliberately diverges from the HTTP API's base64url slugs (ADR-008) because an agent naturally types a class name. An ambiguous short name returns an error listing the candidate FQCNs.

**`inspect-model` sections.** `include[]` accepts any of `columns`, `relations`, `scopes`, `accessors`, `traits`, `mass-assignment` (fillable/guarded/hidden), `policy`, or `all`. Default (no `include`) = `columns` + `relations`. The overview header — class, table, key — and the per-section **counts** are emitted on every call, so even a minimal response self-advertises the deeper layers worth requesting. `ModelData.attributes` (from Spatie's `AttributeFinder`) is split by the presenter: non-virtual entries become `columns`, virtual entries become `accessors`.

**`find-model` errors** when no filter is supplied (it would otherwise just duplicate `list-models`).

**`model-source`** echoes the lazy-resolution ethos of ADR-007: `inspect-model` stays lean by shipping only `defined_in` pointers, and the full source is one precise call away when wanted. It draws from the snippets already produced by `SourceExtractor` (dedented, docblock-aware, trait-attributed) and cached in `ModelData`. A `name` that does not match returns an error listing the available names of that `kind`.

### Output format

AI-optimized **compact JSON** via `CompactPresenter` — structured (the agent can extract fields) but far leaner than the UI DTOs. Illustrative `inspect-model "Order"` (default `include`):

```jsonc
{
  "class": "App\\Models\\Order", "table": "orders", "key": "id",
  "counts": { "columns": 8, "relations": 3, "scopes": 2, "accessors": 1, "traits": 2 },
  "columns": [
    "id: bigint PK",
    "user_id: bigint FK→User",
    "status: string cast:OrderStatus",
    "total_cents: integer",
    "created_at: timestamp nullable"
  ],
  "relations": [
    "user: belongsTo User via user_id",
    "items: hasMany OrderItem via order_id",
    "coupon: belongsTo Coupon via coupon_id nullable"
  ]
}
```

Other sections render comparably terse: `scopes` → `"active(bool $only = true)"` with `defined_in`; `accessors` → `"full_name: string (accessor)"` with `defined_in`; `mass-assignment` → `{ fillable, guarded, hidden }`; `policy` → FQCN or `null`.

### Location pointers

Every scope, relation, and accessor carries `defined_in: "relative/path.php:line"`, sourced from `SourceExtractor`'s **trait-aware** declaring-class resolution. This is the key edge over a naive source scan: a scope that actually lives in `app/Models/Concerns/HasStatus.php` points *there*, not at the model file an agent would otherwise open. Pointers are always present (a few tokens); the snippet itself is fetched on demand via `model-source`.

### Reused & extracted code

- `relationship-graph` and `GraphController` share a new `Services/GraphBuilder` extracted from the controller's currently-inlined mapping. The controller is refactored to delegate to it — **the only change to existing behavior**, covered by a regression test.
- `CompactPresenter` is new (the existing `serialize`/`summarize` shapes are UI-oriented and stay as they are).

### Configuration & safety

A new config block (existing structure untouched):

```php
'mcp' => [
    'enabled' => env('MODEL_EXPLORER_MCP', true),

    // MCP tools read live by default (bypassing ExplorerCache) so an agent
    // never reasons on stale structure during active development. Opt in only
    // if you accept staleness for speed on a very large model set.
    'cache' => [
        'enabled' => env('MODEL_EXPLORER_MCP_CACHE', false),
    ],
],
```

- The server is a **local stdio server** — it runs as `php artisan mcp:start model-explorer` inside the developer's own shell, the same trust boundary as `tinker`. The HTTP `viewModelExplorer` gate (ADR-002) is request-scoped and does not apply; the server is gated only by `enabled` **and** `mcp.enabled`.
- `model_paths` and `excluded_models` are honored automatically — everything flows through `ModelDiscovery`.
- **No live DB rows** are ever returned; all tools are structure, schema, and source metadata.

### Caching: live-by-default for the agent surface

`ExplorerCache` was designed for the **human HTTP/SPA surface**, where its invalidation gaps are tolerable because a developer can see and refresh a stale view. Those gaps make it actively dangerous for an **agent**, which cannot tell a result is stale and will reason on wrong facts:

| Tool | Cache key | Self-invalidates on | Stale when |
|---|---|---|---|
| `inspect-model` | `models.show.{slug}.{filemtime}` | editing the model file | editing a **trait** it uses, a **migration/schema** change, a related model |
| `list-models` | `models.index` (no mtime, no TTL) | nothing | any model added/removed |
| `relationship-graph` | `graph` (no mtime) | nothing | any relation added/changed |
| `find-model` | per-model inspections | model-file edits only | the `inspect-model` gaps, ×N |

The MCP scenario *is* active development: you add a relation or a migration, the agent calls a tool, and silently receives yesterday's structure. A stale answer is worse than a slow correct one.

Therefore the MCP tools **bypass `ExplorerCache` and read live by default**, independent of `model-explorer.cache.enabled` (which continues to govern only the HTTP surface). A separate opt-in, `model-explorer.mcp.cache.enabled` (default `false`), lets a user with a very large model set knowingly trade freshness for speed — chiefly to amortize `find-model`'s per-model inspection. There is deliberately **no per-call `no_cache` flag**: that pushes a staleness judgement onto the agent, which has no way to make it well; correctness belongs in the surface default, not the call site.

### Architecture / file manifest

```
src/Mcp/
  ModelExplorerServer.php          # extends Laravel\Mcp\Server; $tools = [ ... ]
  Tools/
    ListModelsTool.php
    InspectModelTool.php
    RelationshipGraphTool.php
    FindModelTool.php
    ModelSourceTool.php
  Support/
    CompactPresenter.php           # ModelData → lean arrays (overview/columns/relations/scopes/accessors/…)
src/Services/
  GraphBuilder.php                 # extracted from GraphController; used by controller AND tool
routes/ai.php                      # Mcp::local('model-explorer', ModelExplorerServer::class)
config/model-explorer.php          # + `mcp` block
resources/boost/guidelines/
  core.blade.php                   # advertises the server + tools to Boost-installed agents
```

## Consequences

**Positive:**
- Agents get live, schema-described model introspection without scanning source; trait-provided scopes/relations/accessors are correctly attributed, which a raw scan misses.
- Works whether or not Boost is installed; Boost users additionally get auto-advertisement via guidelines.
- Tools stay trivial — all logic remains in the existing, already-tested services. `GraphBuilder` extraction removes a duplication that would otherwise appear.
- Token-economical: compact JSON, opt-in `inspect-model` sections, pointers-not-snippets by default.
- Correct-by-default for active development: the agent surface reads live, so it never silently serves stale structure while you are editing models, traits, or migrations.

**Negative:**
- `laravel/mcp` becomes a hard dependency; consumers who only want the web UI pull one extra package. Mitigated by `mcp.enabled` (disable registration) — a future split to `suggest` + conditional `class_exists` registration remains open if the weight proves unwelcome.
- A second divergence from the HTTP API's conventions: tools resolve **class names**, not base64url slugs (ADR-008). Intentional, for agent ergonomics, but maintainers should not assume slug parity across the two surfaces.
- Consumers must manually add the server to their MCP client config (one-time); there is no equivalent of `boost:install` auto-wiring for non-Boost clients.

## Testing

Pest feature tests against the workbench models, in the existing `it('...')` style:

- Each tool invoked through the server, asserting the compact shape.
- `inspect-model`: default sections vs `include:[all]` vs a single section; counts header always present; `defined_in` populated for a trait-provided member.
- `find-model`: each filter individually, an AND combination, and the no-filter error.
- `model-source`: a known definition returns the trait-correct snippet; an unknown `name` lists available names.
- `relationship-graph`: node/edge topology for a known relation.
- Kill switches: `enabled = false` and `mcp.enabled = false` each suppress registration.
- Caching: with `cache.enabled = true` (HTTP cache on) the tools still read live; with `mcp.cache.enabled = true` the tools go through `ExplorerCache`.
- Regression: `GraphController` output is unchanged after the `GraphBuilder` extraction.
