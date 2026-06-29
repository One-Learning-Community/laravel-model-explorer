<?php

use OneLearningCommunity\LaravelModelExplorer\Mcp\Support\CompactPresenter;
use OneLearningCommunity\LaravelModelExplorer\Services\ModelInspector;
use Workbench\App\Models\Post;

function presentPost(): array
{
    $data = app(ModelInspector::class)->inspect(Post::class);

    return [app(CompactPresenter::class), $data];
}

it('overview carries class, table, key and section counts', function () {
    [$p, $data] = presentPost();
    $overview = $p->overview($data);

    expect($overview['name'])->toBe('Post')
        ->and($overview['table'])->toBe('posts')
        ->and($overview['counts'])->toHaveKeys(['columns', 'relations', 'scopes', 'accessors', 'traits'])
        ->and($overview['counts']['relations'])->toBeGreaterThanOrEqual(3);
});

it('renders columns as terse strings with PK and FK annotations', function () {
    [$p, $data] = presentPost();
    $columns = $p->columns($data);

    expect(collect($columns)->first(fn ($c) => str_starts_with($c, 'id:')))->toContain('PK')
        ->and(collect($columns)->contains(fn ($c) => str_contains($c, 'FK→User')))->toBeTrue();
});

it('renders relations with type, related, via and a defined_in pointer', function () {
    [$p, $data] = presentPost();
    $author = collect($p->relations($data))->firstWhere('name', 'author');

    expect($author['type'])->toBe('belongsTo')
        ->and($author['related'])->toBe('User')
        ->and($author['via'])->toBe('author_id')
        ->and($author['defined_in'])->toContain('HasAuthor.php:');
});

it('renders scope signatures with parameters and trait-correct pointers', function () {
    [$p, $data] = presentPost();
    $published = collect($p->scopes($data))->firstWhere('name', 'published');
    $recent = collect($p->scopes($data))->firstWhere('name', 'recent');

    expect($published['defined_in'])->toContain('HasPublishedState.php:')
        ->and($recent['signature'])->toBe('recent(int $days = 30, bool $published = true)');
});

it('inspect() returns overview plus only the requested sections', function () {
    [$p, $data] = presentPost();
    $out = $p->inspect($data, ['columns']);

    expect($out)->toHaveKeys(['class', 'counts', 'columns'])
        ->and($out)->not->toHaveKey('relations')
        ->and($out)->not->toHaveKey('scopes');
});

it('pointer renders paths relative to base_path', function () {
    [$p, $data] = presentPost();
    $pointer = $p->pointer(['file' => base_path('app/Models/Foo.php'), 'start_line' => 12]);

    expect($pointer)->toBe('app/Models/Foo.php:12');
});
