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
