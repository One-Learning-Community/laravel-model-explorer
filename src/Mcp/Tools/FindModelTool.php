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
use OneLearningCommunity\LaravelModelExplorer\Services\ExplorerCache;
use OneLearningCommunity\LaravelModelExplorer\Services\FreshModelInspector;
use OneLearningCommunity\LaravelModelExplorer\Services\ModelDiscovery;
use OneLearningCommunity\LaravelModelExplorer\Services\SourceFingerprint;
use RuntimeException;
use Spatie\ModelInfo\Attributes\Attribute;

#[Description('Find models matching structural criteria: trait (uses a trait), extends (parent class), relatesTo (has a relation to a model), hasColumn (table has a column). Filters combine with AND. Answers cross-cutting questions without inspecting every model. At least one filter is required.')]
class FindModelTool extends Tool
{
    public function __construct(
        private readonly ModelDiscovery $discovery,
        private readonly FreshModelInspector $inspector,
        private readonly ExplorerCache $cache,
        private readonly SourceFingerprint $fingerprint,
    ) {}

    public function handle(Request $request): ResponseFactory|Response
    {
        $trait = $this->str($request->get('trait'));
        $extends = $this->str($request->get('extends'));
        $relatesTo = $this->str($request->get('relatesTo'));
        $hasColumn = $this->str($request->get('hasColumn'));

        if (! $trait && ! $extends && ! $relatesTo && ! $hasColumn) {
            return Response::error('Provide at least one filter: trait, extends, relatesTo, or hasColumn.');
        }

        $useCache = (bool) config('model-explorer.mcp.cache.enabled', false);
        $matches = [];

        foreach ($this->discovery->discoverAll() as $className) {
            try {
                $data = $this->cache->rememberWhen(
                    $useCache,
                    'mcp.inspect.'.$className.'.'.$this->fingerprint->forClass($className),
                    fn () => $this->inspector->inspect($className),
                );
            } catch (RuntimeException) {
                continue;
            }

            $matched = [];

            if ($trait !== null) {
                if (($hit = $this->matchTrait($data, $trait)) === null) {
                    continue;
                }
                $matched[] = 'trait: '.$hit;
            }

            if ($extends !== null) {
                if (($hit = $this->matchExtends($className, $extends)) === null) {
                    continue;
                }
                $matched[] = 'extends: '.$hit;
            }

            if ($relatesTo !== null) {
                if (($hit = $this->matchRelatesTo($data, $relatesTo)) === null) {
                    continue;
                }
                $matched[] = 'relatesTo: '.$hit;
            }

            if ($hasColumn !== null) {
                if (($hit = $this->matchColumn($data, $hasColumn)) === null) {
                    continue;
                }
                $matched[] = 'hasColumn: '.$hit;
            }

            $matches[] = [
                'class' => $data->className,
                'name' => $data->shortName,
                'table' => $data->table,
                'matched' => $matched,
            ];
        }

        return Response::structured([
            'models' => $matches,
            'count' => count($matches),
        ]);
    }

    private function str(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }

    private function matchTrait(ModelData $data, string $needle): ?string
    {
        $needle = ltrim($needle, '\\');

        foreach ($data->traits as $trait) {
            if (strcasecmp($trait, $needle) === 0 || strcasecmp(class_basename($trait), $needle) === 0) {
                return $trait;
            }
        }

        return null;
    }

    private function matchExtends(string $className, string $needle): ?string
    {
        $needle = ltrim($needle, '\\');

        foreach (class_parents($className) ?: [] as $parent) {
            if (strcasecmp($parent, $needle) === 0 || strcasecmp(class_basename($parent), $needle) === 0) {
                return $parent;
            }
        }

        return null;
    }

    private function matchRelatesTo(ModelData $data, string $needle): ?string
    {
        return $this->relatesToLabel($data, ltrim($needle, '\\'));
    }

    private function relatesToLabel(ModelData $data, string $needle): ?string
    {
        foreach ($data->relations as $rel) {
            if (strcasecmp($rel->related, $needle) === 0 || strcasecmp(class_basename($rel->related), $needle) === 0) {
                return lcfirst($rel->type).' '.class_basename($rel->related);
            }
        }

        return null;
    }

    private function matchColumn(ModelData $data, string $needle): ?string
    {
        $needle = ltrim($needle, '\\');

        foreach ($data->attributes as $attr) {
            /** @var Attribute $attr */
            if (! $attr->virtual && strcasecmp($attr->name, $needle) === 0) {
                return $attr->name;
            }
        }

        return null;
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'trait' => $schema->string()->description('Model uses this trait (short name or FQCN), e.g. "SoftDeletes".'),
            'extends' => $schema->string()->description('Model extends this class (short name or FQCN), e.g. "Authenticatable".'),
            'relatesTo' => $schema->string()->description('Model has a relation pointing at this model (short name or FQCN), e.g. "Team".'),
            'hasColumn' => $schema->string()->description('Model\'s table has this column, e.g. "team_id".'),
        ];
    }
}
