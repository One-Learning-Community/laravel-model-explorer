<?php

use OneLearningCommunity\LaravelModelExplorer\Data\ModelData;
use OneLearningCommunity\LaravelModelExplorer\Services\ModelInspector;
use OneLearningCommunity\LaravelModelExplorer\Services\SubprocessInspector;
use Workbench\App\Models\Post;

it('parses a marker-wrapped serialized payload into a ModelData', function () {
    $real = app(ModelInspector::class)->inspect(Post::class);
    $output = 'boot noise<<<MEX>>>'.base64_encode(serialize($real)).'<<</MEX>>>trailing';

    $sub = new class($output) extends SubprocessInspector
    {
        public function __construct(private string $output) {}

        protected function run(string $className): array
        {
            return [true, $this->output, ''];
        }
    };

    $data = $sub->inspect(Post::class);

    expect($data)->toBeInstanceOf(ModelData::class)
        ->and($data->table)->toBe('posts');
});

it('throws when the subprocess reports failure', function () {
    $sub = new class extends SubprocessInspector
    {
        protected function run(string $className): array
        {
            return [false, '', 'boom'];
        }
    };

    expect(fn () => $sub->inspect('X'))->toThrow(RuntimeException::class, 'boom');
});

it('throws when the payload cannot be read', function () {
    $sub = new class extends SubprocessInspector
    {
        protected function run(string $className): array
        {
            return [true, 'no markers here', ''];
        }
    };

    expect(fn () => $sub->inspect('X'))->toThrow(RuntimeException::class);
});
