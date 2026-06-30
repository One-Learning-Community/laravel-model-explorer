<?php

use OneLearningCommunity\LaravelModelExplorer\Services\GraphBuilder;
use Workbench\App\Models\BrokenModel;
use Workbench\App\Models\Post;

it('builds a node per inspectable model with its relations', function () {
    $graph = app(GraphBuilder::class)->build();

    $post = collect($graph)->firstWhere('class', Post::class);

    expect($post)->not->toBeNull()
        ->and($post['short_name'])->toBe('Post')
        ->and($post['table'])->toBe('posts')
        ->and(collect($post['relations'])->pluck('name'))->toContain('user', 'author');
});

it('omits models that cannot be inspected', function () {
    $graph = app(GraphBuilder::class)->build();

    expect(collect($graph)->pluck('class'))
        ->not->toContain(BrokenModel::class);
});

it('carries each relation\'s source snippet through for trait-correct pointers', function () {
    $graph = app(GraphBuilder::class)->build();

    $post = collect($graph)->firstWhere('class', Post::class);
    $author = collect($post['relations'])->firstWhere('name', 'author');

    expect($author['snippet'])->not->toBeNull()
        ->and($author['snippet']['file'])->toContain('HasAuthor.php')
        ->and($author['snippet']['start_line'])->toBeInt();
});
