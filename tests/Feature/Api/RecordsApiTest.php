<?php

use Illuminate\Support\Facades\Gate;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

function recordsModelSlug(string $className): string
{
    return strtr(base64_encode($className), '+/', '-_');
}

it('returns record attributes when found by primary key', function () {
    app()->detectEnvironment(fn () => 'local');
    $post = Post::forceCreate(['title' => 'Hello', 'body' => 'World']);

    $this->getJson('/_model-explorer/api/models/'.recordsModelSlug(Post::class).'/record?field=id&value='.$post->id)
        ->assertOk()
        ->assertJsonStructure(['key_field', 'key_value', 'model_class', 'short_name', 'attributes'])
        ->assertJsonFragment([
            'key_field' => 'id',
            'key_value' => $post->id,
            'model_class' => Post::class,
            'short_name' => 'Post',
        ]);
});

it('includes hidden attributes in the response', function () {
    app()->detectEnvironment(fn () => 'local');
    $post = Post::forceCreate(['title' => 'T', 'body' => 'B', 'secret_key' => 'shhh']);

    $response = $this->getJson(
        '/_model-explorer/api/models/'.recordsModelSlug(Post::class).'/record?field=id&value='.$post->id
    )->assertOk();

    expect($response->json('attributes'))->toHaveKey('secret_key')
        ->and($response->json('attributes.secret_key'))->toBe('shhh');
});

it('does not include appended accessor values in the record response', function () {
    app()->detectEnvironment(fn () => 'local');
    $post = Post::forceCreate(['title' => 'T', 'body' => 'World']);

    $response = $this->getJson(
        '/_model-explorer/api/models/'.recordsModelSlug(Post::class).'/record?field=id&value='.$post->id
    )->assertOk();

    // summary and excerpt are appended accessors — must not be in the raw response
    expect($response->json('attributes'))->not->toHaveKey('summary')
        ->and($response->json('attributes'))->not->toHaveKey('excerpt');
});

it('resolves an appended accessor value via the attribute endpoint', function () {
    app()->detectEnvironment(fn () => 'local');
    $post = Post::forceCreate(['title' => 'T', 'body' => 'Hello World']);

    $response = $this->getJson(
        '/_model-explorer/api/models/'.recordsModelSlug(Post::class).'/record/attributes/excerpt?record_key='.$post->id
    )->assertOk();

    expect($response->json('name'))->toBe('excerpt')
        ->and($response->json('value'))->toBe('Hello World');
});

it('resolves an old-style accessor via the attribute endpoint', function () {
    app()->detectEnvironment(fn () => 'local');
    $post = Post::forceCreate(['title' => 'T', 'body' => 'B']);

    $response = $this->getJson(
        '/_model-explorer/api/models/'.recordsModelSlug(Post::class).'/record/attributes/summary?record_key='.$post->id
    )->assertOk();

    expect($response->json('name'))->toBe('summary')
        ->and($response->json('value'))->toBe('');
});

it('resolves multiple accessors in a single batch request', function () {
    app()->detectEnvironment(fn () => 'local');
    $post = Post::forceCreate(['title' => 'T', 'body' => 'Hello World']);

    $response = $this->getJson(
        '/_model-explorer/api/models/'.recordsModelSlug(Post::class).'/record/attributes?record_key='.$post->id.'&names[]=summary&names[]=excerpt'
    )->assertOk();

    expect($response->json())->toHaveKeys(['summary', 'excerpt'])
        ->and($response->json('summary.value'))->toBe('')
        ->and($response->json('excerpt.value'))->toBe('Hello World')
        ->and($response->json('summary.error'))->toBeNull()
        ->and($response->json('excerpt.error'))->toBeNull();
});

it('silently skips unknown attribute names in the batch request', function () {
    app()->detectEnvironment(fn () => 'local');
    $post = Post::forceCreate(['title' => 'T', 'body' => 'B']);

    $response = $this->getJson(
        '/_model-explorer/api/models/'.recordsModelSlug(Post::class).'/record/attributes?record_key='.$post->id.'&names[]=summary&names[]=doesNotExist'
    )->assertOk();

    expect($response->json())->toHaveKey('summary')
        ->and($response->json())->not->toHaveKey('doesNotExist');
});

it('defaults to all appended attributes when no names are specified in the batch request', function () {
    app()->detectEnvironment(fn () => 'local');
    $post = Post::forceCreate(['title' => 'T', 'body' => 'B']);

    $response = $this->getJson(
        '/_model-explorer/api/models/'.recordsModelSlug(Post::class).'/record/attributes?record_key='.$post->id
    )->assertOk();

    expect($response->json())->toHaveKeys(['summary', 'excerpt']);
});

it('returns 422 when record_key is missing from the batch attribute endpoint', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->getJson(
        '/_model-explorer/api/models/'.recordsModelSlug(Post::class).'/record/attributes'
    )->assertUnprocessable();
});

it('returns 404 for an unknown attribute name on the attribute endpoint', function () {
    app()->detectEnvironment(fn () => 'local');
    $post = Post::forceCreate(['title' => 'T', 'body' => 'B']);

    $this->getJson(
        '/_model-explorer/api/models/'.recordsModelSlug(Post::class).'/record/attributes/nonExistentAttr?record_key='.$post->id
    )->assertNotFound();
});

it('returns 422 when record_key is missing from the attribute endpoint', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->getJson(
        '/_model-explorer/api/models/'.recordsModelSlug(Post::class).'/record/attributes/summary'
    )->assertUnprocessable();
});

it('uses the primary key field by default when field is omitted', function () {
    app()->detectEnvironment(fn () => 'local');
    $post = Post::forceCreate(['title' => 'Default', 'body' => 'B']);

    $this->getJson('/_model-explorer/api/models/'.recordsModelSlug(Post::class).'/record?value='.$post->id)
        ->assertOk()
        ->assertJsonFragment(['key_value' => $post->id]);
});

it('returns 404 when the record is not found', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->getJson('/_model-explorer/api/models/'.recordsModelSlug(Post::class).'/record?field=id&value=99999')
        ->assertNotFound()
        ->assertJson(['message' => 'Record not found.']);
});

it('returns 422 when the value query param is missing', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->getJson('/_model-explorer/api/models/'.recordsModelSlug(Post::class).'/record?field=id')
        ->assertUnprocessable();
});

it('returns 404 for an invalid model slug on the record endpoint', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->getJson('/_model-explorer/api/models/'.recordsModelSlug('App\Models\Ghost').'/record?field=id&value=1')
        ->assertNotFound()
        ->assertJson(['message' => 'Model not found.']);
});

it('resolves a to-one relation and returns the related record', function () {
    app()->detectEnvironment(fn () => 'local');
    $user = User::forceCreate([]);
    $post = Post::forceCreate(['title' => 'T', 'body' => 'B', 'user_id' => $user->id]);

    $response = $this->getJson(
        '/_model-explorer/api/models/'.recordsModelSlug(Post::class).'/record/relations/user?record_key='.$post->id
    )->assertOk();

    expect($response->json('type'))->toBe('one')
        ->and($response->json('record.key_value'))->toBe($user->id)
        ->and($response->json('record.model_class'))->toBe(User::class);
});

it('returns null record for a to-one relation when no related record exists', function () {
    app()->detectEnvironment(fn () => 'local');
    $post = Post::forceCreate(['title' => 'T', 'body' => 'B']);

    $response = $this->getJson(
        '/_model-explorer/api/models/'.recordsModelSlug(Post::class).'/record/relations/user?record_key='.$post->id
    )->assertOk();

    expect($response->json('type'))->toBe('one')
        ->and($response->json('record'))->toBeNull();
});

it('resolves a to-many relation and returns paginated records', function () {
    app()->detectEnvironment(fn () => 'local');
    $user = User::forceCreate([]);
    Post::forceCreate(['title' => 'A', 'body' => 'B', 'user_id' => $user->id]);
    Post::forceCreate(['title' => 'C', 'body' => 'D', 'user_id' => $user->id]);

    $response = $this->getJson(
        '/_model-explorer/api/models/'.recordsModelSlug(User::class).'/record/relations/posts?record_key='.$user->id
    )->assertOk();

    expect($response->json('type'))->toBe('many')
        ->and($response->json('records'))->toHaveCount(2)
        ->and($response->json('total'))->toBe(2)
        ->and($response->json('per_page'))->toBe(15)
        ->and($response->json('current_page'))->toBe(1);
});

it('paginates to-many relations when requesting a specific page', function () {
    app()->detectEnvironment(fn () => 'local');
    $user = User::forceCreate([]);
    for ($i = 0; $i < 16; $i++) {
        Post::forceCreate(['title' => "Post {$i}", 'body' => 'B', 'user_id' => $user->id]);
    }

    $response = $this->getJson(
        '/_model-explorer/api/models/'.recordsModelSlug(User::class).'/record/relations/posts?record_key='.$user->id.'&page=2'
    )->assertOk();

    expect($response->json('current_page'))->toBe(2)
        ->and($response->json('records'))->toHaveCount(1)
        ->and($response->json('last_page'))->toBe(2);
});

it('returns 404 for an invalid relation name', function () {
    app()->detectEnvironment(fn () => 'local');
    $post = Post::forceCreate(['title' => 'T', 'body' => 'B']);

    $this->getJson(
        '/_model-explorer/api/models/'.recordsModelSlug(Post::class).'/record/relations/nonExistentRelation?record_key='.$post->id
    )->assertNotFound();
});

it('returns 422 when record_key is missing from the relation endpoint', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->getJson(
        '/_model-explorer/api/models/'.recordsModelSlug(Post::class).'/record/relations/user'
    )->assertUnprocessable();
});

it('returns 403 on the record endpoint when the gate denies access', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->getJson(
        '/_model-explorer/api/models/'.recordsModelSlug(Post::class).'/record?field=id&value=1'
    )->assertForbidden();
});
