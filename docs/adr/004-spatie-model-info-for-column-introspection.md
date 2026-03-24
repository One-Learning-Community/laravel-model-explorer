# ADR-004: `spatie/laravel-model-info` for Column Introspection

- **Status:** Accepted
- **Date:** 2026-03-20

## Context

The introspection engine needs to surface DB column data for every model: column names, types, nullability, defaults, and whether a column is a primary key or indexed. Implementing this from scratch requires querying the database's information schema (different SQL per driver — MySQL, PostgreSQL, SQLite all differ), handling Eloquent's column casting layer, and keeping pace with Laravel schema changes.

Alternatives considered:

1. **Raw information_schema queries** — direct SQL against the DB schema. Works, but requires per-driver implementations and produces raw DB types rather than Eloquent-aware types.
2. **`doctrine/dbal`** — a well-known DBAL abstraction. Laravel itself uses it for schema builder operations, but it is a heavy dependency and the API is not Eloquent-aware.
3. **`spatie/laravel-model-info` v2** — a purpose-built Laravel package maintained by Spatie that reflects model attributes, casts, fillable state, and relation stubs using Eloquent's own mechanisms. It normalises across DB drivers and integrates with Laravel's schema builder.

## Decision

Depend on `spatie/laravel-model-info` v2 for all DB column data.

`ModelInspector::inspect()` calls `ModelInfo::forModel($className)` to obtain the base set of attributes (column name, type, nullable, default, cast, fillable, hidden, appended). This data populates `ModelData::$attributes`.

For relation data, Spatie's output is intentionally not used — it provides basic type/related class information but lacks source-trait attribution, PHPDoc snippets, and parameter metadata. Instead, `RelationFinder` overlays richer relation metadata on top (see ADR-005).

## Consequences

**Positive:**
- No raw schema-query code in this package; multi-driver support is handled by Spatie.
- Attribute data (type, cast, fillable, hidden, appended) comes from Eloquent's own layer — it reflects what the application code actually sees, not just what the DB schema says.
- Actively maintained by Spatie with Laravel major-version compatibility.

**Negative:**
- External runtime dependency. If `spatie/laravel-model-info` drops Laravel 12+ support, the package must either vendor a fork or replace the integration.
- `RelationData` partially duplicates Spatie's relation stubs. The overlap is intentional — the richer metadata this package provides cannot be derived from Spatie's output — but it means two sources of truth for relation type and related class that must stay in sync.
