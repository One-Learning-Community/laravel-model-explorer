<?php

namespace OneLearningCommunity\LaravelModelExplorer\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use OneLearningCommunity\LaravelModelExplorer\Data\RelationData;
use OneLearningCommunity\LaravelModelExplorer\Mcp\Support\CompactPresenter;
use OneLearningCommunity\LaravelModelExplorer\Mcp\Support\ModelResolver;
use OneLearningCommunity\LaravelModelExplorer\Services\ExplorerCache;
use OneLearningCommunity\LaravelModelExplorer\Services\FreshModelInspector;
use OneLearningCommunity\LaravelModelExplorer\Services\GraphBuilder;
use OneLearningCommunity\LaravelModelExplorer\Services\SourceFingerprint;

/**
 * The scoped graph return ADR-012 anticipated when it retired the whole-universe
 * relationship-graph tool: a bounded, single-model neighborhood instead of a dump.
 * `direction` defaults to `incoming` because that's the actual gap — outgoing
 * relations are already fully exposed by inspect-model. See ADR-013.
 */
#[Description('Return the depth-1 relation neighborhood of one model: which models point at it (incoming, the default), which models it points at (outgoing), or both. Answers "what breaks if I change this model" at the Eloquent-relation level — these are model-to-model relation edges, not code call sites; to find where the model or its methods are referenced in code, use a text search such as grep. Bounded by limit, with a truncated flag when more edges exist.')]
class ModelNeighborsTool extends Tool
{
    private const DIRECTIONS = ['incoming', 'outgoing', 'both'];

    private const DEFAULT_LIMIT = 50;

    public function __construct(
        private readonly ModelResolver $resolver,
        private readonly FreshModelInspector $inspector,
        private readonly GraphBuilder $graphBuilder,
        private readonly CompactPresenter $presenter,
        private readonly ExplorerCache $cache,
        private readonly SourceFingerprint $fingerprint,
    ) {}

    public function handle(Request $request): ResponseFactory|Response
    {
        $request->validate([
            'model' => 'required|string',
            'direction' => 'nullable|in:'.implode(',', self::DIRECTIONS),
            'depth' => 'nullable|integer',
            'limit' => 'nullable|integer|min:1',
        ]);

        $direction = $request->filled('direction') ? (string) $request->get('direction') : 'incoming';
        $depth = $request->filled('depth') ? (int) $request->get('depth') : 1;
        $limit = $request->filled('limit') ? (int) $request->get('limit') : self::DEFAULT_LIMIT;

        if ($depth !== 1) {
            return Response::error('Only depth=1 is currently supported; multi-hop traversal is not yet implemented.');
        }

        try {
            $className = $this->resolver->resolve((string) $request->get('model'));
        } catch (\RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        $edges = [];

        if ($direction === 'outgoing' || $direction === 'both') {
            try {
                $edges = [...$edges, ...$this->outgoingEdges($className)];
            } catch (\RuntimeException $e) {
                return Response::error($e->getMessage());
            }
        }

        if ($direction === 'incoming' || $direction === 'both') {
            $edges = [...$edges, ...$this->incomingEdges($className)];
        }

        usort($edges, fn (array $a, array $b) => [$a['from'], $a['name']] <=> [$b['from'], $b['name']]);

        $total = count($edges);
        $edges = array_slice($edges, 0, $limit);

        return Response::structured([
            'root' => $className,
            'direction' => $direction,
            'edges' => $edges,
            'count' => count($edges),
            'truncated' => $total > $limit,
        ]);
    }

    /**
     * @return list<array{direction:string,from:string,to:string,type:string,name:string,defined_in:?string}>
     */
    private function outgoingEdges(string $className): array
    {
        $useCache = (bool) config('model-explorer.mcp.cache.enabled', false);

        $data = $this->cache->rememberWhen(
            $useCache,
            'mcp.inspect.'.$className.'.'.$this->fingerprint->forClass($className),
            fn () => $this->inspector->inspect($className),
        );

        return $data->relations->map(fn (RelationData $rel) => $this->edge(
            'outgoing',
            $data->shortName,
            class_basename($rel->related),
            $rel->type,
            $rel->name,
            $rel->snippet,
        ))->values()->all();
    }

    /**
     * Scans every discovered model's relations for one pointing at $className —
     * the actual new capability "who points at this model" answers.
     *
     * @return list<array{direction:string,from:string,to:string,type:string,name:string,defined_in:?string}>
     */
    private function incomingEdges(string $className): array
    {
        $useCache = (bool) config('model-explorer.mcp.cache.enabled', false);

        $graph = $this->cache->rememberWhen(
            $useCache,
            'mcp.graph.'.$this->fingerprint->forModelPaths(),
            fn () => $this->graphBuilder->build(),
        );

        $edges = [];

        foreach ($graph as $model) {
            if (strcasecmp($model['class'], $className) === 0) {
                continue;
            }

            foreach ($model['relations'] as $rel) {
                if (strcasecmp($rel['related'], $className) !== 0) {
                    continue;
                }

                $edges[] = $this->edge(
                    'incoming',
                    $model['short_name'],
                    class_basename($className),
                    $rel['type'],
                    $rel['name'],
                    $rel['snippet'],
                );
            }
        }

        return $edges;
    }

    /**
     * @param  array{file?:string, start_line?:int}|null  $snippet
     * @return array{direction:string,from:string,to:string,type:string,name:string,defined_in:?string}
     */
    private function edge(string $direction, string $from, string $to, string $type, string $name, ?array $snippet): array
    {
        return [
            'direction' => $direction,
            'from' => $from,
            'to' => $to,
            'type' => lcfirst($type),
            'name' => $name,
            'defined_in' => $this->presenter->pointer($snippet),
        ];
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'model' => $schema->string()
                ->description('Fully-qualified or short class name, e.g. "Profile". The root of the neighborhood.')
                ->required(),
            'direction' => $schema->string()
                ->description('One of: incoming, outgoing, both. Defaults to incoming — "which models point at this one," the gap not already covered by inspect-model\'s own relations section.'),
            'depth' => $schema->integer()
                ->description('Reserved for future multi-hop traversal. Only 1 (the default) is currently supported; other values error.'),
            'limit' => $schema->integer()
                ->description('Maximum number of edges to return. Defaults to 50; excess sets truncated=true.'),
        ];
    }
}
