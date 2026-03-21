<?php

namespace OneLearningCommunity\LaravelModelExplorer\Data;

readonly class ModelData
{
    /**
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
