<?php

use OneLearningCommunity\LaravelModelExplorer\Services\ModelInspector;
use Workbench\App\Models\CustomTableModel;
use Workbench\App\Models\NoTimestampsModel;
use Workbench\App\Models\Post;

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
