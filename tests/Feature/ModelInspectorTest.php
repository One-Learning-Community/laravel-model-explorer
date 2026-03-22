<?php

use OneLearningCommunity\LaravelModelExplorer\Data\RelationData;
use OneLearningCommunity\LaravelModelExplorer\Services\ModelInspector;
use Spatie\ModelInfo\Attributes\Attribute;
use Workbench\App\Models\Concerns\HasAuthor;
use Workbench\App\Models\Concerns\HasOwner;
use Workbench\App\Models\Concerns\HasPublishedState;
use Workbench\App\Models\BasePost;
use Workbench\App\Models\ExtendedPost;
use Workbench\App\Models\CustomTableModel;
use Workbench\App\Models\NoTimestampsModel;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

it('returns the correct database table name for a standard model', function () {
    $inspector = new ModelInspector();
    $data = $inspector->inspect(Post::class);

    expect($data->table)->toBe('posts');
});

it('returns the correct table name when the model overrides protected $table', function () {
    $inspector = new ModelInspector();
    $data = $inspector->inspect(CustomTableModel::class);

    expect($data->table)->toBe('custom_table');
});

it('returns fillable and guarded attributes', function () {
    $inspector = new ModelInspector();
    $data = $inspector->inspect(Post::class);

    expect($data->fillable)->toBe(['title', 'body', 'published_at'])
        ->and($data->guarded)->toBeArray();
});

it('returns hidden attributes', function () {
    $inspector = new ModelInspector();
    $data = $inspector->inspect(Post::class);

    expect($data->hidden)->toBe(['secret_key']);
});

it('returns casts with their target types', function () {
    $inspector = new ModelInspector();
    $data = $inspector->inspect(Post::class);

    expect($data->casts)
        ->toHaveKey('published_at')
        ->toHaveKey('is_published');

    expect($data->casts['published_at'])->toBe('datetime')
        ->and($data->casts['is_published'])->toBe('boolean');
});

it('returns appended attributes', function () {
    $inspector = new ModelInspector();
    $data = $inspector->inspect(Post::class);

    expect($data->appends)->toBe(['summary', 'excerpt']);
});

it('returns usesTimestamps true and column names for a standard model', function () {
    $inspector = new ModelInspector();
    $data = $inspector->inspect(Post::class);

    expect($data->usesTimestamps)->toBeTrue()
        ->and($data->createdAtColumn)->toBe('created_at')
        ->and($data->updatedAtColumn)->toBe('updated_at');
});

it('returns usesTimestamps false and null column names when timestamps are disabled', function () {
    $inspector = new ModelInspector();
    $data = $inspector->inspect(NoTimestampsModel::class);

    expect($data->usesTimestamps)->toBeFalse()
        ->and($data->createdAtColumn)->toBeNull()
        ->and($data->updatedAtColumn)->toBeNull();
});

it('returns a collection of Attribute objects with column metadata', function () {
    $inspector = new ModelInspector();
    $data = $inspector->inspect(Post::class);

    expect($data->attributes)->toBeInstanceOf(\Illuminate\Support\Collection::class)
        ->and($data->attributes->first())->toBeInstanceOf(Attribute::class);

    $titleAttr = $data->attributes->firstWhere('name', 'title');
    expect($titleAttr)->not->toBeNull()
        ->and($titleAttr->fillable)->toBeTrue()
        ->and($titleAttr->nullable)->toBeFalse();

    $secretAttr = $data->attributes->firstWhere('name', 'secret_key');
    expect($secretAttr)->not->toBeNull()
        ->and($secretAttr->hidden)->toBeTrue();

    $publishedAtAttr = $data->attributes->firstWhere('name', 'published_at');
    expect($publishedAtAttr)->not->toBeNull()
        ->and($publishedAtAttr->cast)->toBe('datetime');
});

it('returns virtual attributes with appended flag set', function () {
    $inspector = new ModelInspector();
    $data = $inspector->inspect(Post::class);

    $summaryAttr = $data->attributes->firstWhere('name', 'summary');
    expect($summaryAttr)->not->toBeNull()
        ->and($summaryAttr->virtual)->toBeTrue()
        ->and($summaryAttr->appended)->toBeTrue();
});

it('returns a collection of RelationData objects', function () {
    $inspector = new ModelInspector();
    $data = $inspector->inspect(Post::class);

    expect($data->relations)->toBeInstanceOf(\Illuminate\Support\Collection::class);

    $userRelation = $data->relations->firstWhere('name', 'user');
    expect($userRelation)->not->toBeNull()
        ->and($userRelation)->toBeInstanceOf(RelationData::class)
        ->and($userRelation->type)->toBe('BelongsTo')
        ->and($userRelation->related)->toBe(User::class);
});

it('returns a hasMany relation for the user model', function () {
    $inspector = new ModelInspector();
    $data = $inspector->inspect(User::class);

    $postsRelation = $data->relations->firstWhere('name', 'posts');
    expect($postsRelation)->not->toBeNull()
        ->and($postsRelation->type)->toBe('HasMany')
        ->and($postsRelation->related)->toBe(Post::class);
});

it('extracts foreign key and local key for a belongsTo relation', function () {
    $inspector = new ModelInspector();
    $data = $inspector->inspect(Post::class);

    $userRelation = $data->relations->firstWhere('name', 'user');
    expect($userRelation->foreignKey)->toBe('user_id')
        ->and($userRelation->localKey)->toBe('id');
});

it('extracts foreign key and local key for a hasMany relation', function () {
    $inspector = new ModelInspector();
    $data = $inspector->inspect(User::class);

    $postsRelation = $data->relations->firstWhere('name', 'posts');
    expect($postsRelation->foreignKey)->toBe('user_id')
        ->and($postsRelation->localKey)->toBe('id');
});

it('returns non-Illuminate traits used by the model', function () {
    $inspector = new ModelInspector();
    $data = $inspector->inspect(Post::class);

    expect($data->traits)->toContain(HasPublishedState::class);
});

it('excludes internal Illuminate concern traits from the traits list', function () {
    $inspector = new ModelInspector();
    $data = $inspector->inspect(Post::class);

    $concernTraits = array_filter($data->traits, fn (string $t) => str_starts_with($t, 'Illuminate\Database\Eloquent\Concerns\\'));
    expect($concernTraits)->toBeEmpty();
});

it('respects custom excluded_trait_prefixes from config', function () {
    $original = config('model-explorer.excluded_trait_prefixes');
    config()->set('model-explorer.excluded_trait_prefixes', [
        'Workbench\App\Models\Concerns\\',
    ]);

    $inspector = new ModelInspector();
    $data = $inspector->inspect(Post::class);

    config()->set('model-explorer.excluded_trait_prefixes', $original);

    expect($data->traits)->not->toContain(HasPublishedState::class)
        ->and($data->traits)->not->toContain(HasAuthor::class);
});

it('sets definedIn to null for relations defined directly on the model', function () {
    $inspector = new ModelInspector();
    $data = $inspector->inspect(Post::class);

    $userRelation = $data->relations->firstWhere('name', 'user');
    expect($userRelation->definedIn)->toBeNull();
});

it('sets definedIn to the trait FQCN for relations sourced from a trait', function () {
    $inspector = new ModelInspector();
    $data = $inspector->inspect(Post::class);

    $authorRelation = $data->relations->firstWhere('name', 'author');
    expect($authorRelation)->not->toBeNull()
        ->and($authorRelation->definedIn)->toBe(HasAuthor::class);
});

it('returns scopes defined on a model', function () {
    $inspector = new ModelInspector();
    $data = $inspector->inspect(Post::class);

    $scope = $data->scopes->firstWhere('name', 'published');
    expect($scope)->not->toBeNull()
        ->and($scope->definedIn)->toBe(HasPublishedState::class);
});

it('returns an empty scopes collection for a model with no scopes', function () {
    $inspector = new ModelInspector();
    $data = $inspector->inspect(User::class);

    expect($data->scopes)->toBeEmpty();
});

it('returns scopes sorted alphabetically', function () {
    $inspector = new ModelInspector();
    $data = $inspector->inspect(Post::class);

    $names = $data->scopes->pluck('name')->all();
    $sorted = $names;
    sort($sorted);
    expect($names)->toBe($sorted);
});

it('captures parameters for a scope with typed and defaulted arguments', function () {
    $inspector = new ModelInspector();
    $data = $inspector->inspect(Post::class);

    $scope = $data->scopes->firstWhere('name', 'recent');
    expect($scope)->not->toBeNull()
        ->and($scope->parameters)->toHaveCount(2)
        ->and($scope->parameters[0])->toMatchArray(['name' => 'days', 'type' => 'int', 'has_default' => true, 'default' => '30'])
        ->and($scope->parameters[1])->toMatchArray(['name' => 'published', 'type' => 'bool', 'has_default' => true, 'default' => 'true']);
});

it('captures an empty parameters array for a no-arg scope', function () {
    $inspector = new ModelInspector();
    $data = $inspector->inspect(Post::class);

    $scope = $data->scopes->firstWhere('name', 'published');
    expect($scope)->not->toBeNull()
        ->and($scope->parameters)->toBe([]);
});

it('captures a source snippet for a scope', function () {
    $inspector = new ModelInspector();
    $data = $inspector->inspect(Post::class);

    $scope = $data->scopes->firstWhere('name', 'recent');
    expect($scope)->not->toBeNull()
        ->and($scope->snippet)->not->toBeNull()
        ->and($scope->snippet['code'])->toContain('scopeRecent')
        ->and($scope->snippet['start_line'])->toBeInt();
});

it('sets definedIn to the parent class FQCN for a scope declared directly on a parent', function () {
    $inspector = new ModelInspector();
    $data = $inspector->inspect(ExtendedPost::class);

    $scope = $data->scopes->firstWhere('name', 'draft');
    expect($scope)->not->toBeNull()
        ->and($scope->definedIn)->toBe(BasePost::class);
});

it('sets definedIn to null for a scope declared directly on the model itself', function () {
    $inspector = new ModelInspector();
    $data = $inspector->inspect(Post::class);

    $scope = $data->scopes->firstWhere('name', 'recent');
    expect($scope)->not->toBeNull()
        ->and($scope->definedIn)->toBeNull();
});

it('identifies the correct trait for a relation defined in a parent class trait', function () {
    $inspector = new ModelInspector();
    $data = $inspector->inspect(ExtendedPost::class);

    $ownerRelation = $data->relations->firstWhere('name', 'owner');
    expect($ownerRelation)->not->toBeNull()
        ->and($ownerRelation->definedIn)->toBe(HasOwner::class);
});

it('returns an empty traits array for a model with no custom traits', function () {
    $inspector = new ModelInspector();
    $data = $inspector->inspect(User::class);

    expect($data->traits)->toBeArray()->toBeEmpty();
});

it('discovers relations without a declared return type via source scanning', function () {
    $inspector = new ModelInspector();
    $data = $inspector->inspect(Post::class);

    $ownerRelation = $data->relations->firstWhere('name', 'owner');
    expect($ownerRelation)->not->toBeNull()
        ->and($ownerRelation->type)->toBe('BelongsTo')
        ->and($ownerRelation->related)->toBe(User::class);
});

it('extracts a snippet for an old-style accessor', function () {
    $inspector = new ModelInspector();
    $data = $inspector->inspect(Post::class);

    expect($data->accessorSnippets)->toHaveKey('summary');
    expect($data->accessorSnippets['summary']['code'])->toContain('getSummaryAttribute');
    expect($data->accessorSnippets['summary'])->toHaveKey('file');
    expect($data->accessorSnippets['summary'])->toHaveKey('start_line');
});

it('extracts a snippet for a new-style Attribute::make() accessor', function () {
    $inspector = new ModelInspector();
    $data = $inspector->inspect(Post::class);

    expect($data->accessorSnippets)->toHaveKey('excerpt');
    expect($data->accessorSnippets['excerpt']['code'])->toContain('Attribute::make');
    expect($data->accessorSnippets['excerpt'])->toHaveKey('file');
    expect($data->accessorSnippets['excerpt'])->toHaveKey('start_line');
});

it('does not include a snippet for non-virtual (database) columns', function () {
    $inspector = new ModelInspector();
    $data = $inspector->inspect(Post::class);

    expect($data->accessorSnippets)->not->toHaveKey('title');
    expect($data->accessorSnippets)->not->toHaveKey('body');
});

it('does not treat an untyped non-relation method as a relation', function () {
    // Post::activate() has no return type and no relation call — must not appear in relations.
    $inspector = new ModelInspector();
    $data = $inspector->inspect(Post::class);

    $activateRelation = $data->relations->firstWhere('name', 'activate');
    expect($activateRelation)->toBeNull();
});

it('extracts a description from the PHPDoc summary of a scope', function () {
    $inspector = new ModelInspector();
    $data = $inspector->inspect(Post::class);

    $scope = $data->scopes->firstWhere('name', 'recent');
    expect($scope->description)->toBe('Posts created within the given number of days.');
});

it('includes the docblock in the scope source snippet', function () {
    $inspector = new ModelInspector();
    $data = $inspector->inspect(Post::class);

    $scope = $data->scopes->firstWhere('name', 'recent');
    expect($scope->snippet)->not->toBeNull()
        ->and($scope->snippet['code'])->toContain('Posts created within the given number of days.')
        ->and($scope->snippet['code'])->toContain('scopeRecent');
});

it('returns null description for a scope with no PHPDoc summary', function () {
    $inspector = new ModelInspector();
    $data = $inspector->inspect(Post::class);

    // The published scope (defined in HasPublishedState) has no docblock in the workbench fixture.
    $scope = $data->scopes->firstWhere('name', 'published');
    expect($scope)->not->toBeNull()
        ->and($scope->description)->toBeNull();
});

it('extracts a description from the PHPDoc summary of a relation', function () {
    $inspector = new ModelInspector();
    $data = $inspector->inspect(Post::class);

    $relation = $data->relations->firstWhere('name', 'user');
    expect($relation->description)->toBe('The user who authored this post.');
});

it('extracts a source snippet for a relation method', function () {
    $inspector = new ModelInspector();
    $data = $inspector->inspect(Post::class);

    $relation = $data->relations->firstWhere('name', 'user');
    expect($relation->snippet)->not->toBeNull()
        ->and($relation->snippet['code'])->toContain('belongsTo')
        ->and($relation->snippet['code'])->toContain('The user who authored this post.');
});

it('returns null description and null snippet for a relation with no PHPDoc', function () {
    $inspector = new ModelInspector();
    $data = $inspector->inspect(Post::class);

    // The owner relation has no docblock.
    $relation = $data->relations->firstWhere('name', 'owner');
    expect($relation)->not->toBeNull()
        ->and($relation->description)->toBeNull()
        ->and($relation->snippet['doc_summary'])->toBeNull();
});
