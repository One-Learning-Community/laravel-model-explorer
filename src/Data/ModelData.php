<?php

namespace OneLearningCommunity\LaravelModelExplorer\Data;

use Illuminate\Support\Collection;
use Spatie\ModelInfo\Attributes\Attribute;

readonly class ModelData
{
    /**
     * @param  Collection<int, Attribute>  $attributes
     * @param  Collection<int, RelationData>  $relations
     * @param  string[]  $fillable
     * @param  string[]  $guarded
     * @param  string[]  $hidden
     * @param  array<string, string>  $casts
     * @param  string[]  $appends
     */
    public function __construct(
        public string $className,
        public string $shortName,
        public string $table,
        public Collection $attributes,
        public Collection $relations,
        public array $fillable,
        public array $guarded,
        public array $hidden,
        public array $casts,
        public array $appends,
        public bool $usesTimestamps,
        public ?string $createdAtColumn,
        public ?string $updatedAtColumn,
    ) {}
}
