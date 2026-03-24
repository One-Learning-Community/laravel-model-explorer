# ADR-006: `withinSafeRead()` for Live Record Browsing

- **Status:** Accepted
- **Date:** 2026-03-20

## Context

`RecordsController` fetches live Eloquent records from the host application's database. Eloquent models in production applications routinely carry side effects: model observers that fire on `retrieved` events, computed properties that update timestamps, accessors that lazily persist derived values, or trait boot methods that register global listeners.

A developer tool that inadvertently triggers writes, queued jobs, or cache invalidation in production while a developer is browsing records would be unacceptable. The controller must provide a hard guarantee that its database reads are side-effect-free.

Alternatives considered:

1. **Documentation-only** — document that consumers should not attach observers with write side effects. Unreliable; the tool must be safe by default regardless of consumer code.
2. **`Model::withoutEvents()`** — suppresses model event observers for the duration of a callback. Does not prevent writes from accessors or mutators that call `save()` directly.
3. **DB transaction + rollback** — wrapping reads in a transaction that is always rolled back ensures that even if a write is issued, it is never committed.
4. **Both `withoutEvents()` + rolled-back transaction** — defence in depth: events suppressed at the Eloquent layer; any writes that slip through are discarded at the DB layer.

## Decision

All DB work in `RecordsController` is wrapped in `withinSafeRead(callable $callback): mixed`. The implementation:

1. Calls `Model::withoutEvents()` to suppress Eloquent model event listeners.
2. Inside that, opens a `DB::transaction()`.
3. Executes the callback.
4. Always throws a sentinel `RollbackException` carrying the result — the transaction is never committed.
5. The exception is caught in `withinSafeRead()`, which returns the carried result.

This means even if accessor code calls `$this->save()` or a trait fires a queued job that issues a write, neither the Eloquent event dispatch nor the DB write will persist.

## Consequences

**Positive:**
- Hard guarantee against accidental writes: safe to use against any model regardless of observer or accessor complexity.
- Transparent to callers — `withinSafeRead()` returns the callback's result normally.

**Negative:**
- Model events that legitimately fire during reads (audit logging `retrieved`, read-tracking, last-accessed timestamps) are suppressed. This is the correct trade-off for a developer tool but should be noted for consumers who rely on those patterns.
- Slight per-request overhead from the transaction wrapper (negligible for a developer tool).
- The sentinel-exception rollback pattern is non-obvious; future maintainers should read this ADR before modifying `withinSafeRead()`.
