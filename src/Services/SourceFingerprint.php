<?php

namespace OneLearningCommunity\LaravelModelExplorer\Services;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

/**
 * Produces cheap source-state fingerprints used as cache-key components, so that
 * cached model metadata is invalidated when the underlying source changes rather
 * than persisting until a manual flush or TTL expiry.
 */
class SourceFingerprint
{
    /**
     * Modification time of the file that declares the given class, for use in a
     * per-model cache key. Returns 0 when the file cannot be resolved.
     */
    public function forClass(string $className): int
    {
        try {
            $file = (new ReflectionClass($className))->getFileName();

            return $file ? (int) filemtime($file) : 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * A short fingerprint over every PHP file in the configured model paths, so
     * adding, removing, or editing any model file invalidates set-wide caches
     * (the model list and relationship graph) without autoloading every class.
     */
    public function forModelPaths(): string
    {
        $paths = array_values((array) config('model-explorer.model_paths', []));
        $entries = [];

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $entries[] = $file->getPathname().':'.$file->getMTime();
                }
            }
        }

        sort($entries);

        return substr(sha1(implode('|', $entries)), 0, 16);
    }
}
