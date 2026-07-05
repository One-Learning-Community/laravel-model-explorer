<?php

use Illuminate\Support\Facades\Gate;
use Workbench\App\Models\BrokenModel;
use Workbench\App\Models\Concerns\HasAuthor;
use Workbench\App\Models\Country;
use Workbench\App\Models\IndexedRecord;
use Workbench\App\Models\Post;
use Workbench\App\Models\Tag;
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

it('omits models that cannot be instantiated from the list', function () {
    app()->detectEnvironment(fn () => 'local');

    $response = $this->getJson('/_model-explorer/api/models')->assertOk();

    // BrokenModel throws on instantiation; it must be skipped rather than
    // breaking the entire list, which still contains the healthy models.
    expect(collect($response->json())->pluck('class'))
        ->toContain(Post::class)
        ->not->toContain(BrokenModel::class);
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

it('includes expanded enum cases on an enum-cast column', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->getJson('/_model-explorer/api/models/'.modelSlug(Post::class))
        ->assertOk()
        ->assertJsonFragment([
            'name' => 'status',
            'enum_cases' => [
                ['name' => 'Draft', 'value' => 'draft'],
                ['name' => 'Published', 'value' => 'published'],
                ['name' => 'Archived', 'value' => 'archived'],
            ],
        ]);
});

it('sets enum_cases to null on a non-enum column', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->getJson('/_model-explorer/api/models/'.modelSlug(Post::class))
        ->assertOk()
        ->assertJsonFragment(['name' => 'title', 'enum_cases' => null]);
});

it('marks a non-unique indexed column as indexed', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->getJson('/_model-explorer/api/models/'.modelSlug(Post::class))
        ->assertOk()
        ->assertJsonFragment(['name' => 'published_at', 'indexed' => true, 'index_role' => 'single'])
        ->assertJsonFragment(['name' => 'title', 'indexed' => false, 'index_role' => null]);
});

it('reports honest indexed boolean and index_role for composite members', function () {
    app()->detectEnvironment(fn () => 'local');

    // Leading columns are usable by a lone filter (indexed true); non-leading
    // members are not (indexed false) but keep their composite position.
    $this->getJson('/_model-explorer/api/models/'.modelSlug(IndexedRecord::class))
        ->assertOk()
        ->assertJsonFragment(['name' => 'a', 'indexed' => true, 'index_role' => 'single'])
        ->assertJsonFragment(['name' => 'b', 'indexed' => true, 'index_role' => 'composite-leading'])
        ->assertJsonFragment(['name' => 'c', 'indexed' => false, 'index_role' => 'composite-2of3'])
        ->assertJsonFragment(['name' => 'd', 'indexed' => false, 'index_role' => 'composite-3of3']);
});

it('sets defined_in to null for relations on the model directly', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->getJson('/_model-explorer/api/models/'.modelSlug(Post::class))
        ->assertOk()
        ->assertJsonFragment(['name' => 'user', 'defined_in' => null]);
});

it('includes pivot detail for a belongsToMany relation', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->getJson('/_model-explorer/api/models/'.modelSlug(Tag::class))
        ->assertOk()
        ->assertJsonFragment([
            'name' => 'videos',
            'pivot_table' => 'tag_video',
            'pivot_foreign_key' => 'tag_id',
            'pivot_related_key' => 'video_id',
            'pivot_columns' => ['sort_order'],
        ]);
});

it('includes the through model for a hasManyThrough relation', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->getJson('/_model-explorer/api/models/'.modelSlug(Country::class))
        ->assertOk()
        ->assertJsonFragment([
            'name' => 'posts',
            'through_model' => User::class,
            'through_foreign_key' => 'country_id',
        ]);
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
    $this->getJson('/_model-explorer/api/models')
        ->assertForbidden();
});

it('returns 403 on model detail when the gate denies access', function () {
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
    Gate::define('viewModelExplorer', fn ($user = null) => true);

    $this->getJson('/_model-explorer/api/models')
        ->assertOk();
});
