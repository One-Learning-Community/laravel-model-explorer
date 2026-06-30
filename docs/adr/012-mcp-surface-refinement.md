# ADR-012: MCP Surface Refinement — Graph, Vendor Models, and a Members List

- **Status:** Accepted — A & B shipped; C (`list-members`) in progress
- **Date:** 2026-06-29

## Context

ADR-011 shipped the `model-explorer` MCP server with five tools. A real-world audit of the server against a large host application (~224 discovered models, 762 relation edges) exercised every tool and surfaced three gaps. This ADR records them and proposes a direction for each.

The audit also drove three fixes already shipped in **v0.3.1**, which this ADR builds on:

- **Relations no longer silently dropped** when a relation method throws on invoke. ADR-005's "false positive → silent skip" assumption turned out to also silently drop *real* relations whose constraint closure threw (e.g. a `whereHas` closure dereferencing the query Builder). `RelationFinder` now falls back to the declared/derived type + source-parsed related class instead of dropping.
- **Cache keys fingerprint source** (`SourceFingerprint`) so an opted-in cache invalidates on model edits.
- **Fresh-subprocess inspection** (`FreshModelInspector` + `model-explorer:inspect` worker) so the long-lived `mcp:start` process serves current structure after a model is edited, without a manual reconnect — since PHP cannot reload an already-loaded class.

With correctness addressed, the remaining issues are about **surface design**: one tool that does not scale, one scoping limit that reads as a missing feature, and one capability gap.

## Gaps found

### 1. `relationship-graph` does not scale

The tool returns the **entire** graph and nothing else. On the audited app that is 224 nodes + 762 edges ≈ **88 KB**, which exceeds the MCP client's per-result token budget — the result is unusable inline and has to be spilled to a file and `jq`'d. There is no way to scope it (no root model, no depth, no namespace or edge-type filter).

The *data* is sound and consistent with `inspect-model` (e.g. `Profile` reports 34 outgoing edges, matching its 34 relations, including `customMorphOne`, `cachedBelongsTo`, `hasManyDeep`, etc.). The problem is purely delivery: a whole-universe dump is the wrong shape for an agent, which asks scoped questions. In practice every "how do these connect?" question the audit posed was answered better by `find-model` (`relatesTo`/`hasColumn`) or `inspect-model` than by the graph.

### 2. Only configured `model_paths` are inspectable

`inspect-model` on a valid, existing vendor model FQCN (`Spatie\Mailcoach\…\Subscriber`) returns *"No discovered model matches."* The resolver only accepts members of the discovered set. For a third-party or otherwise un-scanned model an agent is pushed back to reading vendor source — the exact cost the server exists to remove. Reasonable as a default, but it reads as a missing capability when you assume "inspect any Eloquent model."

### 3. Structure only — no member-level surface ("skeleton, not behavior")

`inspect-model` exposes columns, relations, scopes, accessors, traits, mass-assignment, and policy — but never the model's *other* methods (business logic, lifecycle hooks like `booted()`, constants, plain properties). `model-source` can fetch a body, but only one member at a time and only for a `scope`/`relation`/`accessor` whose name you already know. There is no way to enumerate "what is actually defined on this class, and where does each piece come from." For "what does this model *do*?", the server still sends you to the file.

## Decision (proposed)

### A. Disable `relationship-graph` on the MCP surface now

Remove the `relationship-graph` **MCP tool** from the registered tool set in the next release — an outright disable, not a deprecate-now/remove-later cycle. It is broken at real scale today, so it should not ship on the agent surface in its current global form. Rationale:

- It cannot be delivered whole at real scale, and a truncated graph is misleading.
- No audited agent task needed it that `find-model`/`inspect-model` did not serve better.
- Keeping a tool that reliably overflows trains agents to avoid the server.
- A deprecated-but-present tool keeps the overflow footgun loaded for one more cycle and still trains agents to avoid the server. If it's broken, take it off the surface rather than label it.

`GraphBuilder` and the **HTTP/SPA graph endpoint stay** — the human-facing force-directed graph is a legitimate, interactive use of the full dataset (a human can pan/zoom 224 nodes; an agent cannot consume 88 KB). This decision is about the *agent* surface only.

The tool comes back **only** when it can be built conservatively/scoped, never as a global dump: a required `root` model plus a bounded `depth` (e.g. 1–2 hops), optional `namespace` / `edge_type` filters, and a hard node/edge cap with an explicit "truncated" flag. A neighborhood is consumable; the universe is not. Until that scoped version exists, the graph stays off the agent surface.

### B. Add a vendor/undiscovered-model escape hatch

Allow `inspect-model` / `model-source` to introspect a class **outside** `model_paths` when it is given a fully-qualified name that resolves to an existing `Illuminate\Database\Eloquent\Model` subclass. Gate it behind a **config** opt-in (`model-explorer.mcp.allow_undiscovered`, default `false`) so the default discovery scoping is preserved. This unblocks "what's in this package's model?" without widening `list-models`/`find-model`, which remain bounded to the discovered set.

Make this a **config** flag, not a per-call one. ADR-011 deliberately rejected a per-call `no_cache` flag because it pushes a judgement onto the agent; the surface default is where scoping choices belong. A per-call vendor flag is a more defensible intent signal than a staleness judgement, but keeping it config-level keeps the two surfaces consistent and the default honestly bounded.

`defined_in` pointers for a vendor model resolve into `vendor/…` — still a valid, openable pointer, and the honest answer for an un-scanned class.

### C. Add a `list-members` capability — every method and property, with provenance

This is the headline addition and the direct answer to gap #3. Expose a returnable list of **all** defined members of a model, each with its origin, so an agent can see the full surface and then `model-source` the parts it cares about — the pointers-not-bodies ethos of ADR-007 / ADR-011, extended from relations/scopes/accessors to the *whole* class.

Shape (illustrative; either a new `list-members` tool or a new `members` section on `inspect-model`):

```jsonc
{
  "methods": [
    { "name": "booted",          "visibility": "protected", "static": true,  "kind": "lifecycle",
      "defined_in": "app/Models/Order.php:42" },
    { "name": "markPaid",        "visibility": "public",    "params": "(Carbon $at)", "returns": "void",
      "kind": "business",        "defined_in": "app/Models/Order.php:88" },
    { "name": "scopeActive",     "visibility": "public",    "kind": "scope",
      "defined_in": "app/Models/Concerns/HasStatus.php:15" },
    { "name": "user",            "visibility": "public",    "returns": "BelongsTo", "kind": "relation",
      "defined_in": "app/Models/Order.php:60" }
  ],
  "properties": [
    { "name": "fillable", "visibility": "protected", "kind": "config",
      "defined_in": "app/Models/Order.php:20" },
    { "name": "MAX_ITEMS", "visibility": "public", "kind": "constant", "value": "50",
      "defined_in": "app/Models/Order.php:12" }
  ]
}
```

Notes:

- **Enumeration boundary (load-bearing).** List only members **defined on the model's own class and its first-party traits** — never the framework/vendor base. `ReflectionClass::getMethods()` / `getConstants()` on an Eloquent model surface *hundreds* of inherited `Illuminate\…` members (`save`, `delete`, `newQuery`, `getAttribute`, `CREATED_AT`, …); emitting those would reproduce the exact un-consumable dump that motivates retiring the graph (gap #1). Reuse the boundary that already exists: `ModelInspector::extractTraits()` filters via `excluded_trait_prefixes`, and `list-members` applies the same exclusion so only host-app-defined surface appears. Without this filter the tool is dead on arrival — it is part of the decision, not a tuning detail.
- **Provenance is the point** — and it has two halves. The `defined_in` **file:line** comes from reflection (`ReflectionMethod::getFileName()` already resolves to the trait file, not the using class). Determining a member's **origin class** (trait vs parent) for the `kind`/attribution must use the `ReflectionClass::getTraits()` walk, **not** `getDeclaringClass()`, which returns the *using* class when a trait is involved. This is the same convention the existing scope/relation/accessor attribution follows; `list-members` generalises it from three member kinds to the whole class.
- **`kind` is a heuristic classification** (relation / scope / accessor / lifecycle / business / config / constant), reusing existing detection where it exists and falling back to `method` / `property`. It is a hint, not a contract.
- **Bodies stay out** — names + signatures + pointers only; `model-source` fetches a body on demand. This keeps it token-economical even for large classes.
- Consider whether this **supersedes the per-kind `model-source` resolution restriction**: with a full member list, `model-source` could accept any member name (currently `scope`/`relation`/`accessor` only).

## Consequences

**Positive**

- Removes a tool that fails at scale; the agent surface stops emitting un-consumable output and the maintained surface is honest about what works.
- `list-members` closes the "skeleton, not behavior" gap that recurred throughout the audit — the single most-felt limitation — while preserving token economy (pointers, not bodies).
- The vendor escape hatch removes a sharp "valid input, flat refusal" edge without loosening the default scoping that keeps `list-models`/`find-model` meaningful.

**Negative / risks**

- Disabling `relationship-graph` is a (pre-1.0) breaking change to the tool set; the Boost guidelines and docs must drop it. Low blast radius given it was unusable at scale, but it is a published tool.
- `list-members` `kind` classification is heuristic; over-promising precision there would just reproduce the trust problem. Document it as best-effort.
- The vendor hatch can reach models that fail to instantiate or whose schema is absent; it must degrade the same way `inspect-model` already does (actionable error, never a fatal).

## Open questions

- `list-members`: new tool vs. a `members` section on `inspect-model`? A section keeps the tool count down but bloats the default unless gated behind `include`.
- Should `find-model` gain a `hasMethod` / `definesMember` filter once member enumeration exists (the structural analogue of `hasColumn`)?

## References

- ADR-005 — reflection + source scanning for relation/scope discovery (the silent-skip assumption refined in v0.3.1).
- ADR-007 — lazy accessor resolution (pointers-not-bodies ethos, extended here to all members).
- ADR-011 — the MCP server and its five-tool surface this ADR refines.
