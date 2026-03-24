<?php

use Illuminate\Support\Facades\Gate;
use OneLearningCommunity\LaravelModelExplorer\Services\ModelDiscovery;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

it('returns a 200 ok json array for the graph endpoint', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->getJson('/_model-explorer/api/graph')
        ->assertOk()
        ->assertJsonIsArray();
});

it('returns model class info and relations when models are discovered', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->mock(ModelDiscovery::class)
        ->shouldReceive('discoverAll')
        ->andReturn([Post::class, User::class]);

    $response = $this->getJson('/_model-explorer/api/graph')->assertOk();

    $json = $response->json();
    expect($json)->toBeArray()->toHaveCount(2);

    $postEntry = collect($json)->firstWhere('class', Post::class);
    expect($postEntry)->not->toBeNull()
        ->and($postEntry['short_name'])->toBe('Post')
        ->and($postEntry['table'])->toBe('posts')
        ->and($postEntry['relations'])->toBeArray();
});

it('includes relation type and related class in graph payload', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->mock(ModelDiscovery::class)
        ->shouldReceive('discoverAll')
        ->andReturn([Post::class]);

    $response = $this->getJson('/_model-explorer/api/graph')->assertOk();

    $postEntry = collect($response->json())->firstWhere('class', Post::class);
    $userRelation = collect($postEntry['relations'])->firstWhere('name', 'user');

    expect($userRelation)->not->toBeNull()
        ->and($userRelation['type'])->toBe('BelongsTo')
        ->and($userRelation['related'])->toBe(User::class);
});

it('returns 403 on the graph endpoint when the gate denies access', function () {
    $this->getJson('/_model-explorer/api/graph')
        ->assertForbidden();
});

it('allows gate override to grant access to graph endpoint in non-local environment', function () {
    Gate::define('viewModelExplorer', fn ($user = null) => true);

    $this->getJson('/_model-explorer/api/graph')
        ->assertOk()
        ->assertJsonIsArray();
});
