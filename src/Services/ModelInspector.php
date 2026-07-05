<?php

namespace OneLearningCommunity\LaravelModelExplorer\Services;

use Illuminate\Database\Eloquent\Casts\Attribute as EloquentAttribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use OneLearningCommunity\LaravelModelExplorer\Data\ModelData;
use OneLearningCommunity\LaravelModelExplorer\Data\RelationData;
use OneLearningCommunity\LaravelModelExplorer\Data\ScopeData;
use Spatie\ModelInfo\Attributes\Attribute;
use Spatie\ModelInfo\Attributes\AttributeFinder;
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
            $model = new $className;
            $modelAttributes = AttributeFinder::forModel($className);
            $modelRelations = RelationFinder::forModel($className);
        } catch (QueryException $e) {
            throw new \RuntimeException("Failed to query model [{$className}]: {$e->getMessage()}", 0, $e);
        } catch (\Throwable $e) {
            throw new \RuntimeException("Could not instantiate model [{$className}]: {$e->getMessage()}", 0, $e);
        }

        $policies = Gate::policies();
        $factory = $this->extractFactory($className);

        $relations = $modelRelations
            ->map(fn (Relation $relation) => $this->buildRelationData($className, $model, $relation))
            ->sortBy('name')
            ->values();

        return new ModelData(
            className: $className,
            shortName: class_basename($className),
            table: $model->getTable(),
            keyName: $model->getKeyName(),
            attributes: $modelAttributes,
            relations: $relations,
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
            accessorSnippets: $this->extractAccessorSnippets($className, $modelAttributes),
            policyClass: $policies[$className] ?? null,
            members: MemberExtractor::forModel($className, $relations->pluck('name')->all()),
            enumCasts: $this->extractEnumCasts($model->getCasts()),
            indexedColumns: $this->extractIndexedColumns($model),
            factoryClass: $factory['class'],
            factoryDefinedIn: $factory['defined_in'],
        );
    }

    /**
     * The model's factory class and a `path:line` pointer to it, resolved through
     * `Model::factory()` so it honors a `$factory` property, a `#[UseFactory]`
     * attribute, a custom `newFactory()`, and package factory traits
     * (`HasPackageFactory`) — not just the convention guess. `factory()` only
     * constructs the factory object (via `Factory::new()`); it runs no
     * `definition()` and touches no DB, so it is cheap and side-effect-free.
     *
     * The `factory()` method exists only when the model uses `HasFactory` (or a
     * variant), so a model without one reports nothing. The class + path come from
     * reflection on the actual factory instance, never a convention-derived string.
     * Best-effort: if the resolved factory class is absent (or anything else
     * throws), degrade to no factory — never wrong.
     *
     * @return array{class: ?string, defined_in: ?string}
     */
    private function extractFactory(string $className): array
    {
        try {
            if (! method_exists($className, 'factory')) {
                return ['class' => null, 'defined_in' => null];
            }

            $factory = $className::factory();
            $reflection = new \ReflectionClass($factory);

            return [
                'class' => $factory::class,
                'defined_in' => $this->relativePathLine($reflection->getFileName() ?: '', $reflection->getStartLine() ?: null),
            ];
        } catch (\Throwable) {
            return ['class' => null, 'defined_in' => null];
        }
    }

    /**
     * Format an absolute file path + line as a base_path-relative `path:line`
     * pointer, matching the `defined_in` idiom used across the surface.
     */
    private function relativePathLine(string $file, ?int $line): ?string
    {
        if ($file === '') {
            return null;
        }

        $base = base_path().DIRECTORY_SEPARATOR;
        $relative = str_starts_with($file, $base) ? substr($file, strlen($base)) : $file;

        return $line ? $relative.':'.$line : $relative;
    }

    /**
     * Columns that participate in a non-unique database index, keyed by name, each
     * mapped to a role label so a composite index doesn't imply every member is
     * independently filterable:
     *   ''                  → leads a single-column index (cheap to filter alone)
     *   'composite-leading' → leads a composite index (a lone/prefix filter can use it)
     *   'composite-{N}of{M}'→ non-leading member at 1-based position N of size M
     *                         (a lone filter on it CANNOT use the index)
     *
     * A column in several indexes keeps its most favorable role. Primary and unique
     * indexes are skipped — those columns are already flagged (`PK`/`unique`) and
     * implicitly indexed. Best-effort: an unreadable schema (exotic driver, missing
     * connection) degrades to no flags.
     *
     * @return array<string, string>
     */
    private function extractIndexedColumns(Model $model): array
    {
        try {
            $indexes = $model->getConnection()->getSchemaBuilder()->getIndexes($model->getTable());
        } catch (\Throwable) {
            return [];
        }

        // Rank the roles so a column indexed several ways keeps the best one.
        $best = []; // column => ['rank' => int, 'pos' => int, 'label' => string]

        foreach ($indexes as $index) {
            if (! empty($index['unique']) || ! empty($index['primary'])) {
                continue;
            }

            $columns = array_values($index['columns'] ?? []);
            $size = count($columns);

            foreach ($columns as $pos => $column) {
                [$rank, $label] = match (true) {
                    $size === 1 => [3, ''],
                    $pos === 0 => [2, 'composite-leading'],
                    default => [1, 'composite-'.($pos + 1).'of'.$size],
                };

                $current = $best[$column] ?? null;

                // Higher rank wins; ties among non-leading members keep the lowest position.
                if ($current === null
                    || $rank > $current['rank']
                    || ($rank === $current['rank'] && $rank === 1 && $pos < $current['pos'])) {
                    $best[$column] = ['rank' => $rank, 'pos' => $pos, 'label' => $label];
                }
            }
        }

        return array_map(fn (array $entry): string => $entry['label'], $best);
    }

    /**
     * Expand any enum casts into their cases, keyed by column name. Backed enums
     * carry their backing `value`; pure enums report `value: null`. Non-enum casts
     * (datetime, boolean, custom cast classes, …) are skipped.
     *
     * @param  array<string, string>  $casts  Column name → cast target (from Model::getCasts()).
     * @return array<string, list<array{name: string, value: string|int|null}>>
     */
    private function extractEnumCasts(array $casts): array
    {
        $enumCasts = [];

        foreach ($casts as $column => $cast) {
            // A cast may be suffixed with arguments (e.g. "encrypted:array"); the
            // enum form is always the bare class string, so only that can match.
            if (! is_string($cast) || ! enum_exists($cast)) {
                continue;
            }

            $enumCasts[$column] = array_map(
                fn (\UnitEnum $case): array => [
                    'name' => $case->name,
                    'value' => $case instanceof \BackedEnum ? $case->value : null,
                ],
                $cast::cases(),
            );
        }

        return $enumCasts;
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
                ->map(function (\ReflectionMethod $method) use ($className): ScopeData {
                    $snippet = SourceExtractor::forMethod($method);

                    return new ScopeData(
                        name: lcfirst(substr($method->getName(), 5)),
                        definedIn: $this->resolveMethodSource($className, $method->getName()),
                        parameters: $this->extractScopeParameters($method),
                        snippet: $snippet,
                        description: $snippet['doc_summary'] ?? SourceExtractor::docSummary($method),
                    );
                })
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

    /**
     * Extract user-visible parameters from a scope method (skipping the leading $query param).
     *
     * @return array<int, array{name: string, type: ?string, has_default: bool, default: ?string}>
     */
    private function extractScopeParameters(\ReflectionMethod $method): array
    {
        $params = [];

        foreach (array_slice($method->getParameters(), 1) as $param) {
            $type = $param->getType();
            $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : null;
            $hasDefault = $param->isOptional() && $param->isDefaultValueAvailable();
            $default = null;

            if ($hasDefault) {
                $raw = $param->getDefaultValue();
                $default = match (true) {
                    is_null($raw) => 'null',
                    is_bool($raw) => $raw ? 'true' : 'false',
                    is_string($raw) => "'{$raw}'",
                    is_array($raw) => '[]',
                    default => (string) $raw,
                };
            }

            $params[] = [
                'name' => $param->getName(),
                'type' => $typeName,
                'has_default' => $hasDefault,
                'default' => $default,
            ];
        }

        return $params;
    }

    private function buildRelationData(string $modelClass, Model $model, Relation $relation): RelationData
    {
        $meta = $this->extractRelationMeta($model, $relation->name);

        $snippet = null;
        $description = null;

        try {
            $refMethod = new \ReflectionMethod($modelClass, $relation->name);
            $snippet = SourceExtractor::forMethod($refMethod);
            $description = $snippet['doc_summary'] ?? SourceExtractor::docSummary($refMethod);
        } catch (\Throwable) {
            // ignore — relation method may not be directly on this class
        }

        return new RelationData(
            name: $relation->name,
            type: class_basename($relation->type),
            related: $relation->related,
            foreignKey: $meta['foreignKey'],
            localKey: $meta['localKey'],
            definedIn: $this->resolveMethodSource($modelClass, $relation->name),
            description: $description,
            snippet: $snippet,
            pivotTable: $meta['pivotTable'],
            pivotForeignKey: $meta['pivotForeignKey'],
            pivotRelatedKey: $meta['pivotRelatedKey'],
            pivotColumns: $meta['pivotColumns'],
            morphType: $meta['morphType'],
            throughModel: $meta['throughModel'],
            throughForeignKey: $meta['throughForeignKey'],
        );
    }

    /**
     * Returns the FQCN of the trait or parent class that provides the method,
     * or null when the method is declared directly on the model class itself.
     *
     * Walk order at each level:
     *  1. Check the class's direct traits — trait wins over direct declaration.
     *  2. If past the target class and the method is declared here (not via trait),
     *     return this class as the source.
     *
     * Note: ReflectionMethod::getDeclaringClass() returns the using class for trait
     * methods, not the trait itself — hence the manual trait walk.
     */
    private function resolveMethodSource(string $modelClass, string $methodName): ?string
    {
        try {
            $reflection = new \ReflectionClass($modelClass);
            $isTargetClass = true;

            while ($reflection && $reflection->getName() !== Model::class) {
                foreach ($reflection->getTraits() as $traitName => $trait) {
                    if ($trait->hasMethod($methodName)) {
                        return $traitName;
                    }
                }

                // If we're in a parent class and the method is declared directly
                // on it (not via a trait — already handled above), report the parent.
                if (! $isTargetClass && $reflection->hasMethod($methodName)) {
                    $declaringClass = $reflection->getMethod($methodName)->getDeclaringClass();
                    if ($declaringClass->getName() === $reflection->getName()) {
                        return $reflection->getName();
                    }
                }

                $isTargetClass = false;
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
     * Best-effort structural detail for one relation, gathered by instantiating it
     * once against a blank model. Every field defaults to null/[] and stays that way
     * when the relation can't be instantiated or the family doesn't carry that detail.
     *
     * @return array{
     *     foreignKey: ?string, localKey: ?string,
     *     pivotTable: ?string, pivotForeignKey: ?string, pivotRelatedKey: ?string, pivotColumns: list<string>,
     *     morphType: ?string, throughModel: ?string, throughForeignKey: ?string
     * }
     */
    private function extractRelationMeta(Model $model, string $relationName): array
    {
        $meta = [
            'foreignKey' => null, 'localKey' => null,
            'pivotTable' => null, 'pivotForeignKey' => null, 'pivotRelatedKey' => null, 'pivotColumns' => [],
            'morphType' => null, 'throughModel' => null, 'throughForeignKey' => null,
        ];

        try {
            $instance = $model->{$relationName}();
        } catch (\Throwable) {
            return $meta;
        }

        // MorphToMany extends BelongsToMany — check this family first so a polymorphic
        // pivot captures both its pivot keys and its morph type.
        if ($instance instanceof BelongsToMany) {
            $foreign = $instance->getForeignPivotKeyName();
            $related = $instance->getRelatedPivotKeyName();

            $meta['foreignKey'] = $foreign;
            $meta['localKey'] = $related;
            $meta['pivotTable'] = $instance->getTable();
            $meta['pivotForeignKey'] = $foreign;
            $meta['pivotRelatedKey'] = $related;
            $meta['pivotColumns'] = array_values(array_filter(
                $instance->getPivotColumns(),
                fn (string $column): bool => $column !== $foreign && $column !== $related,
            ));

            if ($instance instanceof MorphToMany) {
                $meta['morphType'] = $instance->getMorphType();
            }

            return $meta;
        }

        // MorphTo extends BelongsTo.
        if ($instance instanceof BelongsTo) {
            $meta['foreignKey'] = $instance->getForeignKeyName();
            $meta['localKey'] = $instance->getOwnerKeyName();

            if ($instance instanceof MorphTo) {
                $meta['morphType'] = $instance->getMorphType();
            }

            return $meta;
        }

        // MorphOne/MorphMany extend MorphOneOrMany, which extends HasMany (HasOneOrMany).
        if ($instance instanceof HasOneOrMany) {
            $meta['foreignKey'] = $instance->getForeignKeyName();
            $meta['localKey'] = $instance->getLocalKeyName();

            if ($instance instanceof MorphOneOrMany) {
                $meta['morphType'] = $instance->getMorphType();
            }

            return $meta;
        }

        // Shared base for both through-relations. Laravel 11+ moved HasOneThrough
        // and HasManyThrough under HasOneOrManyThrough (previously HasOneThrough
        // extended HasManyThrough), so gate on the base to catch both.
        if ($instance instanceof HasOneOrManyThrough) {
            $meta['foreignKey'] = $instance->getForeignKeyName();        // FK on the far/target table
            $meta['localKey'] = $instance->getLocalKeyName();            // PK on the origin model
            $meta['throughForeignKey'] = $instance->getFirstKeyName();   // FK on the intermediate table
            // HasManyThrough passes the intermediate ("through") model to the base
            // relation constructor as its parent, so getParent() is the through model.
            $meta['throughModel'] = get_class($instance->getParent());

            return $meta;
        }

        return $meta;
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
                $snippets[$attribute->name] = array_merge(
                    $snippet,
                    ['defined_in' => $this->resolveMethodSource($className, $method->getName())],
                );
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
        $oldStyle = 'get'.Str::studly($attributeName).'Attribute';

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
}
