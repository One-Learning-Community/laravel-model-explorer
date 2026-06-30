<?php

namespace OneLearningCommunity\LaravelModelExplorer\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use OneLearningCommunity\LaravelModelExplorer\Mcp\Support\CompactPresenter;
use OneLearningCommunity\LaravelModelExplorer\Services\ExplorerCache;
use OneLearningCommunity\LaravelModelExplorer\Services\GraphBuilder;
use OneLearningCommunity\LaravelModelExplorer\Services\SourceFingerprint;

#[Description('Return the relationship graph of all models as nodes (class, name, table) and edges (from, to, relation type, relation name). Use this to see how models connect without inspecting each one.')]
class RelationshipGraphTool extends Tool
{
    public function __construct(
        private readonly GraphBuilder $builder,
        private readonly CompactPresenter $presenter,
        private readonly ExplorerCache $cache,
        private readonly SourceFingerprint $fingerprint,
    ) {}

    public function handle(Request $request): ResponseFactory
    {
        $useCache = (bool) config('model-explorer.mcp.cache.enabled', false);

        $key = 'mcp.graph.'.$this->fingerprint->forModelPaths();

        $built = $this->cache->rememberWhen($useCache, $key, fn () => $this->builder->build());

        return Response::structured($this->presenter->graph($built));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
