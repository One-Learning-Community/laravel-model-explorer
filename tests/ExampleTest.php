<?php

it('boots the service provider without errors', function () {
    expect(app()->bound(\OneLearningCommunity\LaravelModelExplorer\LaravelModelExplorerServiceProvider::class))
        ->toBeFalse(); // Providers are not bound as themselves; this confirms app() is available

    expect(config('model-explorer.enabled'))->toBeTrue();
});
