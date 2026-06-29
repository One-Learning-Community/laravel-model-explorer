<?php

namespace OneLearningCommunity\LaravelModelExplorer\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use OneLearningCommunity\LaravelModelExplorer\Mcp\Support\CompactPresenter;
use OneLearningCommunity\LaravelModelExplorer\Services\ExplorerCache;
use OneLearningCommunity\LaravelModelExplorer\Services\GraphBuilder;

#[Description('Return the relationship graph of all models as nodes (class, name, table) and edges (from, to, relation type, relation name). Use this to see how models connect without inspecting each one.')]
class RelationshipGraphTool extends Tool
{
    public function __construct(
        private readonly GraphBuilder $builder,
        private readonly CompactPresenter $presenter,
        private readonly ExplorerCache $cache,
    ) {}

    public function handle(Request $request): ResponseFactory
    {
        $useCache = (bool) config('model-explorer.mcp.cache.enabled', false);

        $built = $this->cache->rememberWhen($useCache, 'mcp.graph', fn () => $this->builder->build());

        return Response::structured($this->presenter->graph($built));
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
