<?php

namespace OneLearningCommunity\LaravelModelExplorer\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use OneLearningCommunity\LaravelModelExplorer\Data\MemberData;
use OneLearningCommunity\LaravelModelExplorer\Data\ModelData;
use OneLearningCommunity\LaravelModelExplorer\Data\RelationData;
use OneLearningCommunity\LaravelModelExplorer\Data\ScopeData;
use OneLearningCommunity\LaravelModelExplorer\Mcp\Support\CompactPresenter;
use OneLearningCommunity\LaravelModelExplorer\Mcp\Support\ModelResolver;
use OneLearningCommunity\LaravelModelExplorer\Services\ModelInspector;
use OneLearningCommunity\LaravelModelExplorer\Services\SourceExtractor;

#[Description('Return the source snippet for one defined member of a model (parameters: model + name, optional kind) — a scope, relation, accessor, or any other method/property/constant from inspect-model\'s members section — dedented and correctly attributed to the trait or parent class that defines it. The member to fetch is passed as "name", not "member". Use the defined_in pointers from inspect-model to choose what to fetch. Prefer this over grepping the source for getXAttribute / scopeX / relation bodies: it resolves the definition through the class\'s real trait/parent chain, so it finds members that live in traits or base classes a file grep would miss.')]
class ModelSourceTool extends Tool
{
    public function __construct(
        private readonly ModelResolver $resolver,
        private readonly ModelInspector $inspector,
        private readonly CompactPresenter $presenter,
    ) {}

    public function handle(Request $request): ResponseFactory|Response
    {
        if (($unknown = $this->unknownParams($request)) !== []) {
            return Response::error(
                'Unknown parameter'.(count($unknown) === 1 ? '' : 's').' ['.implode(', ', $unknown).']. '.
                'Accepted parameters: model (required), name (required), kind (optional). '.
                'The member to fetch goes in "name", e.g. name="full_name".'
            );
        }

        $request->validate([
            'model' => 'required|string',
            'kind' => 'nullable|string',
            'name' => 'required|string',
        ], [
            'model.required' => 'Provide a model: a fully-qualified or short class name, e.g. "App\\Models\\Order" or "Order".',
            'name.required' => 'Provide "name": the member to fetch, e.g. scope "active", relation "items", or accessor "full_name". (The parameter is "name", not "member".)',
        ]);

        $model = (string) $request->get('model');
        $kind = $request->filled('kind') ? (string) $request->get('kind') : null;
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
            $label = $kind ?? 'member';
            $hint = $available !== []
                ? 'Available: '.implode(', ', $available).'.'
                : 'Use inspect-model with include=["members"] to see every defined member.';

            return Response::error("No $label named [$name] on {$data->shortName}. $hint");
        }

        return Response::structured(array_filter([
            'code' => $snippet['code'],
            'defined_in' => $this->presenter->pointer($snippet),
            'doc' => $snippet['doc_summary'] ?? null,
        ], fn ($v) => $v !== null));
    }

    /**
     * Parameter keys the caller supplied that this tool does not accept.
     * Catches near-misses like "member" (the correct key is "name") so the
     * error can name the wrong key instead of a bare "name field is required".
     *
     * @return list<string>
     */
    private function unknownParams(Request $request): array
    {
        $accepted = ['model', 'kind', 'name'];

        return array_values(array_diff(array_keys($request->all()), $accepted));
    }

    /**
     * Locate the named definition and return its snippet plus the list of
     * available names for that kind (used to build a helpful error message).
     *
     * When `$kind` is null, every source — scopes, relations, accessors, and
     * the wider members list (business methods, lifecycle hooks, properties,
     * constants, …) — is searched in that order until `$name` matches.
     *
     * @return array{0: ?array{code:string,file:string,start_line:int,end_line?:int,doc_summary?:?string}, 1: list<string>}
     */
    private function locate(ModelData $data, ?string $kind, string $name): array
    {
        if ($kind === null || $kind === 'scope') {
            $available = $data->scopes->pluck('name')->all();
            $scope = $data->scopes->first(fn (ScopeData $s) => strcasecmp($s->name, $name) === 0);

            if ($scope !== null) {
                return [$scope->snippet, $available];
            }
            if ($kind === 'scope') {
                return [null, $available];
            }
        }

        if ($kind === null || $kind === 'relation') {
            $available = $data->relations->pluck('name')->all();
            $relation = $data->relations->first(fn (RelationData $r) => strcasecmp($r->name, $name) === 0);

            if ($relation !== null) {
                return [$relation->snippet, $available];
            }
            if ($kind === 'relation') {
                return [null, $available];
            }
        }

        if ($kind === null || $kind === 'accessor') {
            $available = array_keys($data->accessorSnippets);

            foreach ($data->accessorSnippets as $attr => $snippet) {
                if (strcasecmp($attr, $name) === 0) {
                    return [$snippet, $available];
                }
            }
            if ($kind === 'accessor') {
                return [null, $available];
            }
        }

        // Any other member: business methods, lifecycle hooks, magic methods,
        // plain methods, properties, constants, config properties, etc.
        $member = null;

        foreach ($data->members as $candidate) {
            if (strcasecmp($candidate->name, $name) !== 0) {
                continue;
            }
            if ($kind !== null && strcasecmp($candidate->kind, $kind) !== 0 && strcasecmp($candidate->memberType, $kind) !== 0) {
                continue;
            }

            $member = $candidate;
            break;
        }

        if ($member !== null) {
            return [$this->snippetForMember($data->className, $member), []];
        }

        return [null, []];
    }

    /**
     * @param  class-string  $className
     * @return array{code:string,file:string,start_line:int,end_line?:int,doc_summary?:?string}|null
     */
    private function snippetForMember(string $className, MemberData $member): ?array
    {
        if ($member->memberType === 'method') {
            try {
                return SourceExtractor::forMethod(new \ReflectionMethod($className, $member->name));
            } catch (\ReflectionException) {
                return null;
            }
        }

        if ($member->snippet === null || empty($member->snippet['file'])) {
            return null;
        }

        return SourceExtractor::forDeclarationLine($member->snippet['file'], $member->snippet['start_line'] ?? null);
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
                ->description('Optional. Narrows the lookup: scope, relation, accessor, or any other members kind (business, lifecycle, magic, method, property, constant, config). Omit to resolve by name alone.'),
            'name' => $schema->string()
                ->description('The member to fetch, by its name — e.g. scope "active", relation "items", accessor "full_name", or any other member like "markPaid". This is the parameter to put the member in (it is "name", not "member").')
                ->required(),
        ];
    }
}
