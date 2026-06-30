<?php

namespace OneLearningCommunity\LaravelModelExplorer\Services;

use OneLearningCommunity\LaravelModelExplorer\Data\ModelData;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Inspects a model in a fresh `php artisan model-explorer:inspect` subprocess and
 * decodes the serialized ModelData it returns. Used when the calling (long-lived)
 * process has already loaded the class and the source has since changed — PHP
 * cannot redefine a loaded class, so only a new process sees the current code.
 */
class SubprocessInspector
{
    public function inspect(string $className): ModelData
    {
        [$successful, $stdout, $stderr] = $this->run($className);

        if (! $successful) {
            $reason = trim($stderr !== '' ? $stderr : $stdout);

            throw new RuntimeException("Fresh inspection of [{$className}] failed: {$reason}");
        }

        $payload = $this->extractPayload($stdout);
        $data = $payload !== null ? @unserialize(base64_decode($payload, true) ?: '') : false;

        if (! $data instanceof ModelData) {
            throw new RuntimeException("Fresh inspection of [{$className}] returned an unreadable payload.");
        }

        return $data;
    }

    /**
     * Run the worker command in a fresh process.
     *
     * @return array{0: bool, 1: string, 2: string} [successful, stdout, stderr]
     */
    protected function run(string $className): array
    {
        $process = new Process([
            PHP_BINARY,
            base_path('artisan'),
            'model-explorer:inspect',
            $className,
        ]);

        $process->setTimeout((float) config('model-explorer.mcp.subprocess_timeout', 60));
        $process->run();

        return [$process->isSuccessful(), $process->getOutput(), $process->getErrorOutput()];
    }

    private function extractPayload(string $output): ?string
    {
        return preg_match('/<<<MEX>>>(.*?)<<<\/MEX>>>/s', $output, $matches) === 1
            ? $matches[1]
            : null;
    }
}
