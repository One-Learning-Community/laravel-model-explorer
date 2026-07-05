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

#[Description('Inspect one model\'s structure: columns, relations, scopes, accessors, traits, mass-assignment, policy, and members. Always returns an overview with section counts, then the requested sections (default: columns + relations). Each scope/relation/accessor carries a defined_in "path:line" pointer. The members section can be narrowed with "members:<kind1>,<kind2>" (e.g. "members:relation,business") or "members:file=<substring>" to avoid returning a noisy class\'s whole surface. Prefer this over reading the model source file.')]
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

        $include = $request->array('include');
        $sections = $this->normalizeInclude($include);
        $membersFilter = $this->membersFilter($include);
        $enumCaseLimit = $this->enumCaseLimit($request);
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

        return Response::structured($this->presenter->inspect($data, $sections, $membersFilter, $enumCaseLimit));
    }

    /**
     * Resolve how many enum cases to expand inline: the per-request `enum_case_limit`
     * override when supplied (clamped to ≥ 0), otherwise the configured default. 0
     * omits enum cases entirely.
     */
    private function enumCaseLimit(Request $request): int
    {
        $override = $request->get('enum_case_limit');

        if ($override !== null && $override !== '') {
            return max(0, (int) $override);
        }

        return max(0, (int) config('model-explorer.mcp.enum_case_limit', CompactPresenter::ENUM_CASE_LIMIT));
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

        // A "members:..." filter token still requests the members section itself.
        $sections = array_map(
            fn ($token) => is_string($token) && str_starts_with($token, 'members:') ? 'members' : $token,
            $include,
        );

        return array_values(array_unique(array_intersect(CompactPresenter::SECTIONS, $sections)));
    }

    /**
     * Parses a "members:relation,business" (kind filter) or "members:file=Order.php"
     * (declaring-file substring filter) token out of `include`, if present.
     *
     * @param  array<int, string>  $include
     * @return array{kinds?: list<string>, file?: string}|null
     */
    private function membersFilter(array $include): ?array
    {
        foreach ($include as $token) {
            if (! is_string($token) || ! str_starts_with($token, 'members:')) {
                continue;
            }

            $filter = substr($token, strlen('members:'));

            if (str_starts_with($filter, 'file=')) {
                return ['file' => substr($filter, strlen('file='))];
            }

            return ['kinds' => array_values(array_filter(array_map('trim', explode(',', $filter))))];
        }

        return null;
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
                ->description('Sections to include: '.implode(', ', CompactPresenter::SECTIONS).', or "all". Defaults to columns + relations. '.
                    'Narrow "members" with "members:<kind1>,<kind2>" (kinds: relation, scope, accessor, lifecycle, business, magic, method, config, constant, property) or "members:file=<substring>" to match a defined_in file substring.'),
            'enum_case_limit' => $schema->integer()
                ->min(0)
                ->description('Max enum cases expanded inline per column (backed enums show Name=value). Set to 0 to omit enum cases entirely — useful for a low-token survey across many models. Omit to use the configured default (12).'),
        ];
    }
}
