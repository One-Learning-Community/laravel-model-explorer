<?php

use OneLearningCommunity\LaravelModelExplorer\Mcp\Support\ModelResolver;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

it('resolves a fully-qualified class name', function () {
    expect(app(ModelResolver::class)->resolve(Post::class))
        ->toBe(Post::class);
});

it('resolves a bare short class name', function () {
    expect(app(ModelResolver::class)->resolve('Post'))
        ->toBe(Post::class);
});

it('ignores a leading backslash', function () {
    expect(app(ModelResolver::class)->resolve('\\'.User::class))
        ->toBe(User::class);
});

it('throws an actionable error for an unknown model', function () {
    app(ModelResolver::class)->resolve('Nope');
})->throws(RuntimeException::class, 'No discovered model');
