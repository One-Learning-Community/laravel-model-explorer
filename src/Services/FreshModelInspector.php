<?php

namespace OneLearningCommunity\LaravelModelExplorer\Services;

use OneLearningCommunity\LaravelModelExplorer\Data\ModelData;

/**
 * Inspects a model while guaranteeing the result reflects the current source,
 * even inside a long-lived process (e.g. the MCP server) that cannot reload a
 * class it has already loaded.
 *
 * Decision per class:
 *  - Not yet loaded in this process  → inspect in-process; the first load is
 *    always current. Record the file mtime of the version we loaded.
 *  - Loaded, file unchanged since    → inspect in-process; our loaded copy still
 *    matches disk.
 *  - Loaded, file changed since       → our loaded copy is pinned and stale, so
 *    inspect in a fresh subprocess instead.
 *
 * Registered as a singleton so the loaded-mtime registry survives across calls.
 */
class FreshModelInspector
{
    /** @var array<class-string, int> mtime of the class version loaded in this process */
    private array $loadedMtime = [];

    public function __construct(
        private readonly ModelInspector $inspector,
        private readonly SubprocessInspector $subprocess,
        private readonly SourceFingerprint $fingerprint,
    ) {}

    public function inspect(string $className): ModelData
    {
        if (! class_exists($className, false)) {
            $data = $this->inspector->inspect($className);
            $this->loadedMtime[$className] = $this->fingerprint->forClass($className);

            return $data;
        }

        $current = $this->fingerprint->forClass($className);

        // First time we see an already-loaded class (e.g. autoloaded during boot):
        // assume the in-memory copy is current as of now. Self-corrects on the next edit.
        $loaded = $this->loadedMtime[$className] ??= $current;

        if ($loaded === $current) {
            return $this->inspector->inspect($className);
        }

        // The file changed after we loaded the class; our pinned copy is stale.
        // Note: we deliberately do NOT update $loadedMtime — the in-process class
        // remains the old version, so subsequent calls keep using the subprocess.
        return $this->subprocess->inspect($className);
    }
}
