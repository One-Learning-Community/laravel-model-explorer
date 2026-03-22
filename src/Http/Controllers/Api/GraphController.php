<?php

namespace OneLearningCommunity\LaravelModelExplorer\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use OneLearningCommunity\LaravelModelExplorer\Data\RelationData;
use OneLearningCommunity\LaravelModelExplorer\Services\ModelDiscovery;
use OneLearningCommunity\LaravelModelExplorer\Services\ModelInspector;

class GraphController
{
    public function __construct(
        private readonly ModelDiscovery $discovery,
        private readonly ModelInspector $inspector,
    ) {}

    /**
     * Returns all models with their relationships in a single payload for graph rendering.
     *
     * NOTE: This calls inspect() on every model, which is intentionally heavier than the
     * lightweight index endpoint. It is only used by the relationship graph view.
     */
    public function __invoke(): JsonResponse
    {
        $models = collect($this->discovery->discoverAll())
            ->map(function (string $className): ?array {
                try {
                    $data = $this->inspector->inspect($className);
                } catch (\RuntimeException) {
                    return null;
                }

                return [
                    'class' => $data->className,
                    'short_name' => $data->shortName,
                    'table' => $data->table,
                    'relations' => $data->relations->map(fn (RelationData $rel) => [
                        'name' => $rel->name,
                        'type' => $rel->type,
                        'related' => $rel->related,
                    ])->values(),
                ];
            })
            ->filter()
            ->values();

        return response()->json($models);
    }
}
