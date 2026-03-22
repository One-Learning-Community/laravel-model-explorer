<?php

namespace OneLearningCommunity\LaravelModelExplorer\Data;

readonly class ScopeData
{
    /**
     * @param  array<int, array{name: string, type: ?string, has_default: bool, default: ?string}>  $parameters
     * @param  array{code: string, file: string, start_line: int}|null  $snippet
     */
    public function __construct(
        public string $name,
        public ?string $definedIn,
        public array $parameters,
        public ?array $snippet,
    ) {}
}
