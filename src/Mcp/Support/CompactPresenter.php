<?php

namespace OneLearningCommunity\LaravelModelExplorer\Mcp\Support;

use OneLearningCommunity\LaravelModelExplorer\Data\MemberData;
use OneLearningCommunity\LaravelModelExplorer\Data\ModelData;
use OneLearningCommunity\LaravelModelExplorer\Data\RelationData;
use OneLearningCommunity\LaravelModelExplorer\Data\ScopeData;
use Spatie\ModelInfo\Attributes\Attribute;

class CompactPresenter
{
    public const array SECTIONS = ['columns', 'relations', 'scopes', 'accessors', 'traits', 'mass-assignment', 'policy', 'members'];

    /**
     * @return array<string, mixed>
     */
    public function overview(ModelData $data): array
    {
        return [
            'class' => $data->className,
            'name' => $data->shortName,
            'table' => $data->table,
            'key' => $data->keyName,
            'counts' => [
                'columns' => $data->attributes->reject(fn (Attribute $a) => $a->virtual)->count(),
                'relations' => $data->relations->count(),
                'scopes' => $data->scopes->count(),
                'accessors' => $data->attributes->filter(fn (Attribute $a) => $a->virtual)->count(),
                'traits' => count($data->traits),
                'members' => count($data->members),
            ],
        ];
    }

    /**
     * @param  list<string>  $sections
     * @param  array{kinds?: list<string>, file?: string}|null  $membersFilter  Narrows the
     *   `members` section to matching kinds and/or a declaring-file substring (ADR-012).
     * @return array<string, mixed>
     */
    public function inspect(ModelData $data, array $sections, ?array $membersFilter = null): array
    {
        $out = $this->overview($data);

        $builders = [
            'columns' => fn () => $this->columns($data),
            'relations' => fn () => $this->relations($data),
            'scopes' => fn () => $this->scopes($data),
            'accessors' => fn () => $this->accessors($data),
            'traits' => fn () => $this->traits($data),
            'mass-assignment' => fn () => $this->massAssignment($data),
            'policy' => fn () => $this->policy($data),
            'members' => fn () => $this->members($data, $membersFilter),
        ];

        foreach ($sections as $section) {
            if (isset($builders[$section])) {
                $out[$section] = ($builders[$section])();
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    public function columns(ModelData $data): array
    {
        $fkMap = $this->foreignKeyMap($data);

        return $data->attributes
            ->reject(fn (Attribute $a) => $a->virtual)
            ->map(function (Attribute $a) use ($fkMap): string {
                $parts = [$a->type ?? $a->phpType ?? 'mixed'];

                if ($a->primary) {
                    $parts[] = 'PK';
                }
                if (isset($fkMap[$a->name])) {
                    $parts[] = 'FK→'.$fkMap[$a->name];
                }
                if ($a->unique) {
                    $parts[] = 'unique';
                }
                if ($a->nullable) {
                    $parts[] = 'nullable';
                }
                if ($a->cast) {
                    $parts[] = 'cast:'.class_basename($a->cast);
                }

                return $a->name.': '.implode(' ', $parts);
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function relations(ModelData $data): array
    {
        return $data->relations->map(fn (RelationData $r) => array_filter([
            'name' => $r->name,
            'type' => lcfirst($r->type),
            'related' => class_basename($r->related),
            'via' => $r->foreignKey,
            'defined_in' => $this->pointer($r->snippet),
        ], fn ($v) => $v !== null && $v !== ''))->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function scopes(ModelData $data): array
    {
        return $data->scopes->map(fn (ScopeData $s) => array_filter([
            'name' => $s->name,
            'signature' => $this->scopeSignature($s),
            'doc' => $s->description,
            'defined_in' => $this->pointer($s->snippet),
        ], fn ($v) => $v !== null))->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function accessors(ModelData $data): array
    {
        return $data->attributes
            ->filter(fn (Attribute $a) => $a->virtual)
            ->map(function (Attribute $a) use ($data) {
                $snippet = $data->accessorSnippets[$a->name] ?? null;

                return array_filter([
                    'name' => $a->name,
                    'type' => $a->phpType,
                    'defined_in' => $this->pointer($snippet),
                ], fn ($v) => $v !== null);
            })
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function traits(ModelData $data): array
    {
        return $data->traits;
    }

    /**
     * @return array{fillable:list<string>, guarded:list<string>, hidden:list<string>}
     */
    public function massAssignment(ModelData $data): array
    {
        return [
            'fillable' => $data->fillable,
            'guarded' => $data->guarded,
            'hidden' => $data->hidden,
        ];
    }

    public function policy(ModelData $data): ?string
    {
        return $data->policyClass;
    }

    /**
     * Every first-party member as a terse string, split into methods and
     * properties (constants live alongside properties). Each carries its kind
     * and a `defined_in` pointer, e.g.
     * "markPaid(Carbon $at): void [business] @ app/Models/Order.php:88".
     *
     * `$filter['kinds']` keeps only members whose `kind` is in the list
     * (case-insensitive, e.g. `['relation', 'business']`); `$filter['file']`
     * keeps only members whose `defined_in` file contains the given substring.
     * Both narrow the surface that a noisy class (hundreds of members) returns —
     * the overview's `counts.members` always reports the unfiltered total.
     *
     * @param  array{kinds?: list<string>, file?: string}|null  $filter
     * @return array{methods: list<string>, properties: list<string>}
     */
    public function members(ModelData $data, ?array $filter = null): array
    {
        $methods = [];
        $properties = [];

        foreach ($this->filterMembers($data->members, $filter) as $member) {
            $visibility = $member->visibility === 'public' ? '' : $member->visibility.' ';
            $static = $member->static ? 'static ' : '';
            $pointer = $this->pointer($member->snippet);
            $at = $pointer !== null ? ' @ '.$pointer : '';

            if ($member->memberType === 'method') {
                $methods[] = $visibility.$static.$member->signature.' ['.$member->kind.']'.$at;
            } elseif ($member->memberType === 'constant') {
                $properties[] = $visibility.'const '.$member->name.' = '.$member->value.' [constant]'.$at;
            } else {
                $properties[] = $visibility.$static.'$'.$member->name.' ['.$member->kind.']'.$at;
            }
        }

        return ['methods' => $methods, 'properties' => $properties];
    }

    /**
     * @param  list<MemberData>  $members
     * @param  array{kinds?: list<string>, file?: string}|null  $filter
     * @return list<MemberData>
     */
    private function filterMembers(array $members, ?array $filter): array
    {
        if ($filter === null) {
            return $members;
        }

        if (! empty($filter['kinds'])) {
            $kinds = array_map('strtolower', $filter['kinds']);
            $members = array_filter($members, fn (MemberData $m) => in_array(strtolower($m->kind), $kinds, true));
        }

        if (! empty($filter['file'])) {
            $needle = $filter['file'];
            $members = array_filter($members, fn (MemberData $m) => $m->snippet !== null
                && ! empty($m->snippet['file'])
                && str_contains($m->snippet['file'], $needle));
        }

        return array_values($members);
    }

    /**
     * Format a snippet's location as a base_path-relative `path:line` pointer.
     *
     * @param  array{file?:string, start_line?:int}|null  $snippet
     */
    public function pointer(?array $snippet): ?string
    {
        if (! $snippet || empty($snippet['file'])) {
            return null;
        }

        $file = $snippet['file'];
        $base = base_path().DIRECTORY_SEPARATOR;
        $relative = str_starts_with($file, $base) ? substr($file, strlen($base)) : $file;

        $line = $snippet['start_line'] ?? null;

        return $line ? $relative.':'.$line : $relative;
    }

    /**
     * @return array<string, string> foreign-key column name => related short class name
     */
    private function foreignKeyMap(ModelData $data): array
    {
        $map = [];

        foreach ($data->relations as $rel) {
            if (in_array($rel->type, ['BelongsTo', 'MorphTo'], true) && $rel->foreignKey) {
                $map[$rel->foreignKey] = class_basename($rel->related);
            }
        }

        return $map;
    }

    private function scopeSignature(ScopeData $scope): string
    {
        $params = array_map(function (array $p): string {
            $type = $p['type'] ? $p['type'].' ' : '';
            $default = $p['has_default'] ? ' = '.$p['default'] : '';

            return $type.'$'.$p['name'].$default;
        }, $scope->parameters);

        return $scope->name.'('.implode(', ', $params).')';
    }
}
