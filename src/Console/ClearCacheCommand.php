<?php

namespace OneLearningCommunity\LaravelModelExplorer\Console;

use Illuminate\Console\Command;
use OneLearningCommunity\LaravelModelExplorer\Services\ExplorerCache;

class ClearCacheCommand extends Command
{
    protected $signature = 'model-explorer:clear';

    protected $description = 'Clear cached Model Explorer discovery and inspection data';

    public function handle(ExplorerCache $cache): int
    {
        $cache->flush();

        $this->info('Model Explorer cache cleared.');

        return self::SUCCESS;
    }
}
