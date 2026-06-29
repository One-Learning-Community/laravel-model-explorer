<?php

namespace OneLearningCommunity\LaravelModelExplorer\Services;

use OneLearningCommunity\LaravelModelExplorer\Data\RelationData;

class GraphBuilder
{
    public function __construct(
        private readonly ModelDiscovery $discovery,
        private readonly ModelInspector $inspector,
    ) {}

    /**
     * Build the raw relationship-graph payload: one entry per inspectable model.
     * Un-inspectable models are skipped so a single broken model never breaks the graph.
     *
     * @return list<array{class:string, short_name:string, table:string, relations:list<array{name:string,type:string,related:string}>}>
     */
    public function build(): array
    {
        return collect($this->discovery->discoverAll())
            ->map(function (string $className): ?array {
                try {
                    $data = $this->inspector->inspect($className);
                } catch (\RuntimeException) {
                    return null;
                }

                return [
                    'class' => $data->className,
                    'short_name' => $data->shortName,
                    'table' => $data->table,
                    'relations' => $data->relations->map(fn (RelationData $rel) => [
                        'name' => $rel->name,
                        'type' => $rel->type,
                        'related' => $rel->related,
                    ])->values()->all(),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
