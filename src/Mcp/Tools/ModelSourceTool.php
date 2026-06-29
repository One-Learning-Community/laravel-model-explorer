<?php

namespace OneLearningCommunity\LaravelModelExplorer\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use OneLearningCommunity\LaravelModelExplorer\Data\ModelData;
use OneLearningCommunity\LaravelModelExplorer\Data\RelationData;
use OneLearningCommunity\LaravelModelExplorer\Data\ScopeData;
use OneLearningCommunity\LaravelModelExplorer\Mcp\Support\CompactPresenter;
use OneLearningCommunity\LaravelModelExplorer\Mcp\Support\ModelResolver;
use OneLearningCommunity\LaravelModelExplorer\Services\ModelInspector;

#[Description('Return the source snippet for one scope, relation, or accessor of a model — dedented and correctly attributed to the trait or parent class that defines it. Use the defined_in pointers from inspect-model to choose what to fetch.')]
class ModelSourceTool extends Tool
{
    public function __construct(
        private readonly ModelResolver $resolver,
        private readonly ModelInspector $inspector,
        private readonly CompactPresenter $presenter,
    ) {}

    public function handle(Request $request): ResponseFactory|Response
    {
        $request->validate([
            'model' => 'required|string',
            'kind' => 'required|in:scope,relation,accessor',
            'name' => 'required|string',
        ], [
            'kind.in' => 'kind must be one of: scope, relation, accessor.',
        ]);

        $model = (string) $request->get('model');
        $kind = (string) $request->get('kind');
        $name = (string) $request->get('name');

        try {
            $className = $this->resolver->resolve($model);
            // Live read by design: this tool fetches a single on-demand snippet and intentionally bypasses mcp.cache.enabled.
            $data = $this->inspector->inspect($className);
        } catch (\RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        [$snippet, $available] = $this->locate($data, $kind, $name);

        if ($snippet === null) {
            return Response::error(
                "No {$kind} named [{$name}] on {$data->shortName}. Available: ".
                (count($available) ? implode(', ', $available) : '(none)').'.'
            );
        }

        return Response::structured(array_filter([
            'code' => $snippet['code'],
            'defined_in' => $this->presenter->pointer($snippet),
            'doc' => $snippet['doc_summary'] ?? null,
        ], fn ($v) => $v !== null));
    }

    /**
     * Locate the named definition and return its snippet plus the list of
     * available names for that kind (used to build a helpful error message).
     *
     * @return array{0: ?array{code:string,file:string,start_line:int,doc_summary?:?string}, 1: list<string>}
     */
    private function locate(ModelData $data, string $kind, string $name): array
    {
        if ($kind === 'scope') {
            $available = $data->scopes->pluck('name')->all();
            $scope = $data->scopes->first(fn (ScopeData $s) => strcasecmp($s->name, $name) === 0);

            return [$scope?->snippet, $available];
        }

        if ($kind === 'relation') {
            $available = $data->relations->pluck('name')->all();
            $relation = $data->relations->first(fn (RelationData $r) => strcasecmp($r->name, $name) === 0);

            return [$relation?->snippet, $available];
        }

        // accessor
        $available = array_keys($data->accessorSnippets);

        foreach ($data->accessorSnippets as $attr => $snippet) {
            if (strcasecmp($attr, $name) === 0) {
                return [$snippet, $available];
            }
        }

        return [null, $available];
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'model' => $schema->string()
                ->description('Fully-qualified or short class name, e.g. "Order".')
                ->required(),
            'kind' => $schema->string()
                ->description('One of: scope, relation, accessor.')
                ->required(),
            'name' => $schema->string()
                ->description('The definition name, e.g. scope "active", relation "items", accessor "full_name".')
                ->required(),
        ];
    }
}
