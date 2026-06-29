<?php

use OneLearningCommunity\LaravelModelExplorer\Services\ExplorerCache;

/**
 * Returns a callback that increments and returns $counter->n on each call, so a
 * cached call (which skips the callback) is distinguishable from a fresh one.
 */
function countingCallback(object $counter): Closure
{
    return function () use ($counter) {
        return ++$counter->n;
    };
}

it('does not cache when caching is disabled', function () {
    config()->set('model-explorer.cache.enabled', false);
    $cache = new ExplorerCache;
    $counter = (object) ['n' => 0];
    $cb = countingCallback($counter);

    expect($cache->remember('key', $cb))->toBe(1)
        ->and($cache->remember('key', $cb))->toBe(2);
});

it('caches the computed value when caching is enabled', function () {
    config()->set('model-explorer.cache.enabled', true);
    $cache = new ExplorerCache;
    $counter = (object) ['n' => 0];
    $cb = countingCallback($counter);

    expect($cache->remember('key', $cb))->toBe(1)
        ->and($cache->remember('key', $cb))->toBe(1);
});

it('recomputes after flush', function () {
    config()->set('model-explorer.cache.enabled', true);
    $cache = new ExplorerCache;
    $counter = (object) ['n' => 0];
    $cb = countingCallback($counter);

    expect($cache->remember('key', $cb))->toBe(1);

    $cache->flush();

    expect($cache->remember('key', $cb))->toBe(2);
});

it('clears cached entries via the artisan command', function () {
    config()->set('model-explorer.cache.enabled', true);
    $cache = app(ExplorerCache::class);

    expect($cache->remember('key', fn () => 'first'))->toBe('first')
        ->and($cache->remember('key', fn () => 'ignored'))->toBe('first');

    $this->artisan('model-explorer:clear')
        ->expectsOutputToContain('Model Explorer cache cleared.')
        ->assertExitCode(0);

    expect($cache->remember('key', fn () => 'second'))->toBe('second');
});
