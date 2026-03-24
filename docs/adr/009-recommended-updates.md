# ADR-009: Recommended Updates Before Publishing

- **Status:** Proposed
- **Date:** 2026-03-24

## Context

A review of the package prior to public release identified a set of improvements that are not blocking correctness but would meaningfully improve usability, discoverability, and compatibility for external consumers. This ADR records them in priority order so they can be tracked and executed as discrete work items.

## Recommended Updates

### High Priority

#### README / installation docs
The package has no `README.md`. Consumers have no documented path for installation, gate configuration, or config options. A minimal README covering `composer require`, service-provider auto-discovery, gate setup, and the `model-explorer` config file is essential before any public release.

#### Composite primary key hint in record browser
Models that declare a composite primary key (multiple columns marked as the primary key) will 404 silently in the record browser when a lookup is attempted. `find()` with a single scalar key does not work for composite keys. The UI should detect composite-key models and display an explanatory message rather than a silent 404.

Detection approach: inspect `getKeyName()` on a fresh model instance — if it returns an array, the key is composite.

### Medium Priority

#### Dark / light mode auto-respect
The package hardcodes the `night` DaisyUI theme via `data-theme="night"` on the `<html>` element in `app.blade.php`. This looks jarring when a host application uses a light theme. The SPA should default to `prefers-color-scheme` (or no override) so it inherits the host's colour scheme, with an optional `theme` config key for explicit overrides.

#### "Reset view" button on relationship graph
The force-directed graph supports pan and zoom but provides no way to reset to the initial view once the user has drifted far from the origin. A "Reset view" button overlaid on the graph container should restore `pan` and `zoom` to their initial values.

#### Config: exclude specific model classes
The package currently supports only path-based exclusions via `model_paths`. Consumers frequently want to suppress noise from third-party models shipped by packages such as Telescope, Passport, or Horizon. An `excluded_models` config key accepting an array of fully-qualified class names (or wildcard namespace prefixes) would cover this without requiring consumers to manipulate `model_paths`.

#### FK column links in Columns table
When a column name follows the `{relation}_id` convention and the referenced model exists in the discovered set, the Columns table could render the column name as a link to that model's detail page. This would make the column/relation relationship visually navigable without the user needing to cross-reference the Relations section manually.

### Lower Priority

#### Keyboard shortcut for search
The model-list search input has no keyboard shortcut. A `/` or `Cmd+K` binding to focus the search field is a standard developer-tool convention that would improve navigation speed.

#### Model policy display
If a model has a bound Policy registered with Laravel's `Gate`, the detail page could show the policy class name (and optionally its defined methods). This surfaces authorization context that is otherwise invisible in the browser.

#### Package version in the UI
A small footer note showing the installed package version (read from `composer.json` or a generated constant) would help users file accurate bug reports and correlate behaviour with release notes.

## Consequences

None of the above items changes existing behaviour. Each is independently implementable and shippable. The recommended sequencing is: README first (required for any public release), composite-key hint second (prevents silent 404 confusion), then the remaining items in priority order.
