<?php

namespace OneLearningCommunity\LaravelModelExplorer\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use OneLearningCommunity\LaravelModelExplorer\Data\ModelData;
use OneLearningCommunity\LaravelModelExplorer\Data\RelationData;
use OneLearningCommunity\LaravelModelExplorer\Services\ModelDiscovery;
use OneLearningCommunity\LaravelModelExplorer\Services\ModelInspector;
use Spatie\ModelInfo\Attributes\Attribute;

class ModelsController
{
    public function __construct(
        private readonly ModelDiscovery $discovery,
        private readonly ModelInspector $inspector,
    ) {}

    public function index(): JsonResponse
    {
        $models = collect($this->discovery->discoverAll())
            ->map(function (string $className) {
                $data = $this->inspector->inspect($className);

                return [
                    'class' => $data->className,
                    'short_name' => $data->shortName,
                    'table' => $data->table,
                ];
            })
            ->values();

        return response()->json($models);
    }

    public function show(string $model): JsonResponse
    {
        $className = base64_decode(strtr($model, '-_', '+/'));

        if (! $className || ! class_exists($className)) {
            return response()->json(['message' => 'Model not found.'], 404);
        }

        try {
            $data = $this->inspector->inspect($className);
        } catch (\RuntimeException) {
            return response()->json(['message' => 'Model not found.'], 404);
        }

        return response()->json($this->serialize($data));
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
            'fillable' => $data->fillable,
            'guarded' => $data->guarded,
            'hidden' => $data->hidden,
            'casts' => $data->casts,
            'appends' => $data->appends,
            'uses_timestamps' => $data->usesTimestamps,
            'created_at_column' => $data->createdAtColumn,
            'updated_at_column' => $data->updatedAtColumn,
            'traits' => $data->traits,
            'attributes' => $data->attributes->map(fn (Attribute $attr) => $attr->toArray())->values(),
            'relations' => $data->relations->map(fn (RelationData $rel) => [
                'name' => $rel->name,
                'type' => $rel->type,
                'related' => $rel->related,
                'foreign_key' => $rel->foreignKey,
                'local_key' => $rel->localKey,
                'defined_in' => $rel->definedIn,
            ])->values(),
        ];
    }
}
