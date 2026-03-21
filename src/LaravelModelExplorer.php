<?php

namespace OneLearningCommunity\LaravelModelExplorer;

use OneLearningCommunity\LaravelModelExplorer\Services\ModelDiscovery;
use OneLearningCommunity\LaravelModelExplorer\Services\ModelInspector;

/**
 * Entry point for the Laravel Model Explorer package.
 *
 * The primary services are available via the container:
 *
 *   app(ModelDiscovery::class)->discoverAll()   // Returns list<class-string<Model>>
 *   app(ModelInspector::class)->inspect($class) // Returns ModelData
 *
 * @see ModelDiscovery
 * @see ModelInspector
 */
class LaravelModelExplorer
{
    public function __construct(
        public readonly ModelDiscovery $discovery,
        public readonly ModelInspector $inspector,
    ) {}
}
