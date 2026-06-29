<?php

use OneLearningCommunity\LaravelModelExplorer\Services\ExplorerCache;

it('rememberWhen caches when the condition is true regardless of cache.enabled', function () {
    config(['model-explorer.cache.enabled' => false]);
    $cache = app(ExplorerCache::class);

    $calls = 0;
    $make = function () use ($cache, &$calls) {
        return $cache->rememberWhen(true, 'rw-test', function () use (&$calls) {
            $calls++;

            return 'value';
        });
    };

    expect($make())->toBe('value')
        ->and($make())->toBe('value')
        ->and($calls)->toBe(1);
});

it('rememberWhen runs live every call when the condition is false', function () {
    $cache = app(ExplorerCache::class);

    $calls = 0;
    $make = function () use ($cache, &$calls) {
        return $cache->rememberWhen(false, 'rw-live', function () use (&$calls) {
            $calls++;

            return 'v';
        });
    };

    $make();
    $make();

    expect($calls)->toBe(2);
});
