<?php

use Illuminate\Support\Facades\Artisan;
use OneLearningCommunity\LaravelModelExplorer\Data\ModelData;
use Workbench\App\Models\Post;

it('emits a marker-wrapped serialized ModelData payload', function () {
    Artisan::call('model-explorer:inspect', ['class' => Post::class]);
    $output = Artisan::output();

    expect(preg_match('/<<<MEX>>>(.*?)<<<\/MEX>>>/s', $output, $matches))->toBe(1);

    $data = unserialize(base64_decode($matches[1]));

    expect($data)->toBeInstanceOf(ModelData::class)
        ->and($data->className)->toBe(Post::class)
        ->and($data->table)->toBe('posts')
        ->and($data->relations->firstWhere('name', 'user'))->not->toBeNull();
});

it('exits non-zero for a model that cannot be inspected', function () {
    $code = Artisan::call('model-explorer:inspect', ['class' => 'Workbench\\App\\Models\\NoSuchModel']);

    expect($code)->not->toBe(0);
});
