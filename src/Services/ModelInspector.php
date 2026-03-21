<?php

namespace OneLearningCommunity\LaravelModelExplorer\Services;

use OneLearningCommunity\LaravelModelExplorer\Data\ModelData;

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
            $model = new $className();
        } catch (\Throwable $e) {
            throw new \RuntimeException("Could not instantiate model [{$className}]: {$e->getMessage()}", 0, $e);
        }

        return new ModelData(
            className: $className,
            shortName: class_basename($className),
            table: $model->getTable(),
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
}
