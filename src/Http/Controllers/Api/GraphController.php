<?php

namespace OneLearningCommunity\LaravelModelExplorer\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use OneLearningCommunity\LaravelModelExplorer\Services\ExplorerCache;
use OneLearningCommunity\LaravelModelExplorer\Services\GraphBuilder;
use OneLearningCommunity\LaravelModelExplorer\Services\SourceFingerprint;

class GraphController
{
    public function __construct(
        private readonly GraphBuilder $builder,
        private readonly ExplorerCache $cache,
        private readonly SourceFingerprint $fingerprint,
    ) {}

    /**
     * Returns all models with their relationships in a single payload for graph rendering.
     */
    public function __invoke(): JsonResponse
    {
        $models = $this->cache->remember('graph.'.$this->fingerprint->forModelPaths(), fn () => $this->builder->build());

        return response()->json($models);
    }
}
