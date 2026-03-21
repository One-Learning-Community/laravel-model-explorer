<?php

namespace OneLearningCommunity\LaravelModelExplorer\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use OneLearningCommunity\LaravelModelExplorer\Data\ModelData;
use OneLearningCommunity\LaravelModelExplorer\Data\RelationData;
use Spatie\ModelInfo\ModelInfo;
use Spatie\ModelInfo\Relations\Relation;

class ModelInspector
{
    /**
     * Inspect a model class and return its full attribute metadata.
     *
     * @param  class-string  $className
     *
     * @throws \RuntimeException When the model cannot be instantiated.
     */
    public function inspect(string $className): ModelData
    {
        try {
            $modelInfo = ModelInfo::forModel($className);
            $model = new $className();
        } catch (\Throwable $e) {
            throw new \RuntimeException("Could not instantiate model [{$className}]: {$e->getMessage()}", 0, $e);
        }

        return new ModelData(
            className: $className,
            shortName: class_basename($className),
            table: $modelInfo->tableName,
            attributes: $modelInfo->attributes,
            relations: $modelInfo->relations->map(fn (Relation $relation) => $this->buildRelationData($model, $relation)),
            fillable: $model->getFillable(),
            guarded: $model->getGuarded(),
            hidden: $model->getHidden(),
            casts: $model->getCasts(),
            appends: $model->getAppends(),
            usesTimestamps: $model->usesTimestamps(),
            createdAtColumn: $model->usesTimestamps() ? $model->getCreatedAtColumn() : null,
            updatedAtColumn: $model->usesTimestamps() ? $model->getUpdatedAtColumn() : null,
        );
    }

    private function buildRelationData(Model $model, Relation $relation): RelationData
    {
        [$foreignKey, $localKey] = $this->extractKeys($model, $relation->name);

        return new RelationData(
            name: $relation->name,
            type: class_basename($relation->type),
            related: $relation->related,
            foreignKey: $foreignKey,
            localKey: $localKey,
        );
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function extractKeys(Model $model, string $relationName): array
    {
        try {
            $instance = $model->{$relationName}();
        } catch (\Throwable) {
            return [null, null];
        }

        if ($instance instanceof HasOneOrMany) {
            return [$instance->getForeignKeyName(), $instance->getLocalKeyName()];
        }

        if ($instance instanceof BelongsTo) {
            return [$instance->getForeignKeyName(), $instance->getOwnerKeyName()];
        }

        if ($instance instanceof BelongsToMany) {
            return [$instance->getForeignPivotKeyName(), $instance->getRelatedPivotKeyName()];
        }

        return [null, null];
    }
}
