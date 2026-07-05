<?php

namespace OneLearningCommunity\LaravelModelExplorer\Data;

readonly class RelationData
{
    /**
     * @param  array{code: string, file: string, start_line: int, end_line: int, doc_summary: ?string}|null  $snippet
     * @param  list<string>  $pivotColumns  Extra (withPivot) columns on a many-to-many join row, excluding the two pivot keys.
     */
    public function __construct(
        public string $name,
        public string $type,
        public string $related,
        public ?string $foreignKey,
        public ?string $localKey,
        public ?string $definedIn,
        public ?string $description = null,
        public ?array $snippet = null,
        // Many-to-many (BelongsToMany / MorphToMany) pivot detail.
        public ?string $pivotTable = null,
        public ?string $pivotForeignKey = null,
        public ?string $pivotRelatedKey = null,
        public array $pivotColumns = [],
        // Polymorphic (MorphTo / MorphOne / MorphMany / MorphToMany) type column.
        public ?string $morphType = null,
        // Has-*-through intermediate model and the key linking origin → intermediate.
        public ?string $throughModel = null,
        public ?string $throughForeignKey = null,
    ) {}
}
