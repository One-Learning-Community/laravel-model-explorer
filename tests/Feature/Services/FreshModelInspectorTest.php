<?php

use OneLearningCommunity\LaravelModelExplorer\Data\ModelData;
use OneLearningCommunity\LaravelModelExplorer\Services\FreshModelInspector;
use OneLearningCommunity\LaravelModelExplorer\Services\ModelInspector;
use OneLearningCommunity\LaravelModelExplorer\Services\SourceFingerprint;
use OneLearningCommunity\LaravelModelExplorer\Services\SubprocessInspector;
use Workbench\App\Models\Post;

/**
 * @return array{0: FreshModelInspector, 1: stdClass}
 */
function freshInspectorWithCounters(): array
{
    $counts = new stdClass;
    $counts->inProcess = 0;
    $counts->subprocess = 0;

    $inProcess = new class($counts) extends ModelInspector
    {
        public function __construct(private stdClass $counts) {}

        public function inspect(string $className): ModelData
        {
            $this->counts->inProcess++;

            return (new ModelInspector)->inspect($className);
        }
    };

    $subprocess = new class($counts) extends SubprocessInspector
    {
        public function __construct(private stdClass $counts) {}

        public function inspect(string $className): ModelData
        {
            $this->counts->subprocess++;

            return (new ModelInspector)->inspect($className);
        }
    };

    return [new FreshModelInspector($inProcess, $subprocess, new SourceFingerprint), $counts];
}

it('uses the in-process inspector when the model file is unchanged', function () {
    [$fresh, $counts] = freshInspectorWithCounters();

    $fresh->inspect(Post::class);
    $fresh->inspect(Post::class);

    expect($counts->inProcess)->toBe(2)
        ->and($counts->subprocess)->toBe(0);
});

it('routes to a fresh subprocess once the model file changes after first load', function () {
    [$fresh, $counts] = freshInspectorWithCounters();
    $file = (new ReflectionClass(Post::class))->getFileName();
    $original = filemtime($file);

    $fresh->inspect(Post::class);
    expect($counts->subprocess)->toBe(0);

    touch($file, $original + 10);
    $fresh->inspect(Post::class);
    touch($file, $original);

    expect($counts->subprocess)->toBe(1)
        ->and($counts->inProcess)->toBe(1);
});
