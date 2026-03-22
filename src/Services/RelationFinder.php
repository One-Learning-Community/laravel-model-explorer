<?php

namespace OneLearningCommunity\LaravelModelExplorer\Services;

use Illuminate\Database\Eloquent\Model;
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
     * @return Collection<Relation>
     */
    public function relations(Model $model): Collection
    {
        $class = new ReflectionClass($model);

        return collect($class->getMethods())
            ->filter(fn (ReflectionMethod $method) => $this->hasRelationReturnType($method))
            ->map(function (ReflectionMethod $method) use ($model) {
                $relation = rescue(fn () => $method->invoke($model));

                if (! $relation instanceof IlluminateRelation) {
                    return null;
                }

                // For typed methods the declared return type is the canonical type string.
                // For untyped methods discovered via source scanning, derive it from the instance.
                $type = $method->getReturnType() ?? get_class($relation);

                return new Relation(
                    $method->getName(),
                    (string) $type,
                    $relation->getRelated() ? get_class($relation->getRelated()) : '',
                );
            })
            ->filter()
            ->values();
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
        $source = SourceExtractor::forMethod($method);

        if ($source === null) {
            return false;
        }

        $pattern = '/\$this->(' . implode('|', self::RELATION_METHODS) . ')\s*\(/';

        return (bool) preg_match($pattern, $source['code']);
    }
}
