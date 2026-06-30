<?php

namespace OneLearningCommunity\LaravelModelExplorer\Console;

use Illuminate\Console\Command;
use OneLearningCommunity\LaravelModelExplorer\Services\ModelInspector;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Internal worker: inspects a single model and writes a marker-wrapped,
 * base64-encoded, serialized ModelData payload to stdout. Run as a fresh
 * subprocess so the inspected class is loaded from its current source — the
 * long-lived MCP server cannot reload a class it has already loaded.
 */
class InspectCommand extends Command
{
    protected $signature = 'model-explorer:inspect {class : Fully-qualified model class name}';

    protected $description = 'Internal: inspect a model in a fresh process and emit a serialized payload';

    protected $hidden = true;

    public function handle(ModelInspector $inspector): int
    {
        try {
            $data = $inspector->inspect($this->argument('class'));
        } catch (\Throwable $e) {
            $this->output->write($e->getMessage(), false, OutputInterface::OUTPUT_RAW);

            return self::FAILURE;
        }

        $payload = '<<<MEX>>>'.base64_encode(serialize($data)).'<<</MEX>>>';

        $this->output->write($payload, false, OutputInterface::OUTPUT_RAW);

        return self::SUCCESS;
    }
}
