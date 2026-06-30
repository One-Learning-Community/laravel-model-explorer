<?php

use OneLearningCommunity\LaravelModelExplorer\Mcp\Support\ModelResolver;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\App\Vendor\PretendVendorModel;

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

it('refuses an undiscovered vendor model by default', function () {
    config(['model-explorer.mcp.allow_undiscovered' => false]);

    app(ModelResolver::class)->resolve(PretendVendorModel::class);
})->throws(RuntimeException::class, 'allow_undiscovered');

it('resolves an undiscovered vendor model when the escape hatch is enabled', function () {
    config(['model-explorer.mcp.allow_undiscovered' => true]);

    expect(app(ModelResolver::class)->resolve(PretendVendorModel::class))
        ->toBe(PretendVendorModel::class);
});

it('only accepts the escape hatch by fully-qualified name, never a short name', function () {
    config(['model-explorer.mcp.allow_undiscovered' => true]);

    app(ModelResolver::class)->resolve('PretendVendorModel');
})->throws(RuntimeException::class, 'No discovered model');

it('still rejects a non-model class even with the escape hatch enabled', function () {
    config(['model-explorer.mcp.allow_undiscovered' => true]);

    app(ModelResolver::class)->resolve(ModelResolver::class);
})->throws(RuntimeException::class, 'No discovered model');
