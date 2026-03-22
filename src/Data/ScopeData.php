<?php

namespace OneLearningCommunity\LaravelModelExplorer\Data;

readonly class ScopeData
{
    public function __construct(
        public string $name,
        public ?string $definedIn,
    ) {}
}
