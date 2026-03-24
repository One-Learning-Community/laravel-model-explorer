<?php

use OneLearningCommunity\LaravelModelExplorer\LaravelModelExplorerServiceProvider;

it('boots the service provider without errors', function () {
    expect(app()->bound(LaravelModelExplorerServiceProvider::class))
        ->toBeFalse(); // Providers are not bound as themselves; this confirms app() is available

    expect(config('model-explorer.enabled'))->toBeTrue();
});
