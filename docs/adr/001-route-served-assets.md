# ADR-001: Route-Served Assets (No vendor:publish Required)

- **Status:** Accepted
- **Date:** 2026-03-20

## Context

Laravel packages that ship a UI typically serve their compiled frontend assets (JS, CSS, fonts) in one of two ways:

1. **Published assets** — the package ships a `vendor:publish` command that copies compiled files into the host application's `public/vendor/{package}/` directory. This is the approach taken by Telescope and Horizon.
2. **Route-served assets** — the package registers a dedicated HTTP route that reads files directly from within the package directory and streams them to the browser.

For published assets, consumers must either commit the published files to their own repository or add `public/vendor/model-explorer/` to `.gitignore`. Both paths add friction: committing binary build artefacts bloats the repository history; gitignoring them means the files must be reproduced as part of deployment (an extra setup step that is easy to forget).

Since Model Explorer is a developer-facing tool and not a production-critical service, raw throughput of static file delivery is not a meaningful concern.

## Decision

Assets are served via a dedicated HTTP route registered by the package service provider:

```
GET /_model-explorer/assets/{path}
```

The `AssetController` resolves the requested path relative to the package's own `public/` directory and streams the file with long-lived cache headers (`Cache-Control: public, max-age=31536000, immutable`). No `vendor:publish` step is required or exposed for assets.

The asset route:
- Is registered **before** the SPA catch-all route so it takes routing priority.
- Uses only the `web` middleware — it is intentionally **not** gated by the `Authorize` middleware, since assets contain no sensitive data and browsers do not reliably carry session state when fetching script/style resources.
- Protects against path traversal via `realpath()` comparison: the resolved path must remain within the package's `public/` directory.
- Restricts served extensions to an explicit allowlist (`js`, `css`, `woff`, `woff2`, `ttf`, `svg`, `png`, `ico`, `map`).

## Consequences

**Positive:**
- Zero setup beyond installing the package — no artisan commands, no gitignore changes, no deployment steps for assets.
- Host application repositories are not polluted with compiled vendor artefacts.
- Updating the package automatically delivers updated assets on the next request; there is no stale published copy to re-publish.

**Negative:**
- PHP serves static files rather than the web server (Nginx/Apache/Caddy). For a developer tool this is acceptable; it would be inappropriate for a production-facing UI.
- `Cache-Control: immutable` means a stale cached asset will be served until the browser's cache expires (up to 1 year). This is mitigated by the fact that asset filenames are determined by Vite's build output — breaking changes should produce new filenames via content hashing in future phases.
