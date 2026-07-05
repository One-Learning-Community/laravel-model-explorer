<?php

namespace OneLearningCommunity\LaravelModelExplorer\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use OneLearningCommunity\LaravelModelExplorer\Data\ModelData;
use OneLearningCommunity\LaravelModelExplorer\Data\RelationData;
use OneLearningCommunity\LaravelModelExplorer\Data\ScopeData;
use OneLearningCommunity\LaravelModelExplorer\Services\ExplorerCache;
use OneLearningCommunity\LaravelModelExplorer\Services\ModelDiscovery;
use OneLearningCommunity\LaravelModelExplorer\Services\ModelInspector;
use OneLearningCommunity\LaravelModelExplorer\Services\SourceFingerprint;
use Spatie\ModelInfo\Attributes\Attribute;

class ModelsController
{
    public function __construct(
        private readonly ModelDiscovery $discovery,
        private readonly ModelInspector $inspector,
        private readonly ExplorerCache $cache,
        private readonly SourceFingerprint $fingerprint,
    ) {}

    public function index(): JsonResponse
    {
        $models = $this->cache->remember('models.index.'.$this->fingerprint->forModelPaths(), fn () => collect($this->discovery->discoverAll())
            ->map(fn (string $className) => $this->summarize($className))
            ->filter()
            ->sortBy('short_name')
            ->values()
            ->all());

        return response()->json($models);
    }

    public function show(string $model): JsonResponse
    {
        $className = base64_decode(strtr($model, '-_', '+/'));

        if (! $className || ! class_exists($className)) {
            return response()->json(['message' => 'Model not found.'], 404);
        }

        try {
            $payload = $this->cache->remember(
                'models.show.'.$model.'.'.$this->fingerprint->forClass($className),
                fn () => $this->serialize($this->inspector->inspect($className)),
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        return response()->json($payload);
    }

    /**
     * Build the lightweight list-view summary for a model. Returns null when the
     * model cannot be instantiated, so a single broken model does not break the
     * entire list.
     *
     * @return array{class: string, short_name: string, table: string}|null
     */
    private function summarize(string $className): ?array
    {
        try {
            return [
                'class' => $className,
                'short_name' => class_basename($className),
                'table' => (new $className)->getTable(),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(ModelData $data): array
    {
        return [
            'class' => $data->className,
            'short_name' => $data->shortName,
            'table' => $data->table,
            'key_name' => $data->keyName,
            'fillable' => $data->fillable,
            'guarded' => $data->guarded,
            'hidden' => $data->hidden,
            'casts' => $data->casts,
            'appends' => $data->appends,
            'uses_timestamps' => $data->usesTimestamps,
            'created_at_column' => $data->createdAtColumn,
            'updated_at_column' => $data->updatedAtColumn,
            'policy' => $data->policyClass,
            'traits' => $data->traits,
            'scopes' => $data->scopes->map(fn (ScopeData $scope) => [
                'name' => $scope->name,
                'defined_in' => $scope->definedIn,
                'parameters' => $scope->parameters,
                'snippet' => $scope->snippet,
                'description' => $scope->description,
            ])->values(),
            'attributes' => $data->attributes->map(fn (Attribute $attr) => array_merge(
                $attr->toArray(),
                [
                    'snippet' => $data->accessorSnippets[$attr->name] ?? null,
                    'defined_in' => $data->accessorSnippets[$attr->name]['defined_in'] ?? null,
                    'enum_cases' => $data->enumCasts[$attr->name] ?? null,
                    'indexed' => $data->indexedColumns[$attr->name] ?? false,
                ],
            ))->values(),
            'relations' => $data->relations->map(fn (RelationData $rel) => [
                'name' => $rel->name,
                'type' => $rel->type,
                'related' => $rel->related,
                'foreign_key' => $rel->foreignKey,
                'local_key' => $rel->localKey,
                'defined_in' => $rel->definedIn,
                'description' => $rel->description,
                'snippet' => $rel->snippet,
                'pivot_table' => $rel->pivotTable,
                'pivot_foreign_key' => $rel->pivotForeignKey,
                'pivot_related_key' => $rel->pivotRelatedKey,
                'pivot_columns' => $rel->pivotColumns,
                'morph_type' => $rel->morphType,
                'through_model' => $rel->throughModel,
                'through_foreign_key' => $rel->throughForeignKey,
            ])->values(),
        ];
    }
}
