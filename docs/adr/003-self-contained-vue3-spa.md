# ADR-003: Self-Contained Vue 3 SPA (No Host Application Frontend Dependency)

- **Status:** Accepted
- **Date:** 2026-03-20

## Context

Model Explorer's UI requires a reactive, component-driven frontend capable of rendering graph visualisations, navigable data trees, and live query interfaces in later phases. Several integration strategies were considered:

1. **Blade + Alpine.js** — lightweight, no build step for consumers, but Alpine is not guaranteed to be present in the host application and is insufficient for complex graph UI.
2. **Inertia.js** — natural fit for Laravel applications but requires the host app to have Inertia installed and configured. Incompatible with apps using a different frontend architecture.
3. **Livewire** — server-driven reactivity, but requires Livewire in the host app and does not suit client-side graph rendering well.
4. **Self-contained SPA (Vue 3 + Vite)** — the package maintains its own frontend toolchain, builds its own compiled assets, and delivers a standalone SPA that makes no assumptions about the host application's frontend stack.

The self-contained SPA pattern is used by Laravel Telescope, Laravel Horizon, and Filament Nova for the same reason: developer tools must work across the full spectrum of Laravel applications regardless of their chosen frontend approach.

## Decision

Model Explorer ships as a self-contained Vue 3 SPA. The package maintains its own `package.json` and `vite.config.js`. Compiled output goes to the package's `public/` directory and is served via ADR-001's route-based asset delivery.

**Isolation guarantees:**
- The host application's `package.json`, `vite.config.js`, and frontend toolchain are not touched.
- No Vue, Vite, or other frontend dependency is added to the host application.
- The SPA is mounted on `<div id="app">` inside a minimal Blade shell (`resources/views/app.blade.php`) that contains no host application layout, components, or styles.
- CSS is scoped to the SPA shell and will not leak into or inherit from the host application's styles.

**Vue 3 specifically** (over Vue 2) was chosen because:
- Vue 3 is the current stable release with active maintenance.
- The Composition API is better suited to complex stateful UI (graph navigation, query builder) that will be built in later phases.
- Vue 2 reached end-of-life in December 2023.

The host application in the current development environment uses Vue 2, but this is an implementation detail of that specific application and not a constraint the package should inherit.

**Frontend dependencies** are kept minimal for Phase 1:
- `vue@^3.5` — UI framework
- `@vitejs/plugin-vue` — Vite transform (dev dependency)
- `vite@^6` — build toolchain (dev dependency)

Vue Router and any graph or UI libraries will be introduced in the phases that require them.

## Consequences

**Positive:**
- Works in any Laravel application regardless of frontend stack (Inertia, Livewire, plain Blade, API-only, etc.).
- Package upgrades deliver UI improvements automatically — consumers do not need to re-run any frontend build.
- Frontend development of the package is self-contained: `npm run dev` in the package directory is sufficient.
- Full control over the UI library and styling choices without inheriting or conflicting with host application conventions.

**Negative:**
- A `npm run build` step is required as part of the package release process. Compiled assets (`public/app.js`, `public/app.css`) must be committed to the package repository so consumers receive them via Composer without needing Node.js themselves.
- The package ships two separate runtimes: a PHP library and a compiled JS bundle. Release discipline must ensure they are in sync.
- Consumers cannot easily customise or extend the UI without forking the package (by design — this is a read-only introspection tool, not a component library).
