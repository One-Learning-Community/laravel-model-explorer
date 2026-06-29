<?php

namespace OneLearningCommunity\LaravelModelExplorer\Services;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * Thin caching layer for the (potentially expensive) model discovery and
 * inspection work. Caching is opt-in via the `model-explorer.cache` config.
 *
 * Invalidation uses a versioned key namespace: flush() bumps the version,
 * which orphans every previously cached entry without relying on cache tags
 * (which not all stores support).
 */
class ExplorerCache
{
    private const VERSION_KEY = 'model-explorer:cache-version';

    public function enabled(): bool
    {
        return (bool) config('model-explorer.cache.enabled', false);
    }

    /**
     * Return the cached value for $key, computing and storing it on a miss.
     * When caching is disabled the callback is simply invoked each time.
     */
    public function remember(string $key, Closure $callback): mixed
    {
        if (! $this->enabled()) {
            return $callback();
        }

        $store = $this->store();
        $namespacedKey = $this->prefix($store).$key;
        $ttl = config('model-explorer.cache.ttl');

        return $ttl
            ? $store->remember($namespacedKey, (int) $ttl, $callback)
            : $store->rememberForever($namespacedKey, $callback);
    }

    /**
     * Invalidate every cached entry by bumping the namespace version.
     */
    public function flush(): void
    {
        $store = $this->store();
        $store->forever(self::VERSION_KEY, $this->version($store) + 1);
    }

    private function store(): Repository
    {
        return Cache::store(config('model-explorer.cache.store'));
    }

    private function prefix(Repository $store): string
    {
        return 'model-explorer:v'.$this->version($store).':';
    }

    private function version(Repository $store): int
    {
        return (int) $store->get(self::VERSION_KEY, 1);
    }
}
