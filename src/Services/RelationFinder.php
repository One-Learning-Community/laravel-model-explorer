<?php

namespace OneLearningCommunity\LaravelModelExplorer\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Relation as IlluminateRelation;
use Illuminate\Support\Collection;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;
use Spatie\ModelInfo\Relations\Relation;

class RelationFinder
{
    /**
     * @param  class-string<Model>|Model  $model
     * @return Collection<int, Relation>
     */
    public static function forModel(string|Model $model): Collection
    {
        if (is_string($model)) {
            $model = new $model;
        }

        return (new self)->relations($model);
    }

    /** @var list<string> */
    private const array RELATION_METHODS = [
        'belongsTo', 'hasOne', 'hasMany', 'belongsToMany',
        'hasOneThrough', 'hasManyThrough',
        'morphTo', 'morphOne', 'morphMany', 'morphToMany', 'morphedByMany',
    ];

    /**
     * Maps each relation builder method to the Relation class it returns, used to
     * recover the type of an untyped relation method that could not be invoked.
     *
     * @var array<string, class-string<IlluminateRelation>>
     */
    private const array RELATION_METHOD_TYPES = [
        'belongsTo' => BelongsTo::class,
        'hasOne' => HasOne::class,
        'hasMany' => HasMany::class,
        'belongsToMany' => BelongsToMany::class,
        'hasOneThrough' => HasOneThrough::class,
        'hasManyThrough' => HasManyThrough::class,
        'morphTo' => MorphTo::class,
        'morphOne' => MorphOne::class,
        'morphMany' => MorphMany::class,
        'morphToMany' => MorphToMany::class,
        'morphedByMany' => MorphToMany::class,
    ];

    /**
     * @return Collection<Relation>
     */
    public function relations(Model $model): Collection
    {
        $class = new ReflectionClass($model);

        return collect($class->getMethods())
            ->filter(fn (ReflectionMethod $method) => $this->hasRelationReturnType($method))
            ->map(function (ReflectionMethod $method) use ($model) {
                $relation = rescue(fn () => $method->invoke($model), report: false);

                // Happy path: invoking the method yielded a live relation instance,
                // which gives us the canonical type and related model directly.
                if ($relation instanceof IlluminateRelation) {
                    return new Relation(
                        $method->getName(),
                        (string) ($method->getReturnType() ?? get_class($relation)),
                        $relation->getRelated() ? get_class($relation->getRelated()) : '',
                    );
                }

                // Invocation failed: the method threw (e.g. a whereHas/constraint
                // closure that blows up against a blank, attribute-less model) or
                // returned a non-relation. We have already statically identified
                // this method as a relation, so fall back to its declared type and
                // the related class parsed from source rather than silently dropping
                // it — otherwise the relation set would depend on runtime state.
                $type = $this->staticRelationType($method);

                if ($type === null) {
                    return null;
                }

                return new Relation(
                    $method->getName(),
                    $type,
                    $this->relatedClassFromSource($method) ?? '',
                );
            })
            ->filter()
            ->values();
    }

    /**
     * The canonical relation type string for a method we could not invoke,
     * derived from its declared return type. Returns null when the type cannot
     * be confirmed as an Eloquent relation.
     */
    private function staticRelationType(ReflectionMethod $method): ?string
    {
        $returnType = $method->getReturnType();

        if ($returnType instanceof ReflectionNamedType && is_a($returnType->getName(), IlluminateRelation::class, true)) {
            return $returnType->getName();
        }

        if ($returnType instanceof ReflectionUnionType) {
            foreach ($returnType->getTypes() as $type) {
                if (is_a($type->getName(), IlluminateRelation::class, true)) {
                    return $type->getName();
                }
            }
        }

        // Untyped method: recover the type from the relation primitive it calls.
        $primitive = $this->sourceRelationMethod($method);

        return $primitive !== null ? (self::RELATION_METHOD_TYPES[$primitive] ?? null) : null;
    }

    /**
     * Parse the related model from the first `X::class` argument of the relation
     * call in the method body (e.g. `$this->hasMany(Post::class, ...)`), resolving
     * the short name against the declaring class's namespace and use imports.
     */
    private function relatedClassFromSource(ReflectionMethod $method): ?string
    {
        $source = SourceExtractor::forMethod($method);

        if ($source === null) {
            return null;
        }

        $pattern = '/\$this->(?:'.implode('|', self::RELATION_METHODS).')\s*\(\s*([\\\\A-Za-z_][\\\\A-Za-z0-9_]*)::class/';

        if (! preg_match($pattern, $source['code'], $matches)) {
            return null;
        }

        return $this->resolveClassName($matches[1], $method->getDeclaringClass());
    }

    /**
     * Resolve a class reference found in source to a fully-qualified, existing
     * class name, using the declaring class's namespace and `use` imports.
     */
    private function resolveClassName(string $name, ReflectionClass $context): ?string
    {
        if (str_starts_with($name, '\\')) {
            $fqcn = ltrim($name, '\\');

            return class_exists($fqcn) ? $fqcn : null;
        }

        $sameNamespace = $context->getNamespaceName().'\\'.$name;

        if (class_exists($sameNamespace)) {
            return $sameNamespace;
        }

        $imported = $this->useImports($context)[$name] ?? null;

        if ($imported !== null && class_exists($imported)) {
            return $imported;
        }

        return class_exists($name) ? $name : null;
    }

    /**
     * Build an alias => FQCN map from the `use` statements in the file that
     * declares the given class.
     *
     * @return array<string, string>
     */
    private function useImports(ReflectionClass $context): array
    {
        $file = $context->getFileName();

        if ($file === false || ! is_file($file)) {
            return [];
        }

        $imports = [];

        foreach (file($file) as $line) {
            if (! preg_match('/^\s*use\s+([\\\\A-Za-z0-9_]+)(?:\s+as\s+([A-Za-z0-9_]+))?\s*;/', $line, $matches)) {
                continue;
            }

            $fqcn = ltrim($matches[1], '\\');
            $alias = $matches[2] ?? class_basename($fqcn);
            $imports[$alias] = $fqcn;
        }

        return $imports;
    }

    protected function hasRelationReturnType(ReflectionMethod $method): bool
    {
        if ($method->getNumberOfParameters() > 0) {
            return false;
        }

        if ($method->getReturnType() instanceof ReflectionNamedType) {
            return is_a($method->getReturnType()->getName(), IlluminateRelation::class, true);
        }

        if ($method->getReturnType() instanceof ReflectionUnionType) {
            foreach ($method->getReturnType()->getTypes() as $type) {
                if (is_a($type->getName(), IlluminateRelation::class, true)) {
                    return true;
                }
            }
        }

        // No declared return type — scan the source body for relation calls rather than
        // invoking blindly (which could trigger side effects on non-relation methods).
        if ($method->getReturnType() === null) {
            return $this->sourceContainsRelationCall($method);
        }

        return false;
    }

    /**
     * Reads the method's source and checks for a $this->relationMethod() call.
     * Safe to call on any method — no execution, no side effects.
     */
    private function sourceContainsRelationCall(ReflectionMethod $method): bool
    {
        return $this->sourceRelationMethod($method) !== null;
    }

    /**
     * Returns the first relation builder method (e.g. "hasMany") called in the
     * method body, or null when none is found. No execution, no side effects.
     */
    private function sourceRelationMethod(ReflectionMethod $method): ?string
    {
        $source = SourceExtractor::forMethod($method);

        if ($source === null) {
            return null;
        }

        $pattern = '/\$this->('.implode('|', self::RELATION_METHODS).')\s*\(/';

        return preg_match($pattern, $source['code'], $matches) ? $matches[1] : null;
    }
}
