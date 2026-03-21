<?php

use OneLearningCommunity\LaravelModelExplorer\Services\ModelDiscovery;

it('discovers all Eloquent model classes in configured paths', function () {
    $discovery = new ModelDiscovery();
    $models = $discovery->discoverAll();

    expect($models)->toContain(\Workbench\App\Models\Post::class)
        ->and($models)->toContain(\Workbench\App\Models\CustomTableModel::class)
        ->and($models)->toContain(\Workbench\App\Models\NoTimestampsModel::class);
});

it('skips non-model PHP classes in configured paths', function () {
    // AbstractBaseModel extends Model but is abstract — must not appear
    $discovery = new ModelDiscovery();
    $models = $discovery->discoverAll();

    expect($models)->not->toContain(\Workbench\App\Models\AbstractBaseModel::class);
});

it('skips abstract model classes in configured paths', function () {
    $discovery = new ModelDiscovery();
    $models = $discovery->discoverAll();

    expect($models)->not->toContain(\Workbench\App\Models\AbstractBaseModel::class);
});

it('skips paths that do not exist without crashing', function () {
    $discovery = new ModelDiscovery();
    $result = $discovery->discoverIn('/path/that/does/not/exist');

    expect($result)->toBeArray()->toBeEmpty();
});
