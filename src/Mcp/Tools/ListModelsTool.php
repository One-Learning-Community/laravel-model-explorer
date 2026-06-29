<?php

namespace OneLearningCommunity\LaravelModelExplorer\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use OneLearningCommunity\LaravelModelExplorer\Services\ExplorerCache;
use OneLearningCommunity\LaravelModelExplorer\Services\ModelDiscovery;

#[Description('List all discovered Eloquent models with their fully-qualified class, short name, and table. Call this first to see what models exist before inspecting one.')]
class ListModelsTool extends Tool
{
    public function __construct(
        private readonly ModelDiscovery $discovery,
        private readonly ExplorerCache $cache,
    ) {}

    public function handle(Request $request): ResponseFactory
    {
        $useCache = (bool) config('model-explorer.mcp.cache.enabled', false);

        $models = $this->cache->rememberWhen($useCache, 'mcp.list', function (): array {
            return collect($this->discovery->discoverAll())
                ->map(function (string $className): ?array {
                    try {
                        return [
                            'class' => $className,
                            'name' => class_basename($className),
                            'table' => (new $className)->getTable(),
                        ];
                    } catch (\Throwable) {
                        return null;
                    }
                })
                ->filter()
                ->sortBy('name')
                ->values()
                ->all();
        });

        return Response::structured([
            'models' => $models,
            'count' => count($models),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
