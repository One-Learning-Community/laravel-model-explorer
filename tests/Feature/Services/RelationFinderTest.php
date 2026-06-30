<?php

use OneLearningCommunity\LaravelModelExplorer\Services\RelationFinder;
use Workbench\App\Models\Post;
use Workbench\App\Models\ThrowingRelationModel;

it('still reports a relation whose method throws when invoked on a blank model', function () {
    $relations = RelationFinder::forModel(ThrowingRelationModel::class);

    $relation = $relations->firstWhere('name', 'publishedPosts');

    expect($relation)->not->toBeNull()
        ->and(class_basename($relation->type))->toBe('HasMany')
        ->and($relation->related)->toBe(Post::class);
});

it('reports an untyped relation whose method throws, deriving its type from source', function () {
    $relations = RelationFinder::forModel(ThrowingRelationModel::class);

    $relation = $relations->firstWhere('name', 'archivedPosts');

    expect($relation)->not->toBeNull()
        ->and(class_basename($relation->type))->toBe('HasMany')
        ->and($relation->related)->toBe(Post::class);
});

it('still reports clean relations defined alongside a throwing one', function () {
    $relations = RelationFinder::forModel(ThrowingRelationModel::class);

    expect($relations->firstWhere('name', 'posts'))->not->toBeNull();
});
