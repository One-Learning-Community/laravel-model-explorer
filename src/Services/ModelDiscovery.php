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
     * Namespace resolution assumes configured paths fall under app_path() and map
     * to the application's root namespace (app()->getNamespace()). Paths outside
     * app_path() are not supported in v1 — see model_paths config documentation.
     *
     * @return list<class-string<Model>>
     */
    public function discoverAll(): array
    {
        $paths = config('model-explorer.model_paths', [app_path('Models')]);

        return collect($paths)
            ->sort()
            ->flatMap(fn (string $path, string $namespace) => $this->discoverIn($namespace, $path))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<class-string<Model>>
     */
    public function discoverIn(string $namespace, string $path): array
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

    private function resolveBaseNamespace(string $basePath): string
    {
        // Normalize the path to resolve any symlinks or relative segments (e.g. `..`).
        $basePath = realpath($basePath) ?: $basePath;

        // For paths under app_path(), use the application's root namespace.
        if (Str::startsWith($basePath, app_path())) {
            $relative = Str::after($basePath, app_path());
            $appNamespace = rtrim(app()->getNamespace(), '\\');

            if ($relative === '' || $relative === DIRECTORY_SEPARATOR) {
                return $appNamespace;
            }

            return $appNamespace.'\\'.ltrim(str_replace(DIRECTORY_SEPARATOR, '\\', $relative), '\\');
        }

        // For workbench paths (used in tests), derive namespace from the path segments.
        // workbench/app/ => Workbench\App\, workbench/app/Models => Workbench\App\Models
        // This relies on Testbench's PSR-4 autoload-dev registration.
        $packageRoot = dirname(__DIR__, 2); // src/Services/ -> package root

        if (Str::startsWith($basePath, $packageRoot)) {
            $relative = Str::after($basePath, $packageRoot.DIRECTORY_SEPARATOR);

            return Str::of($relative)
                ->replace(DIRECTORY_SEPARATOR, '\\')
                ->explode('\\')
                ->map(fn (string $segment) => Str::studly($segment))
                ->implode('\\');
        }

        return 'App';
    }
}
