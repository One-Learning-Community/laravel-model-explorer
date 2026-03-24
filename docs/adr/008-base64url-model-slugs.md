# ADR-008: Base64url Encoding for Model Class Name Slugs

- **Status:** Accepted
- **Date:** 2026-03-20

## Context

API routes and Vue Router paths must embed a model identifier in the URL, e.g. `/models/{model}`. The natural identifier is the model's fully-qualified class name (`App\Models\Post`), but backslashes are not legal in URL path segments without percent-encoding. Percent-encoding backslashes produces `App%5CModels%5CPost`, which is technically valid but creates double-encoding risks when passed through Laravel's router, and is awkward to construct consistently in both PHP and JavaScript.

Alternatives considered:

1. **Replace `\` with a safe delimiter** (e.g., `.` or `-`) — fragile if class names contain the chosen delimiter; requires a decode convention that is easy to get wrong with namespaced classes.
2. **Integer IDs from a model registry** — assign sequential IDs to discovered models. Requires shared state between requests; IDs change when models are added or removed; complicates deep-linking.
3. **Short name only** — use `basename` of the class (e.g., `Post`). Collides when two models in different namespaces share a short name.
4. **Base64url encoding** — encode the full FQCN. Always produces a URL-safe string; round-trips perfectly; no collision risk; no shared state needed.

## Decision

Model class names are base64url-encoded wherever they appear in API paths or Vue Router routes. The encoding is standard base64 with padding stripped and URL-unsafe characters substituted:

**JavaScript (encoding):**
```js
btoa(className).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '')
```

**PHP (decoding):**
```php
base64_decode(strtr($slug, '-_', '+/'))
```

This pair is applied consistently in all three API controllers (`ModelsController`, `RecordsController`, `GraphController`) and in Vue Router navigation helpers.

## Consequences

**Positive:**
- URL-safe for any valid PHP class name without per-character special-casing.
- Round-trips identically in both PHP and JavaScript — no normalisation edge cases.
- No server-side state required; any request carrying a valid slug can be decoded independently.

**Negative:**
- Slugs are opaque: `/models/QXBwXE1vZGVsc1xQb3N0` is not human-readable. Deep-links cannot be hand-constructed without knowing the encoding.
- The encoding pair (JS encode / PHP decode) must be kept in sync across both languages. A discrepancy causes silent failures (404 or wrong model) rather than obvious errors. Any change to the encoding scheme breaks all existing bookmarked or shared URLs.
