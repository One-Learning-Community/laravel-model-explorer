<?php

namespace OneLearningCommunity\LaravelModelExplorer\Data;

/**
 * One defined member of a model — a method, property, or constant — with a
 * best-effort `kind` classification and a source pointer for provenance.
 *
 * Bodies are deliberately omitted (ADR-012 §C): names, signatures, and pointers
 * only. Fetch a body on demand via the `model-source` tool.
 */
readonly class MemberData
{
    /**
     * @param  'method'|'property'|'constant'  $memberType
     * @param  string  $kind  Heuristic: relation/scope/accessor/lifecycle/business/method (methods) or config/constant/property.
     * @param  'public'|'protected'|'private'  $visibility
     * @param  ?string  $signature  Method signature, e.g. "markPaid(Carbon $at): void"; null for properties/constants.
     * @param  ?string  $value  Rendered constant value; null otherwise.
     * @param  array{file: string, start_line?: ?int}|null  $snippet  Pointer source for CompactPresenter::pointer().
     */
    public function __construct(
        public string $name,
        public string $memberType,
        public string $kind,
        public string $visibility,
        public bool $static,
        public ?string $signature = null,
        public ?string $value = null,
        public ?array $snippet = null,
    ) {}
}
