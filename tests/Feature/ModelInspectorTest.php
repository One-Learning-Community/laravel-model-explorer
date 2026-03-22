<?php

use OneLearningCommunity\LaravelModelExplorer\Data\RelationData;
use OneLearningCommunity\LaravelModelExplorer\Services\ModelInspector;
use Spatie\ModelInfo\Attributes\Attribute;
use Workbench\App\Models\Concerns\HasAuthor;
use Workbench\App\Models\Concerns\HasOwner;
use Workbench\App\Models\Concerns\HasPublishedState;
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

    expect($data->appends)->toBe(['summary']);
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
