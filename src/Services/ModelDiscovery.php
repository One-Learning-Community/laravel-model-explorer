<?php

namespace OneLearningCommunity\LaravelModelExplorer\Services;

use Illuminate\Database\Eloquent\Model;
use Spatie\ModelInfo\ModelFinder;

class ModelDiscovery
{
    /**
     * Discover all concrete Eloquent model classes across all configured paths.
     *
     * Config format: associative array of namespace => absolute path.
     * e.g. ['App\\Models' => '/path/to/app/Models']
     *
     * @return list<class-string<Model>>
     */
    public function discoverAll(): array
    {
        $paths = config('model-explorer.model_paths', [app_path('Models')]);

        return collect($paths)
            ->sort()
            ->flatMap(fn (string $path, string $namespace) => $this->discoverIn($path, $namespace))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<class-string<Model>>
     */
    public function discoverIn(string $path, string $namespace = ''): array
    {
        if (! is_dir($path)) {
            return [];
        }

        $realPath = realpath($path) ?: $path;

        return ModelFinder::all(
            directory: $realPath,
            basePath: $realPath,
            baseNamespace: $namespace,
        )->all();
    }
}
