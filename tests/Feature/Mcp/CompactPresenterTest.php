<?php

use OneLearningCommunity\LaravelModelExplorer\Mcp\Support\CompactPresenter;
use OneLearningCommunity\LaravelModelExplorer\Services\ModelInspector;
use Workbench\App\Factories\WidgetLegacyFactory;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Country;
use Workbench\App\Models\IndexedRecord;
use Workbench\App\Models\Post;
use Workbench\App\Models\Tag;
use Workbench\App\Models\Widget;

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

it('includes the factory in the overview when one exists', function () {
    $p = app(CompactPresenter::class);
    $data = app(ModelInspector::class)->inspect(Widget::class);
    $overview = $p->overview($data);

    expect($overview['factory']['class'])->toBe(WidgetLegacyFactory::class)
        ->and($overview['factory']['defined_in'])->toContain('WidgetLegacyFactory.php:');
});

it('omits the factory from the overview when the model has none', function () {
    // Post does not use HasFactory, so it has no factory() method.
    [$p, $data] = presentPost();

    expect($p->overview($data))->not->toHaveKey('factory');
});

it('renders columns as terse strings with PK and FK annotations', function () {
    [$p, $data] = presentPost();
    $columns = $p->columns($data);

    expect(collect($columns)->first(fn ($c) => str_starts_with($c, 'id:')))->toContain('PK')
        ->and(collect($columns)->contains(fn ($c) => str_contains($c, 'FK→User')))->toBeTrue();
});

it('expands an enum cast inline in the column string as Name=value pairs', function () {
    [$p, $data] = presentPost();
    $status = collect($p->columns($data))->first(fn ($c) => str_starts_with($c, 'status:'));

    expect($status)->toContain('cast:PostStatus(Draft=draft, Published=published, Archived=archived)');
});

it('caps a wide enum at the case limit with an overflow suffix', function () {
    $p = app(CompactPresenter::class);

    $cases = collect(range(1, 15))
        ->map(fn ($i) => ['name' => "Case{$i}", 'value' => "v{$i}"])
        ->all();

    $rendered = $p->formatEnumCases($cases);

    expect($rendered)->toContain('Case1=v1')
        ->and($rendered)->toContain('Case'.CompactPresenter::ENUM_CASE_LIMIT)
        ->and($rendered)->not->toContain('Case13')
        ->and($rendered)->toEndWith(' …+3 more');
});

it('annotates a non-unique indexed column with "indexed"', function () {
    [$p, $data] = presentPost();
    $publishedAt = collect($p->columns($data))->first(fn ($c) => str_starts_with($c, 'published_at:'));

    expect($publishedAt)->toContain('indexed');
});

it('does not annotate the primary key or plain columns with "indexed"', function () {
    [$p, $data] = presentPost();
    $columns = collect($p->columns($data));

    expect($columns->first(fn ($c) => str_starts_with($c, 'id:')))->not->toContain('indexed')
        ->and($columns->first(fn ($c) => str_starts_with($c, 'title:')))->not->toContain('indexed');
});

it('annotates composite index position instead of blanket-indexed', function () {
    $p = app(CompactPresenter::class);
    $data = app(ModelInspector::class)->inspect(IndexedRecord::class);
    $columns = collect($p->columns($data));

    $col = fn (string $name) => $columns->first(fn ($c) => str_starts_with($c, $name.':'));

    expect($col('a'))->toContain('indexed')->not->toContain('indexed(')
        ->and($col('b'))->toContain('indexed(composite-leading)')
        ->and($col('c'))->toContain('indexed(composite-2of3)')
        ->and($col('d'))->toContain('indexed(composite-3of3)');
});

it('omits the enum parenthetical entirely when the limit is 0', function () {
    [$p, $data] = presentPost();
    $status = collect($p->columns($data, 0))->first(fn ($c) => str_starts_with($c, 'status:'));

    expect($status)->toEndWith('cast:PostStatus')
        ->and($status)->not->toContain('(');
});

it('caps enum cases at an explicit limit', function () {
    [$p, $data] = presentPost();
    $status = collect($p->columns($data, 2))->first(fn ($c) => str_starts_with($c, 'status:'));

    expect($status)->toContain('cast:PostStatus(Draft=draft, Published=published …+1 more');
});

it('formatEnumCases returns an empty string when the limit is 0', function () {
    $p = app(CompactPresenter::class);

    expect($p->formatEnumCases([['name' => 'Draft', 'value' => 'draft']], 0))->toBe('');
});

it('renders pure enum cases as names only', function () {
    $p = app(CompactPresenter::class);

    $rendered = $p->formatEnumCases([
        ['name' => 'Low', 'value' => null],
        ['name' => 'High', 'value' => null],
    ]);

    expect($rendered)->toBe('Low, High');
});

it('renders relations with type, related, via and a defined_in pointer', function () {
    [$p, $data] = presentPost();
    $author = collect($p->relations($data))->firstWhere('name', 'author');

    expect($author['type'])->toBe('belongsTo')
        ->and($author['related'])->toBe('User')
        ->and($author['via'])->toBe('author_id')
        ->and($author['defined_in'])->toContain('HasAuthor.php:');
});

it('renders pivot detail on a belongsToMany relation', function () {
    $p = app(CompactPresenter::class);
    $data = app(ModelInspector::class)->inspect(Tag::class);
    $videos = collect($p->relations($data))->firstWhere('name', 'videos');

    expect($videos['pivot'])->toBe('tag_video')
        ->and($videos['pivot_keys'])->toBe(['tag_id', 'video_id'])
        ->and($videos['pivot_columns'])->toBe(['sort_order']);
});

it('renders the morph type on a polymorphic relation', function () {
    $p = app(CompactPresenter::class);
    $data = app(ModelInspector::class)->inspect(Comment::class);
    $commentable = collect($p->relations($data))->firstWhere('name', 'commentable');

    expect($commentable['morph_type'])->toBe('commentable_type');
});

it('renders the through model and key on a hasManyThrough relation', function () {
    $p = app(CompactPresenter::class);
    $data = app(ModelInspector::class)->inspect(Country::class);
    $posts = collect($p->relations($data))->firstWhere('name', 'posts');

    expect($posts['through'])->toBe('User')
        ->and($posts['through_key'])->toBe('country_id');
});

it('omits pivot, morph and through keys on a plain relation', function () {
    [$p, $data] = presentPost();
    $user = collect($p->relations($data))->firstWhere('name', 'user');

    expect($user)->not->toHaveKey('pivot')
        ->and($user)->not->toHaveKey('morph_type')
        ->and($user)->not->toHaveKey('through');
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

it('renders members split into methods and properties with kind and pointer', function () {
    [$p, $data] = presentPost();
    $members = $p->members($data);

    expect($members)->toHaveKeys(['methods', 'properties'])
        ->and(collect($members['methods'])->contains(fn ($m) => str_contains($m, 'activate() [business] @ ')))->toBeTrue()
        ->and(collect($members['methods'])->contains(fn ($m) => str_contains($m, '[relation]') && str_contains($m, 'HasAuthor.php:')))->toBeTrue()
        ->and(collect($members['properties'])->contains(fn ($m) => str_contains($m, '$fillable [config]')))->toBeTrue();
});

it('filters members to the given kinds', function () {
    [$p, $data] = presentPost();
    $members = $p->members($data, ['kinds' => ['business']]);

    expect(collect($members['methods'])->contains(fn ($m) => str_contains($m, '[business]')))->toBeTrue()
        ->and(collect($members['methods'])->contains(fn ($m) => str_contains($m, '[relation]')))->toBeFalse();
});

it('filters members to a declaring-file substring', function () {
    [$p, $data] = presentPost();
    $members = $p->members($data, ['file' => 'HasAuthor.php']);

    expect(collect($members['methods'])->every(fn ($m) => str_contains($m, 'HasAuthor.php')))->toBeTrue()
        ->and($members['methods'])->not->toBeEmpty();
});

it('counts members in the overview header', function () {
    [$p, $data] = presentPost();

    expect($p->overview($data)['counts']['members'])->toBeGreaterThan(0);
});

it('pointer renders paths relative to base_path', function () {
    [$p, $data] = presentPost();
    $pointer = $p->pointer(['file' => base_path('app/Models/Foo.php'), 'start_line' => 12]);

    expect($pointer)->toBe('app/Models/Foo.php:12');
});

it('pointer omits the line when the declaration line is unknown', function () {
    [$p] = presentPost();
    $pointer = $p->pointer(['file' => base_path('app/Models/Foo.php'), 'start_line' => null]);

    expect($pointer)->toBe('app/Models/Foo.php');
});
