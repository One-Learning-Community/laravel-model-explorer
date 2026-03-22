<?php

namespace OneLearningCommunity\LaravelModelExplorer\Data;

readonly class RelationData
{
    public function __construct(
        public string $name,
        public string $type,
        public string $related,
        public ?string $foreignKey,
        public ?string $localKey,
        public ?string $definedIn,
    ) {}
}
