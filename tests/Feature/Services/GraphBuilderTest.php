<?php

use OneLearningCommunity\LaravelModelExplorer\Services\GraphBuilder;

it('builds a node per inspectable model with its relations', function () {
    $graph = app(GraphBuilder::class)->build();

    $post = collect($graph)->firstWhere('class', \Workbench\App\Models\Post::class);

    expect($post)->not->toBeNull()
        ->and($post['short_name'])->toBe('Post')
        ->and($post['table'])->toBe('posts')
        ->and(collect($post['relations'])->pluck('name'))->toContain('user', 'author');
});

it('omits models that cannot be inspected', function () {
    $graph = app(GraphBuilder::class)->build();

    expect(collect($graph)->pluck('class'))
        ->not->toContain(\Workbench\App\Models\BrokenModel::class);
});
