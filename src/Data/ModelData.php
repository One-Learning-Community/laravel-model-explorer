<?php

namespace OneLearningCommunity\LaravelModelExplorer\Data;

use Illuminate\Support\Collection;
use Spatie\ModelInfo\Attributes\Attribute;

readonly class ModelData
{
    /**
     * @param  Collection<int, Attribute>  $attributes
     * @param  Collection<int, RelationData>  $relations
     * @param  Collection<int, ScopeData>  $scopes
     * @param  string[]  $fillable
     * @param  string[]  $guarded
     * @param  string[]  $hidden
     * @param  array<string, string>  $casts
     * @param  string[]  $appends
     * @param  list<string>  $traits  Non-excluded traits used by the model (recursive).
     * @param  array<string, array{code: string, file: string, start_line: int}>  $accessorSnippets  Attribute name → source metadata.
     * @param  list<MemberData>  $members  Every first-party-defined method/property/constant, with provenance (ADR-012 §C).
     * @param  array<string, list<array{name: string, value: string|int|null}>>  $enumCasts  Column name → the cases of its enum cast (backed enums carry `value`; pure enums have `value: null`).
     * @param  array<string, bool>  $indexedColumns  Column name → true when the column participates in a non-unique DB index (PK/unique columns are omitted — already flagged and implicitly indexed).
     */
    public function __construct(
        public string $className,
        public string $shortName,
        public string $table,
        /** @var string|list<string> */
        public string|array $keyName,
        public Collection $attributes,
        public Collection $relations,
        public Collection $scopes,
        public array $fillable,
        public array $guarded,
        public array $hidden,
        public array $casts,
        public array $appends,
        public bool $usesTimestamps,
        public ?string $createdAtColumn,
        public ?string $updatedAtColumn,
        public array $traits,
        public array $accessorSnippets = [],
        public ?string $policyClass = null,
        public array $members = [],
        public array $enumCasts = [],
        public array $indexedColumns = [],
    ) {}
}
