<?php

use OneLearningCommunity\LaravelModelExplorer\Services\ModelDiscovery;
use Workbench\App\Models\AbstractBaseModel;
use Workbench\App\Models\CustomTableModel;
use Workbench\App\Models\NoTimestampsModel;
use Workbench\App\Models\Post;

it('discovers all Eloquent model classes in configured paths', function () {
    $discovery = new ModelDiscovery;
    $models = $discovery->discoverAll();

    expect($models)->toContain(Post::class)
        ->and($models)->toContain(CustomTableModel::class)
        ->and($models)->toContain(NoTimestampsModel::class);
});

it('skips non-model PHP classes in configured paths', function () {
    // AbstractBaseModel extends Model but is abstract — must not appear
    $discovery = new ModelDiscovery;
    $models = $discovery->discoverAll();

    expect($models)->not->toContain(AbstractBaseModel::class);
});

it('skips abstract model classes in configured paths', function () {
    $discovery = new ModelDiscovery;
    $models = $discovery->discoverAll();

    expect($models)->not->toContain(AbstractBaseModel::class);
});

it('skips paths that do not exist without crashing', function () {
    $discovery = new ModelDiscovery;
    $result = $discovery->discoverIn('/path/that/does/not/exist');

    expect($result)->toBeArray()->toBeEmpty();
});
