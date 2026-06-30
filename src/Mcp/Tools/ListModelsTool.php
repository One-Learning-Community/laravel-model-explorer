<?php

namespace OneLearningCommunity\LaravelModelExplorer\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use OneLearningCommunity\LaravelModelExplorer\Services\ExplorerCache;
use OneLearningCommunity\LaravelModelExplorer\Services\ModelDiscovery;
use OneLearningCommunity\LaravelModelExplorer\Services\SourceFingerprint;

#[Description('List all discovered Eloquent models with their fully-qualified class, short name, and table. Call this first to see what models exist before inspecting one.')]
class ListModelsTool extends Tool
{
    public function __construct(
        private readonly ModelDiscovery $discovery,
        private readonly ExplorerCache $cache,
        private readonly SourceFingerprint $fingerprint,
    ) {}

    public function handle(Request $request): ResponseFactory
    {
        $useCache = (bool) config('model-explorer.mcp.cache.enabled', false);

        $key = 'mcp.list.'.$this->fingerprint->forModelPaths();

        $models = $this->cache->rememberWhen($useCache, $key, function (): array {
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
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
