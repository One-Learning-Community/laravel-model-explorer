<?php

namespace OneLearningCommunity\LaravelModelExplorer\Services;

use Illuminate\Database\Eloquent\Casts\Attribute as EloquentAttribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use OneLearningCommunity\LaravelModelExplorer\Data\ModelData;
use OneLearningCommunity\LaravelModelExplorer\Data\RelationData;
use OneLearningCommunity\LaravelModelExplorer\Data\ScopeData;
use Spatie\ModelInfo\Attributes\Attribute;
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
            relations: $this->discoverRelations($className, $modelInfo)->map(fn (Relation $relation) => $this->buildRelationData($className, $model, $relation))->sortBy('name')->values(),
            scopes: $this->extractScopes($className),
            fillable: $model->getFillable(),
            guarded: $model->getGuarded(),
            hidden: $model->getHidden(),
            casts: $model->getCasts(),
            appends: $model->getAppends(),
            usesTimestamps: $model->usesTimestamps(),
            createdAtColumn: $model->usesTimestamps() ? $model->getCreatedAtColumn() : null,
            updatedAtColumn: $model->usesTimestamps() ? $model->getUpdatedAtColumn() : null,
            traits: $this->extractTraits($className),
            accessorSnippets: $this->extractAccessorSnippets($className, $modelInfo->attributes),
        );
    }

    /**
     * @param  class-string  $className
     * @return list<string>
     */
    private function extractTraits(string $className): array
    {
        $excludedPrefixes = $this->excludedTraitPrefixes();

        $traits = array_filter(
            array_keys(class_uses_recursive($className)),
            function (string $trait) use ($excludedPrefixes): bool {
                foreach ($excludedPrefixes as $prefix) {
                    if (str_starts_with($trait, $prefix)) {
                        return false;
                    }
                }

                return true;
            },
        );

        usort($traits, fn (string $a, string $b) => class_basename($a) <=> class_basename($b));

        return array_values($traits);
    }

    /**
     * @param  class-string  $className
     * @return Collection<int, ScopeData>
     */
    private function extractScopes(string $className): Collection
    {
        $excludedPrefixes = $this->excludedTraitPrefixes();

        try {
            return collect((new \ReflectionClass($className))->getMethods(\ReflectionMethod::IS_PUBLIC))
                ->filter(fn (\ReflectionMethod $method) => (bool) preg_match('/^scope[A-Z]/', $method->getName()))
                ->map(fn (\ReflectionMethod $method) => new ScopeData(
                    name: lcfirst(substr($method->getName(), 5)),
                    definedIn: $this->resolveMethodSource($className, $method->getName()),
                ))
                ->filter(function (ScopeData $scope) use ($excludedPrefixes): bool {
                    if ($scope->definedIn === null) {
                        return true;
                    }

                    foreach ($excludedPrefixes as $prefix) {
                        if (str_starts_with($scope->definedIn, $prefix)) {
                            return false;
                        }
                    }

                    return true;
                })
                ->sortBy('name')
                ->values();
        } catch (\Throwable) {
            return collect();
        }
    }

    private function buildRelationData(string $modelClass, Model $model, Relation $relation): RelationData
    {
        [$foreignKey, $localKey] = $this->extractKeys($model, $relation->name);

        return new RelationData(
            name: $relation->name,
            type: class_basename($relation->type),
            related: $relation->related,
            foreignKey: $foreignKey,
            localKey: $localKey,
            definedIn: $this->resolveMethodSource($modelClass, $relation->name),
        );
    }

    /**
     * Returns the FQCN of the trait that provides the method, or null when the method
     * is declared directly on the model class (or a parent class without using a trait).
     *
     * ReflectionMethod::getDeclaringClass() returns the using class for trait methods,
     * not the trait. We walk the class hierarchy checking each class's direct traits.
     */
    private function resolveMethodSource(string $modelClass, string $methodName): ?string
    {
        try {
            $reflection = new \ReflectionClass($modelClass);

            while ($reflection && $reflection->getName() !== Model::class) {
                foreach ($reflection->getTraits() as $traitName => $trait) {
                    if ($trait->hasMethod($methodName)) {
                        return $traitName;
                    }
                }

                $reflection = $reflection->getParentClass() ?: null;
            }
        } catch (\Throwable) {
            // ignore
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function excludedTraitPrefixes(): array
    {
        return config('model-explorer.excluded_trait_prefixes', [
            'Illuminate\Database\Eloquent\Concerns\\',
            'Illuminate\Database\Eloquent\HasCollection',
            'Illuminate\Support\Traits\\',
        ]);
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

    /**
     * For each virtual attribute, attempt to locate its accessor method and extract
     * the source snippet. Supports both old-style (getFooAttribute) and new-style
     * (foo(): Attribute) accessors.
     *
     * @param  Collection<int, Attribute>  $attributes
     * @return array<string, array{code: string, file: string, start_line: int}>
     */
    private function extractAccessorSnippets(string $className, Collection $attributes): array
    {
        $snippets = [];

        try {
            $reflection = new \ReflectionClass($className);
        } catch (\Throwable) {
            return $snippets;
        }

        foreach ($attributes->filter(fn (Attribute $attr) => $attr->virtual) as $attribute) {
            $method = $this->findAccessorMethod($reflection, $attribute->name);

            if ($method === null) {
                continue;
            }

            $snippet = SourceExtractor::forMethod($method);

            if ($snippet !== null) {
                $snippets[$attribute->name] = $snippet;
            }
        }

        return $snippets;
    }

    /**
     * Locates the accessor method for the given attribute name.
     *
     * Checks old-style (getFooAttribute) first, then new-style (foo(): Attribute).
     */
    private function findAccessorMethod(\ReflectionClass $reflection, string $attributeName): ?\ReflectionMethod
    {
        // Old-style: getFooBarAttribute()
        $oldStyle = 'get' . Str::studly($attributeName) . 'Attribute';

        if ($reflection->hasMethod($oldStyle)) {
            return $reflection->getMethod($oldStyle);
        }

        // New-style: foo(): \Illuminate\Database\Eloquent\Casts\Attribute
        $newStyle = Str::camel($attributeName);

        if ($reflection->hasMethod($newStyle)) {
            $method = $reflection->getMethod($newStyle);
            $returnType = $method->getReturnType();

            if ($returnType instanceof \ReflectionNamedType &&
                is_a($returnType->getName(), EloquentAttribute::class, true)) {
                return $method;
            }
        }

        return null;
    }

    private function discoverRelations(string $className, ModelInfo $modelInfo): Collection
    {
        return RelationFinder::forModel($className);
    }
}
