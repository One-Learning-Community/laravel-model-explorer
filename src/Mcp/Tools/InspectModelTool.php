<?php

namespace OneLearningCommunity\LaravelModelExplorer\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use OneLearningCommunity\LaravelModelExplorer\Mcp\Support\CompactPresenter;
use OneLearningCommunity\LaravelModelExplorer\Mcp\Support\ModelResolver;
use OneLearningCommunity\LaravelModelExplorer\Services\ExplorerCache;
use OneLearningCommunity\LaravelModelExplorer\Services\FreshModelInspector;
use OneLearningCommunity\LaravelModelExplorer\Services\SourceFingerprint;

#[Description('Inspect one model\'s structure: columns, relations, scopes, accessors, traits, mass-assignment, and policy. Always returns an overview with section counts, then the requested sections (default: columns + relations). Each scope/relation/accessor carries a defined_in "path:line" pointer. Prefer this over reading the model source file.')]
class InspectModelTool extends Tool
{
    public function __construct(
        private readonly ModelResolver $resolver,
        private readonly FreshModelInspector $inspector,
        private readonly CompactPresenter $presenter,
        private readonly ExplorerCache $cache,
        private readonly SourceFingerprint $fingerprint,
    ) {}

    public function handle(Request $request): ResponseFactory|Response
    {
        $request->validate([
            'model' => 'required|string',
        ], [
            'model.required' => 'Provide a model: a fully-qualified class name or a short class name, e.g. "App\\Models\\Order" or "Order".',
        ]);

        try {
            $className = $this->resolver->resolve($request->get('model'));
        } catch (\RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        $sections = $this->normalizeInclude((array) $request->array('include'));
        $useCache = (bool) config('model-explorer.mcp.cache.enabled', false);

        try {
            $data = $this->cache->rememberWhen(
                $useCache,
                'mcp.inspect.'.$className.'.'.$this->fingerprint->forClass($className),
                fn () => $this->inspector->inspect($className),
            );
        } catch (\RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::structured($this->presenter->inspect($data, $sections));
    }

    /**
     * @param  array<int, string>  $include
     * @return list<string>
     */
    private function normalizeInclude(array $include): array
    {
        if ($include === []) {
            return ['columns', 'relations'];
        }

        if (in_array('all', $include, true)) {
            return CompactPresenter::SECTIONS;
        }

        return array_values(array_intersect(CompactPresenter::SECTIONS, $include));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'model' => $schema->string()
                ->description('Fully-qualified class name or short class name, e.g. "App\\Models\\Order" or "Order".')
                ->required(),
            'include' => $schema->array()
                ->description('Sections to include: '.implode(', ', CompactPresenter::SECTIONS).', or "all". Defaults to columns + relations.'),
        ];
    }
}
