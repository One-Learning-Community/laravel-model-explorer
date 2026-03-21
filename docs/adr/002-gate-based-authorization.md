# ADR-002: Gate-Based Authorization with Environment-Aware Default

- **Status:** Accepted
- **Date:** 2026-03-20

## Context

Model Explorer exposes internal application structure — model definitions, table schemas, column types, relationships, and eventually live data. Accidentally exposing this to unauthenticated users in a production environment would be a serious security incident.

Authorization must therefore be:
- **Secure by default** — requiring no configuration to be safe in production.
- **Explicit** — access in non-local environments must be a deliberate opt-in, not an oversight.
- **Flexible** — teams should be able to plug in their own access logic (role checks, IP allowlists, etc.) without forking the package.

Several patterns were considered:

1. **Environment check only** — allow access only when `APP_ENV=local`. Simple, but too rigid: staging environments and shared development servers legitimately need access.
2. **Config flag** — a boolean `enabled` in `config/model-explorer.php`. Provides an emergency kill switch but not fine-grained access control.
3. **Middleware stack** — expose a configurable middleware array. Flexible, but requires consumers to understand and wire up custom middleware classes.
4. **Laravel Gate** — register a named gate (`viewModelExplorer`) with a default implementation. Consumers override it in their `AuthServiceProvider` using Laravel's standard idiom.

## Decision

Authorization is implemented via a Laravel Gate named `viewModelExplorer`.

The package service provider registers a **default gate** in `packageBooted()`:

```php
Gate::define('viewModelExplorer', function ($user = null): bool {
    return app()->environment('local');
});
```

This default:
- Allows all access (authenticated or not) when `APP_ENV=local`.
- Denies all access in every other environment.

Because `App\Providers\AuthServiceProvider::boot()` runs after package service providers, any consumer-defined override of the gate will replace the default:

```php
// In the host application's AuthServiceProvider
Gate::define('viewModelExplorer', function (User $user): bool {
    return $user->hasRole('developer');
});
```

A separate `enabled` config key acts as a hard kill switch — returning 404 regardless of gate outcome. This allows teams to completely disable the tool via environment variable (`MODEL_EXPLORER_ENABLED=false`) without touching authorization logic.

### Middleware parameter requirement

Laravel's `Gate` short-circuits to `false` for unauthenticated requests when a gate callback's first parameter does not accept `null`. Any gate override for this package **must** declare `$user = null` (or `?User $user`) to permit unauthenticated access:

```php
// Correct — allows guests through to the gate logic
Gate::define('viewModelExplorer', fn (?User $user) => true);

// Incorrect — Laravel will deny unauthenticated requests before calling this
Gate::define('viewModelExplorer', fn (User $user) => true);
```

This behaviour is enforced by the package's test suite and documented here for consumers who override the gate.

## Consequences

**Positive:**
- Secure by default: no configuration required to be safe in production.
- Consumers use the Laravel Gate API they already know — no package-specific concepts.
- The kill switch (`enabled = false`) provides a fast, zero-logic escape hatch.
- The gate approach naturally supports any access logic: role checks, IP allowlists, team membership, etc.

**Negative:**
- The `$user = null` requirement for guest access is a non-obvious Laravel behaviour that will surprise consumers who write a gate without it. Mitigated by documentation and the test suite.
- Boot order dependency (package SP before app SP) is implicit. If a consumer registers their override in a provider that loads before the package, the package's default will overwrite it. Standard Laravel provider ordering makes this unlikely but worth being aware of.
