<?php

namespace OneLearningCommunity\LaravelModelExplorer\Data;

readonly class RelationData
{
    /**
     * @param  array{code: string, file: string, start_line: int, doc_summary: ?string}|null  $snippet
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
    ) {}
}
