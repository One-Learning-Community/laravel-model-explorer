# ADR-005: Reflection + Source Scanning for Relation/Scope Discovery

- **Status:** Accepted
- **Date:** 2026-03-20

## Context

Eloquent relations and local scopes are PHP methods. There is no central registry — the framework discovers them at runtime by invoking them. To introspect a model's relations without instantiating every method, we must infer which methods are relations from their signatures or bodies.

Typed return hints (`public function posts(): HasMany`) make this straightforward for modern code, but many real-world codebases predate PHP 7.4 return types or simply omit them (`public function posts()`). Limiting detection to typed methods would produce incomplete results.

Alternatives considered:

1. **Typed-return-only detection** — simple and reliable, but silently misses all untyped relations.
2. **Require explicit model annotation** — e.g., a `#[Relation]` attribute or docblock. Too invasive; requires changes in consumer models.
3. **Parse PhpDoc `@return` tags** — fragile; PHPDoc is inconsistently written and often omitted.
4. **Source scanning via regex** — read the method body and match calls to known Eloquent relation factory methods (`$this->hasMany(`, `$this->belongsTo(`, etc.).

## Decision

`RelationFinder` uses a two-pass approach:

**Pass 1 — Typed detection:** `ReflectionMethod::getReturnType()` is checked against a known list of Eloquent relation class FQCNs (`HasMany`, `BelongsTo`, `BelongsToMany`, `HasOne`, morphs, etc.). Methods with a matching return type are confirmed relations.

**Pass 2 — Source scanning:** Methods with no return type (or a non-relation return type) are passed to `SourceExtractor::forMethod()`, which reads the raw source lines for the method via `ReflectionMethod::getFileName()`/`getStartLine()`/`getEndLine()`. A regex then matches calls to `$this->hasMany(`, `$this->belongsTo(`, etc. within the body. A match flags the method as a candidate relation.

Confirmed candidates from both passes are invoked on a fresh model instance to obtain `getRelated()` class name, foreign key, and local key.

`SourceExtractor` returns `null` for eval'd or PHAR-sourced classes (no file-backed source available). Those methods are silently skipped.

The same `SourceExtractor` is also used to attach source snippets to scopes and accessors displayed in the UI.

## Consequences

**Positive:**
- Detects both typed and untyped relations; consumer models require no changes.
- Source extraction is also reused for snippet display (relation source, scope source, accessor source).

**Negative:**
- Source scanning uses regex — it can produce false positives on methods that call relation factory methods as helpers without returning them. In practice this is rare, but the discovered method is invoked (guarded by try/catch), so a false positive results in a silent skip rather than data corruption.
- PHAR-packaged and eval'd models produce no source and their untyped relations are not discoverable.
- `ReflectionClass::getDeclaringClass()` returns the *using* class, not the defining trait, when a method comes from a trait. Source attribution therefore uses a manual trait-walk: `ReflectionClass::getTraits()` is called at each level of the class hierarchy and checked first before falling back to the declaring class. This walk is used for both relation and scope `definedIn` attribution.
