<?php

namespace OneLearningCommunity\LaravelModelExplorer\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
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
        $paths = config('model-explorer.model_paths');
        $excluded = config('model-explorer.excluded_models', []);

        return collect($paths)
            ->sort()
            ->flatMap(fn (string $path, string $namespace) => $this->discoverIn($path, $namespace))
            ->reject(fn (string $className) => $this->isExcluded($className, $excluded))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Determine whether a class matches any of the configured exclusion patterns.
     *
     * Patterns are matched against the fully-qualified class name and may use `*`
     * as a wildcard (e.g. 'Laravel\Telescope\*'). Leading backslashes are ignored.
     *
     * @param  list<string>  $patterns
     */
    private function isExcluded(string $className, array $patterns): bool
    {
        if ($patterns === []) {
            return false;
        }

        $normalized = ltrim($className, '\\');
        $patterns = array_map(fn (string $pattern) => ltrim($pattern, '\\'), $patterns);

        return Str::is($patterns, $normalized);
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
