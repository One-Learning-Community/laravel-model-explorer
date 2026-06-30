<?php

use OneLearningCommunity\LaravelModelExplorer\Services\SourceFingerprint;
use Workbench\App\Models\Post;

it('returns the mtime of the file declaring a class', function () {
    $fingerprint = new SourceFingerprint;
    $file = (new ReflectionClass(Post::class))->getFileName();

    expect($fingerprint->forClass(Post::class))->toBe((int) filemtime($file));
});

it('returns 0 when the class cannot be reflected', function () {
    $fingerprint = new SourceFingerprint;

    expect($fingerprint->forClass('Workbench\\App\\Models\\NoSuchModel'))->toBe(0);
});

it('changes the model-paths fingerprint when a model file is modified', function () {
    $fingerprint = new SourceFingerprint;
    $file = (new ReflectionClass(Post::class))->getFileName();
    $original = filemtime($file);

    $before = $fingerprint->forModelPaths();

    touch($file, $original + 10);
    $after = $fingerprint->forModelPaths();

    touch($file, $original);

    expect($after)->not->toBe($before)
        ->and($fingerprint->forModelPaths())->toBe($before);
});
