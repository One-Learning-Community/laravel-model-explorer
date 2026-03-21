<?php

namespace OneLearningCommunity\LaravelModelExplorer\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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
            ->flatMap(fn (string $path) => $this->discoverIn($path))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<class-string<Model>>
     */
    public function discoverIn(string $path): array
    {
        if (! is_dir($path)) {
            return [];
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $models = [];

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $className = $this->fileToClassName($file->getRealPath(), $path);

            if ($className === null) {
                continue;
            }

            try {
                $reflection = new \ReflectionClass($className);
            } catch (\Throwable) {
                continue;
            }

            if ($reflection->isAbstract() || ! $reflection->isSubclassOf(Model::class)) {
                continue;
            }

            $models[] = $reflection->getName();
        }

        return $models;
    }

    private function fileToClassName(string $filePath, string $basePath): ?string
    {
        // Normalize both paths so that paths containing `..` or symlinks
        // resolve correctly when stripping the base prefix from the file path.
        $basePath = realpath($basePath) ?: $basePath;

        // Derive the base namespace for this path.
        // Standard case: path is under app_path(), namespace root is app()->getNamespace().
        // Non-standard paths (e.g. workbench/) require the namespace to be inferable
        // from composer's autoload-dev map — which Testbench registers at boot.
        $baseNamespace = $this->resolveBaseNamespace($basePath);

        $relative = Str::of($filePath)
            ->replaceFirst($basePath, '')
            ->replaceLast('.php', '')
            ->trim(DIRECTORY_SEPARATOR)
            ->replace(DIRECTORY_SEPARATOR, '\\');

        if ($relative->isEmpty()) {
            return null;
        }

        return rtrim($baseNamespace, '\\').'\\'.ltrim((string) $relative, '\\');
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
