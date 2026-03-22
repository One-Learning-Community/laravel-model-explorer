<?php

use Illuminate\Support\Facades\Gate;
use Workbench\App\Models\Concerns\HasAuthor;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

function modelSlug(string $className): string
{
    return strtr(base64_encode($className), '+/', '-_');
}

it('returns a json list of discovered models', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->getJson('/_model-explorer/api/models')
        ->assertOk()
        ->assertJsonStructure([['class', 'short_name', 'table']]);
});

it('includes each model class name and table in the list', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->getJson('/_model-explorer/api/models')
        ->assertOk()
        ->assertJsonFragment(['class' => Post::class, 'short_name' => 'Post', 'table' => 'posts'])
        ->assertJsonFragment(['class' => User::class, 'short_name' => 'User', 'table' => 'users']);
});

it('returns full model detail for a valid model slug', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->getJson('/_model-explorer/api/models/'.modelSlug(Post::class))
        ->assertOk()
        ->assertJsonFragment(['class' => Post::class, 'table' => 'posts'])
        ->assertJsonStructure(['class', 'short_name', 'table', 'fillable', 'guarded', 'hidden', 'casts', 'appends', 'uses_timestamps', 'traits', 'attributes', 'relations']);
});

it('includes relation metadata in model detail', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->getJson('/_model-explorer/api/models/'.modelSlug(Post::class))
        ->assertOk()
        ->assertJsonFragment([
            'name' => 'user',
            'type' => 'BelongsTo',
            'related' => User::class,
            'foreign_key' => 'user_id',
            'local_key' => 'id',
            'defined_in' => null,
        ]);
});

it('sets defined_in to null for relations on the model directly', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->getJson('/_model-explorer/api/models/'.modelSlug(Post::class))
        ->assertOk()
        ->assertJsonFragment(['name' => 'user', 'defined_in' => null]);
});

it('sets defined_in to the trait FQCN for trait-sourced relations', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->getJson('/_model-explorer/api/models/'.modelSlug(Post::class))
        ->assertOk()
        ->assertJsonFragment(['name' => 'author', 'defined_in' => HasAuthor::class]);
});

it('returns 404 json for an unknown model slug', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->getJson('/_model-explorer/api/models/'.modelSlug('App\Models\DoesNotExist'))
        ->assertNotFound()
        ->assertJson(['message' => 'Model not found.']);
});

it('returns 403 on the model list when the gate denies access', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->getJson('/_model-explorer/api/models')
        ->assertForbidden();
});

it('returns 403 on model detail when the gate denies access', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->getJson('/_model-explorer/api/models/'.modelSlug(Post::class))
        ->assertForbidden();
});

it('returns 404 on model list when the package is disabled', function () {
    app()->detectEnvironment(fn () => 'local');
    config()->set('model-explorer.enabled', false);

    $this->getJson('/_model-explorer/api/models')
        ->assertNotFound();
});

it('allows gate override to grant access to api in non-local environment', function () {
    app()->detectEnvironment(fn () => 'production');
    Gate::define('viewModelExplorer', fn ($user = null) => true);

    $this->getJson('/_model-explorer/api/models')
        ->assertOk();
});
