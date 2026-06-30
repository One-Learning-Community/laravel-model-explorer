# ADR-013: Scoped Relation-Neighborhood Query (`model-neighbors`)

- **Status:** Accepted — shipped
- **Date:** 2026-06-30

## Context

ADR-012 retired the whole-graph `relationship-graph` MCP tool: it could only return the entire graph (224 nodes / 762 edges ≈ 88 KB on the audited app), which overflows an agent's response budget. That ADR explicitly sanctioned a future, narrower return:

> The tool comes back only when it can be built conservatively/scoped, never as a global dump: a required `root` model plus a bounded `depth` (e.g. 1–2 hops), optional `namespace` / `edge_type` filters, and a hard node/edge cap with an explicit "truncated" flag.

A follow-up audit of the remaining surface (`model-source` kind-lifting, `inspect-model` member filtering, `find-model`'s `definesMember` — all ADR-012 follow-ups, shipped 2026-06-30) named one more gap: **blast radius**. None of the four tools answer "what breaks if I change this model?" — specifically, "which *other* models have a relation pointing at this one?" `inspect-model` shows a model's own outgoing relations; nothing shows incoming ones. A source grep for the class name finds string literals, not trait-composed relation declarations, and misses the relation *type* and *method name* entirely.

This is the bounded slice of the original graph idea that's actually worth shipping: `GraphBuilder` already computes every edge for the HTTP graph endpoint (ADR-012 didn't remove that, only the MCP tool that dumped it). The gap isn't *computing* the neighborhood — it's *exposing a query* over data the package already has, instead of forcing the agent to read every model's relations and reconstruct the inverse itself.

## Decision

### A new tool, not a revived `relationship-graph` or a `find-model` filter

Ship **`model-neighbors`**, a fifth MCP tool, rather than either alternative considered:

- **Reviving `relationship-graph` under its old name** was rejected: that name implies whole-graph, force-directed semantics this tool doesn't have. At depth 1, the result is "edges touching one model," not a graph — a distinct name avoids reintroducing the expectation ADR-012 explicitly closed off.
- **A `find-model` filter** (e.g. `pointsAt`) was rejected: `find-model`'s filters return one row per *matching model* with a one-line `matched` label, deduplicated to the first hit per filter. The neighborhood query needs one row per *edge* (a model can have multiple relations into the root), each carrying its own type, name, and `defined_in` pointer — a structurally different output shape that doesn't fit `find-model`'s existing contract without changing it for every other filter too.

### Shape: `root` + `direction` + `depth` (validated to 1) + `limit` + `truncated`

| Param | Required | Default | Notes |
|---|---|---|---|
| `model` | yes | — | Root model, resolved via the existing `ModelResolver` (short name/FQCN, honors `mcp.allow_undiscovered` — consistent with `inspect-model`/`model-source`, not `find-model`'s discovered-set-only scoping, since this is a single-model lookup rather than a cross-model scan) |
| `direction` | no | `incoming` | `incoming` \| `outgoing` \| `both` |
| `depth` | no | `1` | Only `1` is accepted; any other value errors with an actionable message |
| `limit` | no | `50` | Caps edges returned; excess sets `truncated: true` |

**`direction` defaults to `incoming`, not `both`.** Outgoing relations are already fully exposed by `inspect-model`'s `relations` section — defaulting to `both` would make the common case ("what points at this?") pay for data the agent likely already has from a prior `inspect-model` call. `outgoing` and `both` remain available for completeness (e.g. when the agent hasn't called `inspect-model` first, or wants both directions in one round trip).

**`limit` is a per-call parameter, not a config knob** — a deliberate deviation from this project's usual MCP defaults (`mcp.cache.enabled`, `mcp.allow_undiscovered`), which are deployment-wide judgement calls the surface owner makes once. The right cap for "how many incoming edges do I want to see" varies per query, not per deployment, so it belongs on the call, not in `config/model-explorer.php`.

**`depth` is exposed now but only `1` validates.** This reserves the parameter for future multi-hop traversal (e.g. `Order` → `LineItem` → `Product`) without a breaking schema change later, while being honest that multi-hop isn't implemented yet — the smallest version of the ADR-012-sanctioned shape that ships today.

### Output

Each edge carries a `defined_in` pointer, same as every other relation surface in this package:

```jsonc
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

When `direction=both`, edges from both directions merge into one list, each tagged with its own `direction` — there's no value in two separate arrays for a single round trip.

### Implementation: extend `GraphBuilder`, don't duplicate it

`GraphBuilder::build()` already inspects every discovered model to compute the relations array consumed by the HTTP `/graph` endpoint — exactly the data an incoming-edge scan needs. Its per-relation entries previously carried only `name`/`type`/`related`, discarding the `RelationData` snippet (file + start line) needed for a `defined_in` pointer. `model-neighbors` needed that pointer without a second inspection pass per matching model, so `GraphBuilder::build()` now threads `snippet` through unchanged. This is additive: the two existing consumers (`GraphBuilderTest`, the HTTP `/graph` endpoint and its `GraphApiTest`) assert specific keys, not exact array equality, so neither broke.

`model-neighbors` computes:
- **outgoing** edges from the root's own already-inspected `ModelData->relations` (same path `inspect-model` uses, cached the same way under `mcp.inspect.{class}.{mtime}`).
- **incoming** edges by scanning `GraphBuilder::build()`'s output (cached under `mcp.graph.{fingerprint}`, the same key the retired `relationship-graph` tool and the HTTP endpoint already used) for any relation whose `related` FQCN matches the root, excluding the root's own entry.

No new computation path — both directions reuse services this package already had.

## Consequences

**Positive**

- Closes the highest-value gap from the post-ADR-012 audit ("blast radius") without resurrecting the whole-graph overflow problem: a bounded result with an explicit `truncated` signal instead of either silence or a 88 KB dump.
- Reuses `GraphBuilder` and the existing per-model inspection/caching paths entirely — no new expensive computation, only a query over data already computed for the HTTP graph endpoint.
- `depth` ships now as a stable, documented parameter even though only `1` validates, so a future multi-hop implementation doesn't need a breaking schema change.

**Negative / risks**

- `incoming` direction still costs an O(n) scan over every discovered model's relations (same cost `GraphBuilder::build()` always paid for the HTTP graph) — fine at the audited scale (224 models) but doesn't avoid the underlying per-model inspection cost the way a precomputed inverse index would. Not addressed here; `mcp.cache.enabled` already mitigates repeated calls the same way it does for `find-model`.
- A model with no first-party relations pointing at it (a true leaf) returns an empty `edges` array rather than an error — intentional (consistent with `find-model` returning `count: 0` rather than erroring on no matches), but worth noting since it differs from `inspect-model`/`model-source`'s "not found" error shape for genuinely unresolvable input.

## References

- ADR-005 — reflection + source scanning for relation discovery (the data `GraphBuilder` and `model-neighbors` both consume).
- ADR-011 — the MCP server and its original five-tool surface.
- ADR-012 — retired the whole-graph tool and explicitly sanctioned the scoped return this ADR ships; also the source of the `model-source`/`inspect-model`/`find-model` follow-ups whose audit named the blast-radius gap this ADR closes.
