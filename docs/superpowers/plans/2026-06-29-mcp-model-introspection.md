# MCP Model Introspection Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship a `laravel/mcp` local MCP server (`model-explorer`) exposing five AI model-introspection tools, plus Boost guidelines, so an AI agent can introspect Eloquent models without scanning source.

**Architecture:** Five `Tool` classes are thin adapters over the existing `ModelDiscovery` / `ModelInspector` services; a single `CompactPresenter` maps `ModelData` to AI-optimized JSON returned via `Response::structured()`. A `ModelResolver` turns an FQCN-or-short-name into a class. The relationship-graph mapping is extracted from `GraphController` into a reusable `GraphBuilder`. The agent surface reads live by default (bypassing `ExplorerCache`), with an opt-in `mcp.cache.enabled` escape hatch.

**Tech Stack:** PHP 8.3+, Laravel 11–13, `laravel/mcp`, `spatie/laravel-model-info`, Pest 4.

## Global Constraints

- PHP `^8.3`; Laravel `^11||^12||^13` — every new class must work across all three.
- Tests use **Pest** in `it('...', function () {})` style. Run with `./vendor/bin/pest`.
- All tool output is returned via `Laravel\Mcp\Response::structured(array)` (compact JSON). Errors via `Response::error(string)`.
- **MCP reads live by default**: tools bypass `ExplorerCache` unless `model-explorer.mcp.cache.enabled` (default `false`) is true. Never gate MCP caching on `model-explorer.cache.enabled`.
- Model inputs resolve by **FQCN or short class name** (NOT the base64url slugs the HTTP API uses).
- Location pointers are the snippet's file made **relative to `base_path()`** plus `:start_line`, e.g. `app/Models/Concerns/HasAuthor.php:9`.
- Trait-aware source attribution from `SourceExtractor` must be preserved end-to-end (do not re-derive declaring class).
- Namespace root: `OneLearningCommunity\LaravelModelExplorer\`. New MCP code lives under `src/Mcp/`.
- `public/` is committed; no frontend changes in this plan.

---

### Task 1: Extract `GraphBuilder` service (refactor `GraphController`)

**Files:**
- Create: `src/Services/GraphBuilder.php`
- Modify: `src/Http/Controllers/Api/GraphController.php`
- Test: `tests/Feature/Services/GraphBuilderTest.php`, `tests/Feature/Api/GraphApiTest.php` (existing — must still pass)

**Interfaces:**
- Produces: `GraphBuilder::build(): array` — returns the exact array the controller previously built inline: a list of `['class' => string, 'short_name' => string, 'table' => string, 'relations' => list<array{name:string,type:string,related:string}>]`, with un-inspectable models filtered out. **Uncached** (the controller wraps it in cache).

- [ ] **Step 1: Write the failing test**

```php
<?php

use OneLearningCommunity\LaravelModelExplorer\Services\GraphBuilder;

it('builds a node per inspectable model with its relations', function () {
    $graph = app(GraphBuilder::class)->build();

    $post = collect($graph)->firstWhere('class', \Workbench\App\Models\Post::class);

    expect($post)->not->toBeNull()
        ->and($post['short_name'])->toBe('Post')
        ->and($post['table'])->toBe('posts')
        ->and(collect($post['relations'])->pluck('name'))->toContain('user', 'author');
});

it('omits models that cannot be inspected', function () {
    $graph = app(GraphBuilder::class)->build();

    expect(collect($graph)->pluck('class'))
        ->not->toContain(\Workbench\App\Models\BrokenModel::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Services/GraphBuilderTest.php`
Expected: FAIL — `Class "OneLearningCommunity\LaravelModelExplorer\Services\GraphBuilder" not found`.

- [ ] **Step 3: Create `GraphBuilder` with the controller's inlined logic**

```php
<?php

namespace OneLearningCommunity\LaravelModelExplorer\Services;

use OneLearningCommunity\LaravelModelExplorer\Data\RelationData;

class GraphBuilder
{
    public function __construct(
        private readonly ModelDiscovery $discovery,
        private readonly ModelInspector $inspector,
    ) {}

    /**
     * Build the raw relationship-graph payload: one entry per inspectable model.
     * Un-inspectable models are skipped so a single broken model never breaks the graph.
     *
     * @return list<array{class:string, short_name:string, table:string, relations:list<array{name:string,type:string,related:string}>}>
     */
    public function build(): array
    {
        return collect($this->discovery->discoverAll())
            ->map(function (string $className): ?array {
                try {
                    $data = $this->inspector->inspect($className);
                } catch (\RuntimeException) {
                    return null;
                }

                return [
                    'class' => $data->className,
                    'short_name' => $data->shortName,
                    'table' => $data->table,
                    'relations' => $data->relations->map(fn (RelationData $rel) => [
                        'name' => $rel->name,
                        'type' => $rel->type,
                        'related' => $rel->related,
                    ])->values()->all(),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
```

- [ ] **Step 4: Refactor `GraphController` to delegate to `GraphBuilder`**

Replace the whole controller body with:

```php
<?php

namespace OneLearningCommunity\LaravelModelExplorer\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use OneLearningCommunity\LaravelModelExplorer\Services\ExplorerCache;
use OneLearningCommunity\LaravelModelExplorer\Services\GraphBuilder;

class GraphController
{
    public function __construct(
        private readonly GraphBuilder $builder,
        private readonly ExplorerCache $cache,
    ) {}

    /**
     * Returns all models with their relationships in a single payload for graph rendering.
     */
    public function __invoke(): JsonResponse
    {
        $models = $this->cache->remember('graph', fn () => $this->builder->build());

        return response()->json($models);
    }
}
```

- [ ] **Step 5: Run tests (new + existing graph regression)**

Run: `./vendor/bin/pest tests/Feature/Services/GraphBuilderTest.php tests/Feature/Api/GraphApiTest.php`
Expected: PASS. The existing `GraphApiTest` confirms the controller output is byte-identical after extraction.

- [ ] **Step 6: Commit**

```bash
git add src/Services/GraphBuilder.php src/Http/Controllers/Api/GraphController.php tests/Feature/Services/GraphBuilderTest.php
git commit -m "refactor: extract GraphBuilder from GraphController"
```

---

### Task 2: Add `laravel/mcp` dependency, `mcp` config, and `ExplorerCache::rememberWhen()`

**Files:**
- Modify: `composer.json` (add `laravel/mcp` to `require`)
- Modify: `config/model-explorer.php` (add `mcp` block)
- Modify: `src/Services/ExplorerCache.php`
- Test: `tests/Feature/Services/ExplorerCacheTest.php` (create if absent)

**Interfaces:**
- Produces: `ExplorerCache::rememberWhen(bool $condition, string $key, \Closure $callback): mixed` — caches through the existing versioned-namespace machinery when `$condition` is true (regardless of `cache.enabled`); otherwise invokes the callback live.
- Produces config keys: `model-explorer.mcp.enabled` (default `true`), `model-explorer.mcp.cache.enabled` (default `false`).

- [ ] **Step 1: Add `laravel/mcp` to composer and install**

Run:
```bash
composer require laravel/mcp
```
Expected: `laravel/mcp` added under `require` in `composer.json` and installed into `vendor/`.

- [ ] **Step 2: Add the `mcp` config block**

In `config/model-explorer.php`, immediately before the closing `];`, insert:

```php
    /*
    |--------------------------------------------------------------------------
    | MCP Server
    |--------------------------------------------------------------------------
    | A local laravel/mcp server ("model-explorer") exposes model-introspection
    | tools to AI agents. It registers only when both `enabled` and `mcp.enabled`
    | are true. Wire it into your AI client with:
    |
    |   { "mcpServers": { "model-explorer": {
    |       "command": "php", "args": ["artisan", "mcp:start", "model-explorer"] } } }
    |
    | The tools read live by default so an agent never reasons on stale structure
    | during active development. Enable `mcp.cache.enabled` only if you accept
    | staleness for speed on a very large model set.
    */
    'mcp' => [
        'enabled' => env('MODEL_EXPLORER_MCP', true),

        'cache' => [
            'enabled' => env('MODEL_EXPLORER_MCP_CACHE', false),
        ],
    ],
```

- [ ] **Step 3: Write the failing test**

```php
<?php

use OneLearningCommunity\LaravelModelExplorer\Services\ExplorerCache;

it('rememberWhen caches when the condition is true regardless of cache.enabled', function () {
    config(['model-explorer.cache.enabled' => false]);
    $cache = app(ExplorerCache::class);

    $calls = 0;
    $make = fn () => $cache->rememberWhen(true, 'rw-test', function () use (&$calls) {
        $calls++;

        return 'value';
    });

    expect($make())->toBe('value')
        ->and($make())->toBe('value')
        ->and($calls)->toBe(1);
});

it('rememberWhen runs live every call when the condition is false', function () {
    $cache = app(ExplorerCache::class);

    $calls = 0;
    $make = fn () => $cache->rememberWhen(false, 'rw-live', function () use (&$calls) {
        $calls++;

        return 'v';
    });

    $make();
    $make();

    expect($calls)->toBe(2);
});
```

- [ ] **Step 4: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Services/ExplorerCacheTest.php`
Expected: FAIL — `Call to undefined method ...ExplorerCache::rememberWhen()`.

- [ ] **Step 5: Refactor `ExplorerCache` to add `rememberWhen` and share the caching path**

Replace the `remember()` method and add `rememberWhen()` + a private `cacheThrough()`:

```php
    /**
     * Return the cached value for $key, computing and storing it on a miss.
     * When caching is disabled the callback is simply invoked each time.
     */
    public function remember(string $key, Closure $callback): mixed
    {
        return $this->rememberWhen($this->enabled(), $key, $callback);
    }

    /**
     * Cache through the versioned namespace when $condition is true (independent of
     * the global cache.enabled flag); otherwise invoke the callback live. Used by the
     * MCP surface, which reads live by default but may opt in via mcp.cache.enabled.
     */
    public function rememberWhen(bool $condition, string $key, Closure $callback): mixed
    {
        if (! $condition) {
            return $callback();
        }

        return $this->cacheThrough($key, $callback);
    }

    private function cacheThrough(string $key, Closure $callback): mixed
    {
        $store = $this->store();
        $namespacedKey = $this->prefix($store).$key;
        $ttl = config('model-explorer.cache.ttl');

        return $ttl
            ? $store->remember($namespacedKey, (int) $ttl, $callback)
            : $store->rememberForever($namespacedKey, $callback);
    }
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `./vendor/bin/pest tests/Feature/Services/ExplorerCacheTest.php`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add composer.json composer.lock config/model-explorer.php src/Services/ExplorerCache.php tests/Feature/Services/ExplorerCacheTest.php
git commit -m "feat: add laravel/mcp dep, mcp config block, ExplorerCache::rememberWhen"
```

---

### Task 3: `ModelResolver` (FQCN / short-name resolution)

**Files:**
- Create: `src/Mcp/Support/ModelResolver.php`
- Test: `tests/Feature/Mcp/ModelResolverTest.php`

**Interfaces:**
- Consumes: `ModelDiscovery::discoverAll(): array` (list of FQCN strings).
- Produces: `ModelResolver::resolve(string $input): string` — returns the FQCN. Throws `\RuntimeException` with an AI-actionable message when the input is unknown, or ambiguous (multiple discovered classes share the short name).

- [ ] **Step 1: Write the failing test**

```php
<?php

use OneLearningCommunity\LaravelModelExplorer\Mcp\Support\ModelResolver;

it('resolves a fully-qualified class name', function () {
    expect(app(ModelResolver::class)->resolve(\Workbench\App\Models\Post::class))
        ->toBe(\Workbench\App\Models\Post::class);
});

it('resolves a bare short class name', function () {
    expect(app(ModelResolver::class)->resolve('Post'))
        ->toBe(\Workbench\App\Models\Post::class);
});

it('ignores a leading backslash', function () {
    expect(app(ModelResolver::class)->resolve('\\'.\Workbench\App\Models\User::class))
        ->toBe(\Workbench\App\Models\User::class);
});

it('throws an actionable error for an unknown model', function () {
    app(ModelResolver::class)->resolve('Nope');
})->throws(RuntimeException::class, 'No discovered model');
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Mcp/ModelResolverTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement `ModelResolver`**

```php
<?php

namespace OneLearningCommunity\LaravelModelExplorer\Mcp\Support;

use OneLearningCommunity\LaravelModelExplorer\Services\ModelDiscovery;

class ModelResolver
{
    public function __construct(private readonly ModelDiscovery $discovery) {}

    /**
     * Resolve an FQCN or short class name against the discovered model set.
     *
     * @return class-string
     *
     * @throws \RuntimeException When unknown or ambiguous.
     */
    public function resolve(string $input): string
    {
        $needle = ltrim(trim($input), '\\');
        $discovered = $this->discovery->discoverAll();

        foreach ($discovered as $class) {
            if (strcasecmp($class, $needle) === 0) {
                return $class;
            }
        }

        $matches = array_values(array_filter(
            $discovered,
            fn (string $class) => strcasecmp(class_basename($class), $needle) === 0,
        ));

        if (count($matches) === 1) {
            return $matches[0];
        }

        if (count($matches) > 1) {
            throw new \RuntimeException(
                "Ambiguous model name [{$input}]. Use a fully-qualified class name; candidates: ".implode(', ', $matches).'.'
            );
        }

        throw new \RuntimeException("No discovered model matches [{$input}].");
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Mcp/ModelResolverTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Mcp/Support/ModelResolver.php tests/Feature/Mcp/ModelResolverTest.php
git commit -m "feat: add ModelResolver for MCP model-name resolution"
```

---

### Task 4: `CompactPresenter` (ModelData → AI-optimized JSON)

**Files:**
- Create: `src/Mcp/Support/CompactPresenter.php`
- Test: `tests/Feature/Mcp/CompactPresenterTest.php`

**Interfaces:**
- Consumes: `ModelInspector::inspect(string): ModelData`; the `ModelData` / `RelationData` / `ScopeData` / `Spatie\ModelInfo\Attributes\Attribute` shapes.
- Produces (all used by later tasks):
  - `summary(ModelData): array{class,name,table}`
  - `overview(ModelData): array` — `class,name,table,key,counts`
  - `inspect(ModelData, list<string> $sections): array` — overview + requested sections
  - `columns(ModelData): list<string>`
  - `relations(ModelData): list<array>`
  - `scopes(ModelData): list<array>`
  - `accessors(ModelData): list<array>`
  - `traits(ModelData): list<string>`
  - `massAssignment(ModelData): array{fillable,guarded,hidden}`
  - `policy(ModelData): ?string`
  - `graph(array $builderOutput): array{nodes,edges}`
  - `pointer(?array $snippet): ?string` — `relative/path.php:line` or null
  - `const SECTIONS` — `['columns','relations','scopes','accessors','traits','mass-assignment','policy']`

- [ ] **Step 1: Write the failing test**

```php
<?php

use OneLearningCommunity\LaravelModelExplorer\Mcp\Support\CompactPresenter;
use OneLearningCommunity\LaravelModelExplorer\Services\ModelInspector;

function presentPost(): array
{
    $data = app(ModelInspector::class)->inspect(\Workbench\App\Models\Post::class);

    return [app(CompactPresenter::class), $data];
}

it('overview carries class, table, key and section counts', function () {
    [$p, $data] = presentPost();
    $overview = $p->overview($data);

    expect($overview['name'])->toBe('Post')
        ->and($overview['table'])->toBe('posts')
        ->and($overview['counts'])->toHaveKeys(['columns', 'relations', 'scopes', 'accessors', 'traits'])
        ->and($overview['counts']['relations'])->toBeGreaterThanOrEqual(3);
});

it('renders columns as terse strings with PK and FK annotations', function () {
    [$p, $data] = presentPost();
    $columns = $p->columns($data);

    expect(collect($columns)->first(fn ($c) => str_starts_with($c, 'id:')))->toContain('PK')
        ->and(collect($columns)->contains(fn ($c) => str_contains($c, 'FK→User')))->toBeTrue();
});

it('renders relations with type, related, via and a defined_in pointer', function () {
    [$p, $data] = presentPost();
    $author = collect($p->relations($data))->firstWhere('name', 'author');

    expect($author['type'])->toBe('belongsTo')
        ->and($author['related'])->toBe('User')
        ->and($author['via'])->toBe('author_id')
        ->and($author['defined_in'])->toContain('HasAuthor.php:');
});

it('renders scope signatures with parameters and trait-correct pointers', function () {
    [$p, $data] = presentPost();
    $published = collect($p->scopes($data))->firstWhere('name', 'published');
    $recent = collect($p->scopes($data))->firstWhere('name', 'recent');

    expect($published['defined_in'])->toContain('HasPublishedState.php:')
        ->and($recent['signature'])->toBe('recent(int $days = 30, bool $published = true)');
});

it('inspect() returns overview plus only the requested sections', function () {
    [$p, $data] = presentPost();
    $out = $p->inspect($data, ['columns']);

    expect($out)->toHaveKeys(['class', 'counts', 'columns'])
        ->and($out)->not->toHaveKey('relations')
        ->and($out)->not->toHaveKey('scopes');
});

it('pointer renders paths relative to base_path', function () {
    [$p, $data] = presentPost();
    $pointer = $p->pointer(['file' => base_path('app/Models/Foo.php'), 'start_line' => 12]);

    expect($pointer)->toBe('app/Models/Foo.php:12');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Mcp/CompactPresenterTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement `CompactPresenter`**

```php
<?php

namespace OneLearningCommunity\LaravelModelExplorer\Mcp\Support;

use OneLearningCommunity\LaravelModelExplorer\Data\ModelData;
use OneLearningCommunity\LaravelModelExplorer\Data\RelationData;
use OneLearningCommunity\LaravelModelExplorer\Data\ScopeData;
use Spatie\ModelInfo\Attributes\Attribute;

class CompactPresenter
{
    /** @var list<string> */
    public const SECTIONS = ['columns', 'relations', 'scopes', 'accessors', 'traits', 'mass-assignment', 'policy'];

    /**
     * @return array{class:string, name:string, table:string}
     */
    public function summary(ModelData $data): array
    {
        return [
            'class' => $data->className,
            'name' => $data->shortName,
            'table' => $data->table,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function overview(ModelData $data): array
    {
        return [
            'class' => $data->className,
            'name' => $data->shortName,
            'table' => $data->table,
            'key' => $data->keyName,
            'counts' => [
                'columns' => $data->attributes->reject(fn (Attribute $a) => $a->virtual)->count(),
                'relations' => $data->relations->count(),
                'scopes' => $data->scopes->count(),
                'accessors' => $data->attributes->filter(fn (Attribute $a) => $a->virtual)->count(),
                'traits' => count($data->traits),
            ],
        ];
    }

    /**
     * @param  list<string>  $sections
     * @return array<string, mixed>
     */
    public function inspect(ModelData $data, array $sections): array
    {
        $out = $this->overview($data);

        $builders = [
            'columns' => fn () => $this->columns($data),
            'relations' => fn () => $this->relations($data),
            'scopes' => fn () => $this->scopes($data),
            'accessors' => fn () => $this->accessors($data),
            'traits' => fn () => $this->traits($data),
            'mass-assignment' => fn () => $this->massAssignment($data),
            'policy' => fn () => $this->policy($data),
        ];

        foreach ($sections as $section) {
            if (isset($builders[$section])) {
                $out[$section] = ($builders[$section])();
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    public function columns(ModelData $data): array
    {
        $fkMap = $this->foreignKeyMap($data);

        return $data->attributes
            ->reject(fn (Attribute $a) => $a->virtual)
            ->map(function (Attribute $a) use ($fkMap): string {
                $parts = [$a->type ?? $a->phpType ?? 'mixed'];

                if ($a->primary) {
                    $parts[] = 'PK';
                }
                if (isset($fkMap[$a->name])) {
                    $parts[] = 'FK→'.$fkMap[$a->name];
                }
                if ($a->unique) {
                    $parts[] = 'unique';
                }
                if ($a->nullable) {
                    $parts[] = 'nullable';
                }
                if ($a->cast) {
                    $parts[] = 'cast:'.class_basename($a->cast);
                }

                return $a->name.': '.implode(' ', $parts);
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function relations(ModelData $data): array
    {
        return $data->relations->map(fn (RelationData $r) => array_filter([
            'name' => $r->name,
            'type' => lcfirst($r->type),
            'related' => class_basename($r->related),
            'via' => $r->foreignKey,
            'defined_in' => $this->pointer($r->snippet),
        ], fn ($v) => $v !== null && $v !== ''))->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function scopes(ModelData $data): array
    {
        return $data->scopes->map(fn (ScopeData $s) => array_filter([
            'name' => $s->name,
            'signature' => $this->scopeSignature($s),
            'doc' => $s->description,
            'defined_in' => $this->pointer($s->snippet),
        ], fn ($v) => $v !== null))->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function accessors(ModelData $data): array
    {
        return $data->attributes
            ->filter(fn (Attribute $a) => $a->virtual)
            ->map(function (Attribute $a) use ($data) {
                $snippet = $data->accessorSnippets[$a->name] ?? null;

                return array_filter([
                    'name' => $a->name,
                    'type' => $a->phpType,
                    'defined_in' => $this->pointer($snippet),
                ], fn ($v) => $v !== null);
            })
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function traits(ModelData $data): array
    {
        return $data->traits;
    }

    /**
     * @return array{fillable:list<string>, guarded:list<string>, hidden:list<string>}
     */
    public function massAssignment(ModelData $data): array
    {
        return [
            'fillable' => $data->fillable,
            'guarded' => $data->guarded,
            'hidden' => $data->hidden,
        ];
    }

    public function policy(ModelData $data): ?string
    {
        return $data->policyClass;
    }

    /**
     * @param  list<array{class:string, short_name:string, table:string, relations:list<array{name:string,type:string,related:string}>}>  $builderOutput
     * @return array{nodes:list<array>, edges:list<array>}
     */
    public function graph(array $builderOutput): array
    {
        $nodes = [];
        $edges = [];

        foreach ($builderOutput as $model) {
            $nodes[] = [
                'class' => $model['class'],
                'name' => $model['short_name'],
                'table' => $model['table'],
            ];

            foreach ($model['relations'] as $rel) {
                $edges[] = [
                    'from' => $model['short_name'],
                    'to' => class_basename($rel['related']),
                    'type' => lcfirst($rel['type']),
                    'name' => $rel['name'],
                ];
            }
        }

        return ['nodes' => $nodes, 'edges' => $edges];
    }

    /**
     * Format a snippet's location as a base_path-relative `path:line` pointer.
     *
     * @param  array{file?:string, start_line?:int}|null  $snippet
     */
    public function pointer(?array $snippet): ?string
    {
        if (! $snippet || empty($snippet['file'])) {
            return null;
        }

        $file = $snippet['file'];
        $base = base_path().DIRECTORY_SEPARATOR;
        $relative = str_starts_with($file, $base) ? substr($file, strlen($base)) : $file;

        return $relative.':'.($snippet['start_line'] ?? 0);
    }

    /**
     * @return array<string, string>  foreign-key column name => related short class name
     */
    private function foreignKeyMap(ModelData $data): array
    {
        $map = [];

        foreach ($data->relations as $rel) {
            if (in_array($rel->type, ['BelongsTo', 'MorphTo'], true) && $rel->foreignKey) {
                $map[$rel->foreignKey] = class_basename($rel->related);
            }
        }

        return $map;
    }

    private function scopeSignature(ScopeData $scope): string
    {
        $params = array_map(function (array $p): string {
            $type = $p['type'] ? $p['type'].' ' : '';
            $default = $p['has_default'] ? ' = '.$p['default'] : '';

            return $type.'$'.$p['name'].$default;
        }, $scope->parameters);

        return $scope->name.'('.implode(', ', $params).')';
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Mcp/CompactPresenterTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Mcp/Support/CompactPresenter.php tests/Feature/Mcp/CompactPresenterTest.php
git commit -m "feat: add CompactPresenter for AI-optimized model JSON"
```

---

### Task 5: `ModelExplorerServer` + `ListModelsTool`

**Files:**
- Create: `src/Mcp/ModelExplorerServer.php`
- Create: `src/Mcp/Tools/ListModelsTool.php`
- Test: `tests/Feature/Mcp/ListModelsToolTest.php`

**Interfaces:**
- Consumes: `ModelDiscovery`, `ExplorerCache::rememberWhen`.
- Produces: `ModelExplorerServer` with `$tools` listing all five tool classes (later tasks add their classes to this array); `ListModelsTool` returning `Response::structured(['models' => list<array{class,name,table}>, 'count' => int])`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use OneLearningCommunity\LaravelModelExplorer\Mcp\ModelExplorerServer;
use OneLearningCommunity\LaravelModelExplorer\Mcp\Tools\ListModelsTool;

it('lists discovered models with class, name and table', function () {
    $response = ModelExplorerServer::tool(ListModelsTool::class, []);

    $response->assertOk()
        ->assertSee('Post')
        ->assertSee('posts')
        ->assertSee(addslashes(\Workbench\App\Models\User::class));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Mcp/ListModelsToolTest.php`
Expected: FAIL — classes not found.

- [ ] **Step 3: Create the server**

```php
<?php

namespace OneLearningCommunity\LaravelModelExplorer\Mcp;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use OneLearningCommunity\LaravelModelExplorer\Mcp\Tools\FindModelTool;
use OneLearningCommunity\LaravelModelExplorer\Mcp\Tools\InspectModelTool;
use OneLearningCommunity\LaravelModelExplorer\Mcp\Tools\ListModelsTool;
use OneLearningCommunity\LaravelModelExplorer\Mcp\Tools\ModelSourceTool;
use OneLearningCommunity\LaravelModelExplorer\Mcp\Tools\RelationshipGraphTool;

#[Name('Model Explorer')]
#[Version('1.0.0')]
#[Instructions('Introspect the application\'s Eloquent models: list them, inspect one model\'s columns/relations/scopes/accessors, view the relationship graph, find models by structural criteria, and fetch source snippets. Prefer these tools over reading model source files.')]
class ModelExplorerServer extends Server
{
    /**
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        ListModelsTool::class,
        InspectModelTool::class,
        RelationshipGraphTool::class,
        FindModelTool::class,
        ModelSourceTool::class,
    ];
}
```

> Note: the four not-yet-created tool classes are referenced here so the server is complete; they are implemented in Tasks 6–9. If you run the full suite before those exist, this file will error — run only this task's test file until Task 9, or create empty stub classes. (Subagent-driven execution implements tasks in order, so the stubs are unnecessary.)

- [ ] **Step 4: Create `ListModelsTool`**

```php
<?php

namespace OneLearningCommunity\LaravelModelExplorer\Mcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use OneLearningCommunity\LaravelModelExplorer\Services\ExplorerCache;
use OneLearningCommunity\LaravelModelExplorer\Services\ModelDiscovery;

#[Description('List all discovered Eloquent models with their fully-qualified class, short name, and table. Call this first to see what models exist before inspecting one.')]
class ListModelsTool extends Tool
{
    public function __construct(
        private readonly ModelDiscovery $discovery,
        private readonly ExplorerCache $cache,
    ) {}

    public function handle(Request $request): Response
    {
        $useCache = (bool) config('model-explorer.mcp.cache.enabled', false);

        $models = $this->cache->rememberWhen($useCache, 'mcp.list', function (): array {
            return collect($this->discovery->discoverAll())
                ->map(function (string $className): ?array {
                    try {
                        return [
                            'class' => $className,
                            'name' => class_basename($className),
                            'table' => (new $className)->getTable(),
                        ];
                    } catch (\Throwable) {
                        return null;
                    }
                })
                ->filter()
                ->sortBy('name')
                ->values()
                ->all();
        });

        return Response::structured([
            'models' => $models,
            'count' => count($models),
        ]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(\Illuminate\Contracts\JsonSchema\JsonSchema $schema): array
    {
        return [];
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Mcp/ListModelsToolTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Mcp/ModelExplorerServer.php src/Mcp/Tools/ListModelsTool.php tests/Feature/Mcp/ListModelsToolTest.php
git commit -m "feat: add ModelExplorerServer and list-models tool"
```

---

### Task 6: `InspectModelTool`

**Files:**
- Create: `src/Mcp/Tools/InspectModelTool.php`
- Test: `tests/Feature/Mcp/InspectModelToolTest.php`

**Interfaces:**
- Consumes: `ModelResolver::resolve`, `ModelInspector::inspect`, `CompactPresenter::inspect` + `CompactPresenter::SECTIONS`, `ExplorerCache::rememberWhen`.
- Produces: `Response::structured(<overview + requested sections>)`; `Response::error(...)` on unknown/ambiguous model.

- [ ] **Step 1: Write the failing test**

```php
<?php

use OneLearningCommunity\LaravelModelExplorer\Mcp\ModelExplorerServer;
use OneLearningCommunity\LaravelModelExplorer\Mcp\Tools\InspectModelTool;

it('returns overview + columns + relations by default', function () {
    $response = ModelExplorerServer::tool(InspectModelTool::class, ['model' => 'Post']);

    $response->assertOk()
        ->assertSee('counts')
        ->assertSee('FK→User')
        ->assertSee('author');
});

it('honours an explicit include of a single section', function () {
    $response = ModelExplorerServer::tool(InspectModelTool::class, [
        'model' => 'Post',
        'include' => ['scopes'],
    ]);

    $response->assertOk()
        ->assertSee('published')
        ->assertSee('HasPublishedState.php');
});

it('include all returns every section', function () {
    $response = ModelExplorerServer::tool(InspectModelTool::class, [
        'model' => 'Post',
        'include' => ['all'],
    ]);

    $response->assertOk()
        ->assertSee('fillable')
        ->assertSee('summary');
});

it('errors with an actionable message for an unknown model', function () {
    ModelExplorerServer::tool(InspectModelTool::class, ['model' => 'Nope'])
        ->assertHasErrors();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Mcp/InspectModelToolTest.php`
Expected: FAIL — `InspectModelTool` not found.

- [ ] **Step 3: Implement `InspectModelTool`**

```php
<?php

namespace OneLearningCommunity\LaravelModelExplorer\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use OneLearningCommunity\LaravelModelExplorer\Mcp\Support\CompactPresenter;
use OneLearningCommunity\LaravelModelExplorer\Mcp\Support\ModelResolver;
use OneLearningCommunity\LaravelModelExplorer\Services\ExplorerCache;
use OneLearningCommunity\LaravelModelExplorer\Services\ModelInspector;

#[Description('Inspect one model\'s structure: columns, relations, scopes, accessors, traits, mass-assignment, and policy. Always returns an overview with section counts, then the requested sections (default: columns + relations). Each scope/relation/accessor carries a defined_in "path:line" pointer. Prefer this over reading the model source file.')]
class InspectModelTool extends Tool
{
    public function __construct(
        private readonly ModelResolver $resolver,
        private readonly ModelInspector $inspector,
        private readonly CompactPresenter $presenter,
        private readonly ExplorerCache $cache,
    ) {}

    public function handle(Request $request): Response
    {
        $request->validate([
            'model' => 'required|string',
        ], [
            'model.required' => 'Provide a model: a fully-qualified class name or a short class name, e.g. "App\\Models\\Order" or "Order".',
        ]);

        try {
            $className = $this->resolver->resolve($request->get('model'));
        } catch (\RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        $sections = $this->normalizeInclude((array) $request->array('include'));
        $useCache = (bool) config('model-explorer.mcp.cache.enabled', false);

        try {
            $data = $this->cache->rememberWhen(
                $useCache,
                'mcp.inspect.'.$className,
                fn () => $this->inspector->inspect($className),
            );
        } catch (\RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::structured($this->presenter->inspect($data, $sections));
    }

    /**
     * @param  array<int, string>  $include
     * @return list<string>
     */
    private function normalizeInclude(array $include): array
    {
        if ($include === []) {
            return ['columns', 'relations'];
        }

        if (in_array('all', $include, true)) {
            return CompactPresenter::SECTIONS;
        }

        return array_values(array_intersect(CompactPresenter::SECTIONS, $include));
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'model' => $schema->string()
                ->description('Fully-qualified class name or short class name, e.g. "App\\Models\\Order" or "Order".')
                ->required(),
            'include' => $schema->array()
                ->description('Sections to include: '.implode(', ', CompactPresenter::SECTIONS).', or "all". Defaults to columns + relations.'),
        ];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Mcp/InspectModelToolTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Mcp/Tools/InspectModelTool.php tests/Feature/Mcp/InspectModelToolTest.php
git commit -m "feat: add inspect-model tool with opt-in sections"
```

---

### Task 7: `RelationshipGraphTool`

**Files:**
- Create: `src/Mcp/Tools/RelationshipGraphTool.php`
- Test: `tests/Feature/Mcp/RelationshipGraphToolTest.php`

**Interfaces:**
- Consumes: `GraphBuilder::build`, `CompactPresenter::graph`, `ExplorerCache::rememberWhen`.
- Produces: `Response::structured(['nodes' => ..., 'edges' => ...])`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use OneLearningCommunity\LaravelModelExplorer\Mcp\ModelExplorerServer;
use OneLearningCommunity\LaravelModelExplorer\Mcp\Tools\RelationshipGraphTool;

it('returns nodes and edges for the model graph', function () {
    $response = ModelExplorerServer::tool(RelationshipGraphTool::class, []);

    $response->assertOk()
        ->assertSee('nodes')
        ->assertSee('edges')
        ->assertSee('belongsTo')
        ->assertSee('hasMany');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Mcp/RelationshipGraphToolTest.php`
Expected: FAIL — `RelationshipGraphTool` not found.

- [ ] **Step 3: Implement `RelationshipGraphTool`**

```php
<?php

namespace OneLearningCommunity\LaravelModelExplorer\Mcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use OneLearningCommunity\LaravelModelExplorer\Mcp\Support\CompactPresenter;
use OneLearningCommunity\LaravelModelExplorer\Services\ExplorerCache;
use OneLearningCommunity\LaravelModelExplorer\Services\GraphBuilder;

#[Description('Return the relationship graph of all models as nodes (class, name, table) and edges (from, to, relation type, relation name). Use this to see how models connect without inspecting each one.')]
class RelationshipGraphTool extends Tool
{
    public function __construct(
        private readonly GraphBuilder $builder,
        private readonly CompactPresenter $presenter,
        private readonly ExplorerCache $cache,
    ) {}

    public function handle(Request $request): Response
    {
        $useCache = (bool) config('model-explorer.mcp.cache.enabled', false);

        $built = $this->cache->rememberWhen($useCache, 'mcp.graph', fn () => $this->builder->build());

        return Response::structured($this->presenter->graph($built));
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(\Illuminate\Contracts\JsonSchema\JsonSchema $schema): array
    {
        return [];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Mcp/RelationshipGraphToolTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Mcp/Tools/RelationshipGraphTool.php tests/Feature/Mcp/RelationshipGraphToolTest.php
git commit -m "feat: add relationship-graph tool"
```

---

### Task 8: `FindModelTool`

**Files:**
- Create: `src/Mcp/Tools/FindModelTool.php`
- Test: `tests/Feature/Mcp/FindModelToolTest.php`

**Interfaces:**
- Consumes: `ModelDiscovery::discoverAll`, `ModelInspector::inspect`, `ExplorerCache::rememberWhen`.
- Produces: `Response::structured(['models' => list<array{class,name,table,matched:list<string>}>, 'count' => int])`; `Response::error(...)` when no filter is given.

- [ ] **Step 1: Write the failing test**

```php
<?php

use OneLearningCommunity\LaravelModelExplorer\Mcp\ModelExplorerServer;
use OneLearningCommunity\LaravelModelExplorer\Mcp\Tools\FindModelTool;

it('finds models by trait', function () {
    ModelExplorerServer::tool(FindModelTool::class, ['trait' => 'HasAuthor'])
        ->assertOk()
        ->assertSee('Post')
        ->assertSee('trait:');
});

it('finds models by base class via extends', function () {
    ModelExplorerServer::tool(FindModelTool::class, ['extends' => 'BasePost'])
        ->assertOk()
        ->assertSee('ExtendedPost');
});

it('finds models by relation target via relatesTo', function () {
    ModelExplorerServer::tool(FindModelTool::class, ['relatesTo' => 'User'])
        ->assertOk()
        ->assertSee('Post');
});

it('combines filters with AND', function () {
    ModelExplorerServer::tool(FindModelTool::class, [
        'trait' => 'HasAuthor',
        'hasColumn' => 'title',
    ])->assertOk()->assertSee('Post');
});

it('errors when no filter is provided', function () {
    ModelExplorerServer::tool(FindModelTool::class, [])->assertHasErrors();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Mcp/FindModelToolTest.php`
Expected: FAIL — `FindModelTool` not found.

- [ ] **Step 3: Implement `FindModelTool`**

```php
<?php

namespace OneLearningCommunity\LaravelModelExplorer\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use OneLearningCommunity\LaravelModelExplorer\Data\ModelData;
use OneLearningCommunity\LaravelModelExplorer\Data\RelationData;
use OneLearningCommunity\LaravelModelExplorer\Services\ExplorerCache;
use OneLearningCommunity\LaravelModelExplorer\Services\ModelDiscovery;
use OneLearningCommunity\LaravelModelExplorer\Services\ModelInspector;
use Spatie\ModelInfo\Attributes\Attribute;

#[Description('Find models matching structural criteria: trait (uses a trait), extends (parent class), relatesTo (has a relation to a model), hasColumn (table has a column). Filters combine with AND. Answers cross-cutting questions without inspecting every model. At least one filter is required.')]
class FindModelTool extends Tool
{
    public function __construct(
        private readonly ModelDiscovery $discovery,
        private readonly ModelInspector $inspector,
        private readonly ExplorerCache $cache,
    ) {}

    public function handle(Request $request): Response
    {
        $trait = $this->str($request->get('trait'));
        $extends = $this->str($request->get('extends'));
        $relatesTo = $this->str($request->get('relatesTo'));
        $hasColumn = $this->str($request->get('hasColumn'));

        if (! $trait && ! $extends && ! $relatesTo && ! $hasColumn) {
            return Response::error('Provide at least one filter: trait, extends, relatesTo, or hasColumn.');
        }

        $useCache = (bool) config('model-explorer.mcp.cache.enabled', false);
        $matches = [];

        foreach ($this->discovery->discoverAll() as $className) {
            try {
                $data = $this->cache->rememberWhen(
                    $useCache,
                    'mcp.inspect.'.$className,
                    fn () => $this->inspector->inspect($className),
                );
            } catch (\RuntimeException) {
                continue;
            }

            $matched = [];

            if ($trait !== null) {
                if (($hit = $this->matchTrait($data, $trait)) === null) {
                    continue;
                }
                $matched[] = 'trait: '.$hit;
            }

            if ($extends !== null) {
                if (($hit = $this->matchExtends($className, $extends)) === null) {
                    continue;
                }
                $matched[] = 'extends: '.$hit;
            }

            if ($relatesTo !== null) {
                if (($hit = $this->matchRelatesTo($data, $relatesTo)) === null) {
                    continue;
                }
                $matched[] = 'relatesTo: '.$hit;
            }

            if ($hasColumn !== null) {
                if (($hit = $this->matchColumn($data, $hasColumn)) === null) {
                    continue;
                }
                $matched[] = 'hasColumn: '.$hit;
            }

            $matches[] = [
                'class' => $data->className,
                'name' => $data->shortName,
                'table' => $data->table,
                'matched' => $matched,
            ];
        }

        return Response::structured([
            'models' => $matches,
            'count' => count($matches),
        ]);
    }

    private function str(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }

    private function matchTrait(ModelData $data, string $needle): ?string
    {
        foreach ($data->traits as $trait) {
            if (strcasecmp($trait, $needle) === 0 || strcasecmp(class_basename($trait), $needle) === 0) {
                return $trait;
            }
        }

        return null;
    }

    private function matchExtends(string $className, string $needle): ?string
    {
        $needle = ltrim($needle, '\\');

        foreach (class_parents($className) ?: [] as $parent) {
            if (strcasecmp($parent, $needle) === 0 || strcasecmp(class_basename($parent), $needle) === 0) {
                return $parent;
            }
        }

        return null;
    }

    private function matchRelatesTo(ModelData $data, string $needle): ?string
    {
        $needle = ltrim($needle, '\\');

        return $data->relations->first(function (RelationData $rel) use ($needle) {
            return strcasecmp($rel->related, $needle) === 0
                || strcasecmp(class_basename($rel->related), $needle) === 0;
        })?->name === null
            ? $this->relatesToLabel($data, $needle)
            : $this->relatesToLabel($data, $needle);
    }

    private function relatesToLabel(ModelData $data, string $needle): ?string
    {
        foreach ($data->relations as $rel) {
            if (strcasecmp($rel->related, $needle) === 0 || strcasecmp(class_basename($rel->related), $needle) === 0) {
                return lcfirst($rel->type).' '.class_basename($rel->related);
            }
        }

        return null;
    }

    private function matchColumn(ModelData $data, string $needle): ?string
    {
        foreach ($data->attributes as $attr) {
            /** @var Attribute $attr */
            if (! $attr->virtual && strcasecmp($attr->name, $needle) === 0) {
                return $attr->name;
            }
        }

        return null;
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'trait' => $schema->string()->description('Model uses this trait (short name or FQCN), e.g. "SoftDeletes".'),
            'extends' => $schema->string()->description('Model extends this class (short name or FQCN), e.g. "Authenticatable".'),
            'relatesTo' => $schema->string()->description('Model has a relation pointing at this model (short name or FQCN), e.g. "Team".'),
            'hasColumn' => $schema->string()->description('Model\'s table has this column, e.g. "team_id".'),
        ];
    }
}
```

> Implementation note: `matchRelatesTo` is written to return the relation label via `relatesToLabel`; simplify if you prefer — the behavior required is "return a `Type Related` label when any relation targets `$needle`, else null." Keep `relatesToLabel` as the single source of truth.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Mcp/FindModelToolTest.php`
Expected: PASS.

- [ ] **Step 5: Simplify `matchRelatesTo` to delegate cleanly**

Replace the `matchRelatesTo` method body with a direct delegation (removing the redundant `first()` dance):

```php
    private function matchRelatesTo(ModelData $data, string $needle): ?string
    {
        return $this->relatesToLabel($data, ltrim($needle, '\\'));
    }
```

- [ ] **Step 6: Re-run test to confirm still green**

Run: `./vendor/bin/pest tests/Feature/Mcp/FindModelToolTest.php`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add src/Mcp/Tools/FindModelTool.php tests/Feature/Mcp/FindModelToolTest.php
git commit -m "feat: add find-model tool with trait/extends/relatesTo/hasColumn filters"
```

---

### Task 9: `ModelSourceTool`

**Files:**
- Create: `src/Mcp/Tools/ModelSourceTool.php`
- Test: `tests/Feature/Mcp/ModelSourceToolTest.php`

**Interfaces:**
- Consumes: `ModelResolver::resolve`, `ModelInspector::inspect`, `CompactPresenter::pointer`.
- Produces: `Response::structured(['code' => string, 'defined_in' => ?string, 'doc' => ?string])`; `Response::error(...)` listing available names when the `name` is unknown for that `kind`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use OneLearningCommunity\LaravelModelExplorer\Mcp\ModelExplorerServer;
use OneLearningCommunity\LaravelModelExplorer\Mcp\Tools\ModelSourceTool;

it('returns the trait-correct snippet for a relation', function () {
    $response = ModelExplorerServer::tool(ModelSourceTool::class, [
        'model' => 'Post',
        'kind' => 'relation',
        'name' => 'author',
    ]);

    $response->assertOk()
        ->assertSee('belongsTo')
        ->assertSee('HasAuthor.php');
});

it('returns the snippet for a scope', function () {
    ModelExplorerServer::tool(ModelSourceTool::class, [
        'model' => 'Post',
        'kind' => 'scope',
        'name' => 'recent',
    ])->assertOk()->assertSee('subDays');
});

it('errors and lists available names for an unknown definition', function () {
    ModelExplorerServer::tool(ModelSourceTool::class, [
        'model' => 'Post',
        'kind' => 'scope',
        'name' => 'nope',
    ])->assertHasErrors();
});

it('rejects an invalid kind', function () {
    ModelExplorerServer::tool(ModelSourceTool::class, [
        'model' => 'Post',
        'kind' => 'column',
        'name' => 'title',
    ])->assertHasErrors();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Mcp/ModelSourceToolTest.php`
Expected: FAIL — `ModelSourceTool` not found.

- [ ] **Step 3: Implement `ModelSourceTool`**

```php
<?php

namespace OneLearningCommunity\LaravelModelExplorer\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use OneLearningCommunity\LaravelModelExplorer\Data\ModelData;
use OneLearningCommunity\LaravelModelExplorer\Data\RelationData;
use OneLearningCommunity\LaravelModelExplorer\Data\ScopeData;
use OneLearningCommunity\LaravelModelExplorer\Mcp\Support\CompactPresenter;
use OneLearningCommunity\LaravelModelExplorer\Mcp\Support\ModelResolver;
use OneLearningCommunity\LaravelModelExplorer\Services\ModelInspector;

#[Description('Return the source snippet for one scope, relation, or accessor of a model — dedented and correctly attributed to the trait or parent class that defines it. Use the defined_in pointers from inspect-model to choose what to fetch.')]
class ModelSourceTool extends Tool
{
    public function __construct(
        private readonly ModelResolver $resolver,
        private readonly ModelInspector $inspector,
        private readonly CompactPresenter $presenter,
    ) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'model' => 'required|string',
            'kind' => 'required|in:scope,relation,accessor',
            'name' => 'required|string',
        ], [
            'kind.in' => 'kind must be one of: scope, relation, accessor.',
        ]);

        try {
            $className = $this->resolver->resolve($validated['model']);
            $data = $this->inspector->inspect($className);
        } catch (\RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        [$snippet, $available] = $this->locate($data, $validated['kind'], $validated['name']);

        if ($snippet === null) {
            return Response::error(
                "No {$validated['kind']} named [{$validated['name']}] on {$data->shortName}. Available: ".
                (count($available) ? implode(', ', $available) : '(none)').'.'
            );
        }

        return Response::structured(array_filter([
            'code' => $snippet['code'],
            'defined_in' => $this->presenter->pointer($snippet),
            'doc' => $snippet['doc_summary'] ?? null,
        ], fn ($v) => $v !== null));
    }

    /**
     * @return array{0: ?array{code:string,file:string,start_line:int,doc_summary:?string}, 1: list<string>}
     */
    private function locate(ModelData $data, string $kind, string $name): array
    {
        if ($kind === 'scope') {
            $available = $data->scopes->pluck('name')->all();
            $scope = $data->scopes->first(fn (ScopeData $s) => strcasecmp($s->name, $name) === 0);

            return [$scope?->snippet, $available];
        }

        if ($kind === 'relation') {
            $available = $data->relations->pluck('name')->all();
            $relation = $data->relations->first(fn (RelationData $r) => strcasecmp($r->name, $name) === 0);

            return [$relation?->snippet, $available];
        }

        // accessor
        $available = array_keys($data->accessorSnippets);

        foreach ($data->accessorSnippets as $attr => $snippet) {
            if (strcasecmp($attr, $name) === 0) {
                return [$snippet, $available];
            }
        }

        return [null, $available];
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'model' => $schema->string()
                ->description('Fully-qualified or short class name, e.g. "Order".')
                ->required(),
            'kind' => $schema->string()
                ->description('One of: scope, relation, accessor.')
                ->required(),
            'name' => $schema->string()
                ->description('The definition name, e.g. scope "active", relation "items", accessor "full_name".')
                ->required(),
        ];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Mcp/ModelSourceToolTest.php`
Expected: PASS.

- [ ] **Step 5: Run the whole MCP suite (server now references all five real tools)**

Run: `./vendor/bin/pest tests/Feature/Mcp`
Expected: PASS for every MCP test file.

- [ ] **Step 6: Commit**

```bash
git add src/Mcp/Tools/ModelSourceTool.php tests/Feature/Mcp/ModelSourceToolTest.php
git commit -m "feat: add model-source tool for on-demand snippets"
```

---

### Task 10: Register the server in the service provider (with kill-switch gating)

**Files:**
- Modify: `src/LaravelModelExplorerServiceProvider.php`
- Test: `tests/Feature/Mcp/McpRegistrationTest.php`

**Interfaces:**
- Produces: `LaravelModelExplorerServiceProvider::shouldRegisterMcp(): bool` — true only when `model-explorer.enabled` and `model-explorer.mcp.enabled` are both true and `Laravel\Mcp\Facades\Mcp` exists. `packageBooted()` calls `Mcp::local('model-explorer', ModelExplorerServer::class)` when that predicate holds.

- [ ] **Step 1: Write the failing test**

```php
<?php

use OneLearningCommunity\LaravelModelExplorer\LaravelModelExplorerServiceProvider;

function provider(): LaravelModelExplorerServiceProvider
{
    return new LaravelModelExplorerServiceProvider(app());
}

it('registers MCP when both flags are enabled', function () {
    config(['model-explorer.enabled' => true, 'model-explorer.mcp.enabled' => true]);

    expect(provider()->shouldRegisterMcp())->toBeTrue();
});

it('does not register MCP when the package is disabled', function () {
    config(['model-explorer.enabled' => false, 'model-explorer.mcp.enabled' => true]);

    expect(provider()->shouldRegisterMcp())->toBeFalse();
});

it('does not register MCP when mcp is disabled', function () {
    config(['model-explorer.enabled' => true, 'model-explorer.mcp.enabled' => false]);

    expect(provider()->shouldRegisterMcp())->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Mcp/McpRegistrationTest.php`
Expected: FAIL — `Call to undefined method ...::shouldRegisterMcp()`.

- [ ] **Step 3: Add the predicate and registration to the provider**

Add the import at the top of `src/LaravelModelExplorerServiceProvider.php`:

```php
use Laravel\Mcp\Facades\Mcp;
use OneLearningCommunity\LaravelModelExplorer\Mcp\ModelExplorerServer;
```

In `packageBooted()`, after the existing `Gate::define(...)` block, add:

```php
        $this->registerMcpServer();
```

Then add these two methods to the class:

```php
    /**
     * The MCP server registers only when the package and its MCP feature are both
     * enabled (and laravel/mcp is installed). This is the agent-surface kill switch.
     */
    public function shouldRegisterMcp(): bool
    {
        return (bool) config('model-explorer.enabled', true)
            && (bool) config('model-explorer.mcp.enabled', true)
            && class_exists(Mcp::class);
    }

    private function registerMcpServer(): void
    {
        if ($this->shouldRegisterMcp()) {
            Mcp::local('model-explorer', ModelExplorerServer::class);
        }
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Mcp/McpRegistrationTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/LaravelModelExplorerServiceProvider.php tests/Feature/Mcp/McpRegistrationTest.php
git commit -m "feat: register model-explorer MCP server with enabled/mcp.enabled gating"
```

---

### Task 11: Boost guidelines + docs (README, CHANGELOG)

**Files:**
- Create: `resources/boost/guidelines/core.blade.php`
- Modify: `README.md`
- Modify: `CHANGELOG.md`
- Test: `tests/Feature/Mcp/BoostGuidelinesTest.php`

**Interfaces:**
- Produces: `resources/boost/guidelines/core.blade.php` advertising the five tools to Boost-installed agents (auto-loaded on the consumer's `boost:install`).

- [ ] **Step 1: Write the failing test**

```php
<?php

it('ships a Boost guidelines file naming every MCP tool', function () {
    $path = __DIR__.'/../../../resources/boost/guidelines/core.blade.php';

    expect(file_exists($path))->toBeTrue();

    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('model-explorer')
        ->toContain('list-models')
        ->toContain('inspect-model')
        ->toContain('relationship-graph')
        ->toContain('find-model')
        ->toContain('model-source');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Mcp/BoostGuidelinesTest.php`
Expected: FAIL — file does not exist.

- [ ] **Step 3: Create the Boost guidelines file**

```blade
## Laravel Model Explorer — Model Introspection

This app ships the `model-explorer` MCP server. When you need to understand the
application's Eloquent models, prefer these tools over reading model source files —
they resolve trait-provided members and database columns that a source scan misses.

### Tools

- `list-models` — list every model (class, short name, table). Start here.
- `inspect-model` — one model's structure. Returns an overview with section counts,
  then the sections you ask for via `include` (`columns`, `relations`, `scopes`,
  `accessors`, `traits`, `mass-assignment`, `policy`, or `all`; default columns +
  relations). Pass the model as a short name (`Order`) or FQCN. Each scope/relation/
  accessor includes a `defined_in` `path:line` pointer.
- `relationship-graph` — all models as nodes + edges; use it to see how models connect.
- `find-model` — find models by `trait`, `extends`, `relatesTo`, or `hasColumn`
  (filters AND together). Use for cross-cutting questions like "which models use
  SoftDeletes" or "which models belong to Team".
- `model-source` — fetch the dedented, trait-correct source for one `scope`,
  `relation`, or `accessor` (`model`, `kind`, `name`). Use the `defined_in` pointers
  from `inspect-model` to decide what to fetch.

These tools read live, so results always reflect the current model code.
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Mcp/BoostGuidelinesTest.php`
Expected: PASS.

- [ ] **Step 5: Add a README section**

Add a new section to `README.md` (after the existing feature/usage sections), documenting the MCP server:

```markdown
## AI Model Introspection (MCP)

Model Explorer ships a local [`laravel/mcp`](https://laravel.com/docs/mcp) server,
`model-explorer`, that lets AI coding agents introspect your Eloquent models without
scanning source. Register it in your AI client:

```json
{
  "mcpServers": {
    "model-explorer": {
      "command": "php",
      "args": ["artisan", "mcp:start", "model-explorer"]
    }
  }
}
```

Tools: `list-models`, `inspect-model` (opt-in sections via `include`),
`relationship-graph`, `find-model` (filter by `trait`/`extends`/`relatesTo`/`hasColumn`),
and `model-source`. If you use [Laravel Boost](https://laravel.com/docs/boost),
`boost:install` automatically advertises these tools to your agent.

The tools read live by default so an agent never sees stale structure mid-development.
Set `MODEL_EXPLORER_MCP_CACHE=true` to trade freshness for speed on very large model
sets, or `MODEL_EXPLORER_MCP=false` to disable the server entirely.
```

- [ ] **Step 6: Add a CHANGELOG entry**

Add to the top of `CHANGELOG.md` under a new unreleased/next-version heading:

```markdown
### Added
- MCP server (`model-explorer`) exposing AI model-introspection tools: `list-models`,
  `inspect-model`, `relationship-graph`, `find-model`, and `model-source`. Reads live
  by default; opt-in caching via `MODEL_EXPLORER_MCP_CACHE`. Boost guidelines advertise
  the tools on `boost:install`. See ADR-011.
```

- [ ] **Step 7: Run the full suite**

Run: `./vendor/bin/pest`
Expected: PASS — all tests, including the existing ones.

- [ ] **Step 8: Commit**

```bash
git add resources/boost/guidelines/core.blade.php README.md CHANGELOG.md tests/Feature/Mcp/BoostGuidelinesTest.php
git commit -m "docs: Boost guidelines + README/CHANGELOG for MCP server"
```

---

## Self-Review

**Spec coverage (ADR-011 → tasks):**
- Delivery via own `laravel/mcp` server → Tasks 2, 5, 10. ✓
- Five tools (`list-models`, `inspect-model`, `relationship-graph`, `find-model`, `model-source`) → Tasks 5–9. ✓
- Compact JSON via `CompactPresenter`/`Response::structured` → Tasks 4–9. ✓
- `inspect-model` `include` + counts header + `defined_in` pointers → Tasks 4, 6. ✓
- `model` resolves FQCN/short, ambiguous error → Task 3. ✓
- `find-model` filters + AND + no-filter error + `matched` notes → Task 8. ✓
- `model-source` kind/name + not-found lists names + trait-correct → Task 9. ✓
- `GraphBuilder` extraction + controller regression → Task 1. ✓
- Live-by-default caching, `mcp.cache.enabled` opt-in, `rememberWhen` → Task 2 (+ used in 5–8). ✓
- `mcp.enabled`/`enabled` kill switches → Task 10. ✓
- Boost guidelines → Task 11. ✓
- No live DB rows → guaranteed (no tool queries records; only structure/source). ✓

**Placeholder scan:** No TBD/TODO; every code step shows full code. (Task 5 intentionally references Tasks 6–9 tool classes in the server `$tools` array — addressed by the in-order execution note and the Task 9 full-suite run.)

**Type consistency:** `rememberWhen(bool,string,Closure)`, `resolve(string):string`, `inspect(ModelData,array):array`, `pointer(?array):?string`, `graph(array):array`, `CompactPresenter::SECTIONS`, and `shouldRegisterMcp():bool` are used identically across producing and consuming tasks. `RelationData->type` is the class basename (e.g. `BelongsTo`) everywhere it is read.
