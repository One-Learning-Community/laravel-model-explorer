<?php

namespace OneLearningCommunity\LaravelModelExplorer\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \OneLearningCommunity\LaravelModelExplorer\LaravelModelExplorer
 */
class ModelExplorer extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \OneLearningCommunity\LaravelModelExplorer\LaravelModelExplorer::class;
    }
}
