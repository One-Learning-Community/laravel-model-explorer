# ADR-010: GitHub Pages Documentation Site

- **Status:** Accepted
- **Date:** 2026-03-24

## Context

As the package approaches public release, a hosted documentation site is needed to:

- Provide a usage and features guide for consumers who find the package via Packagist or GitHub.
- Drive organic traffic and improve discoverability.
- Serve as a reference point for contributors.

The package already uses Vue 3 and Vite 6 for its embedded SPA. A documentation tool that shares this toolchain avoids introducing a second, unrelated build system and lets the same developer maintain both.

Options considered:

| Option | Notes |
|---|---|
| **VitePress** | Vue/Vite-native static site generator; official Vue ecosystem tool; minimal config; GitHub Pages deployment via one Actions workflow |
| Docusaurus | React-based; good for large projects; heavier than needed here |
| GitHub Wiki | No versioning, no custom theme, not deployable as a standalone site |
| Plain Markdown in `docs/` | No navigation, search, or structured layout |

## Decision

Adopt **VitePress** for the documentation site, hosted on **GitHub Pages** via a GitHub Actions workflow.

- Source lives in a `docs-site/` directory at the package root (separate from `docs/adr/` which holds ADRs and is not part of the public site).
- The site is built and deployed automatically on every push to `main` via `.github/workflows/docs.yml`.
- The GitHub Actions workflow runs `npm ci && npm run docs:build` and deploys the `docs-site/.vitepress/dist` output to the `gh-pages` branch using the official `peaceiris/actions-gh-pages` action (or the native `actions/deploy-pages` equivalent).
- VitePress is added as a `devDependency` in `package.json`; it does not affect the package's runtime or its consumers.
- The public URL will be the repository's default GitHub Pages URL (`https://{org}.github.io/{repo}/`) unless a custom domain is configured later.

## Consequences

**Positive:**
- Consistent toolchain — VitePress uses the same Vite pipeline already present in the repo; contributors only need one `npm ci`.
- Built-in full-text search (local), dark mode, and responsive layout with zero extra configuration.
- Automatic deploys on merge to `main` keep docs in sync with the codebase.
- GitHub Pages is free and requires no external hosting account.

**Negative:**
- `docs-site/` adds a second Markdown + build artefact concern to the repo; contributors must keep docs updated alongside code changes.
- VitePress SSG output (HTML + assets) is committed to / deployed from `gh-pages` branch, not the main branch — reviewers cannot preview doc changes in a PR without running the build locally or waiting for the deploy preview (if configured).
- Adding VitePress as a dev dependency slightly increases `npm ci` time in CI.
