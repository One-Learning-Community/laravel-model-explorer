<?php

namespace OneLearningCommunity\LaravelModelExplorer\Facades;

use Illuminate\Support\Facades\Facade;
use OneLearningCommunity\LaravelModelExplorer\LaravelModelExplorer;

/**
 * @see LaravelModelExplorer
 */
class ModelExplorer extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LaravelModelExplorer::class;
    }
}
