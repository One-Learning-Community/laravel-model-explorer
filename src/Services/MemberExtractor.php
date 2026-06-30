<?php

namespace OneLearningCommunity\LaravelModelExplorer\Services;

use Illuminate\Database\Eloquent\Casts\Attribute as EloquentAttribute;
use Illuminate\Database\Eloquent\Relations\Relation;
use OneLearningCommunity\LaravelModelExplorer\Data\MemberData;

/**
 * Enumerates the members (methods, properties, constants) a model actually
 * defines, with provenance and a heuristic `kind` — the "skeleton, not just
 * behaviour" surface of ADR-012 §C.
 *
 * Boundary (load-bearing): only members whose definition lives in a first-party
 * file — i.e. NOT under a `vendor/` directory — are returned. This excludes the
 * hundreds of inherited Illuminate\Database\Eloquent\Model methods (save, delete,
 * newQuery, …) that would otherwise drown the result, and subsumes the
 * `excluded_trait_prefixes` boundary used elsewhere (those traits all ship in
 * vendor/). Trait-provided members point at the trait file, not the model.
 */
class MemberExtractor
{
    /** Eloquent/host configuration properties surfaced with kind `config`. */
    private const CONFIG_PROPS = [
        'table', 'connection', 'primaryKey', 'keyType', 'incrementing', 'timestamps',
        'fillable', 'guarded', 'hidden', 'visible', 'casts', 'appends', 'with',
        'withCount', 'perPage', 'dateFormat', 'dates', 'touches', 'dispatchesEvents',
        'observables', 'attributes',
    ];

    /**
     * @param  class-string  $className
     * @param  list<string>  $relationNames  Discovered relation method names (for kind tagging).
     * @return list<MemberData>
     */
    public static function forModel(string $className, array $relationNames = []): array
    {
        try {
            $reflection = new \ReflectionClass($className);
        } catch (\Throwable) {
            return [];
        }

        $members = [];

        foreach ($reflection->getMethods() as $method) {
            if (self::isFirstParty($method->getFileName())) {
                $members[] = self::methodMember($method, $relationNames);
            }
        }

        foreach ($reflection->getProperties() as $property) {
            $file = $property->getDeclaringClass()->getFileName();
            if (self::isFirstParty($file)) {
                $members[] = self::propertyMember($property, $file);
            }
        }

        foreach ($reflection->getReflectionConstants() as $constant) {
            $file = $constant->getDeclaringClass()->getFileName();
            if (self::isFirstParty($file)) {
                $members[] = self::constantMember($constant, $file);
            }
        }

        usort($members, fn (MemberData $a, MemberData $b) => [$a->memberType, $a->name] <=> [$b->memberType, $b->name]);

        return $members;
    }

    private static function isFirstParty(string|false $file): bool
    {
        return is_string($file)
            && $file !== ''
            && ! str_contains($file, DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR);
    }

    /**
     * @param  list<string>  $relationNames
     */
    private static function methodMember(\ReflectionMethod $method, array $relationNames): MemberData
    {
        $snippet = SourceExtractor::forMethod($method);

        return new MemberData(
            name: $method->getName(),
            memberType: 'method',
            kind: self::methodKind($method, $relationNames),
            visibility: self::visibility($method),
            static: $method->isStatic(),
            signature: self::signature($method),
            snippet: $snippet !== null ? ['file' => $snippet['file'], 'start_line' => $snippet['start_line']] : null,
        );
    }

    private static function propertyMember(\ReflectionProperty $property, string $file): MemberData
    {
        $name = $property->getName();
        $line = self::declarationLine($file, '/(?:public|protected|private|static|readonly|var)\s[^;]*\$'.preg_quote($name, '/').'\b/');

        return new MemberData(
            name: $name,
            memberType: 'property',
            kind: in_array($name, self::CONFIG_PROPS, true) ? 'config' : 'property',
            visibility: self::visibility($property),
            static: $property->isStatic(),
            snippet: ['file' => $file, 'start_line' => $line],
        );
    }

    private static function constantMember(\ReflectionClassConstant $constant, string $file): MemberData
    {
        $line = self::declarationLine($file, '/\bconst\s+'.preg_quote($constant->getName(), '/').'\b/');

        return new MemberData(
            name: $constant->getName(),
            memberType: 'constant',
            kind: 'constant',
            visibility: self::visibility($constant),
            static: false,
            value: self::renderValue($constant->getValue()),
            snippet: ['file' => $file, 'start_line' => $line],
        );
    }

    /**
     * @param  list<string>  $relationNames
     */
    private static function methodKind(\ReflectionMethod $method, array $relationNames): string
    {
        $name = $method->getName();

        if (in_array($name, $relationNames, true)) {
            return 'relation';
        }

        if (preg_match('/^scope[A-Z]/', $name)) {
            return 'scope';
        }

        if (self::isAccessor($method)) {
            return 'accessor';
        }

        if (in_array($name, ['boot', 'booted', 'booting'], true) || preg_match('/^(boot|initialize)[A-Z]/', $name)) {
            return 'lifecycle';
        }

        $returnType = $method->getReturnType();
        if ($returnType instanceof \ReflectionNamedType && ! $returnType->isBuiltin() && is_a($returnType->getName(), Relation::class, true)) {
            return 'relation';
        }

        if (str_starts_with($name, '__')) {
            return 'magic';
        }

        return $method->isPublic() ? 'business' : 'method';
    }

    private static function isAccessor(\ReflectionMethod $method): bool
    {
        if (preg_match('/^get[A-Z]\w*Attribute$/', $method->getName())) {
            return true;
        }

        $returnType = $method->getReturnType();

        return $returnType instanceof \ReflectionNamedType
            && ! $returnType->isBuiltin()
            && is_a($returnType->getName(), EloquentAttribute::class, true);
    }

    private static function signature(\ReflectionMethod $method): string
    {
        $params = array_map(function (\ReflectionParameter $param): string {
            $type = self::typeToString($param->getType());
            $prefix = $type !== null ? $type.' ' : '';
            $variadic = $param->isVariadic() ? '...' : '';
            $default = $param->isOptional() && $param->isDefaultValueAvailable()
                ? ' = '.self::renderValue($param->getDefaultValue())
                : '';

            return $prefix.$variadic.'$'.$param->getName().$default;
        }, $method->getParameters());

        $return = self::typeToString($method->getReturnType());

        return $method->getName().'('.implode(', ', $params).')'.($return !== null ? ': '.$return : '');
    }

    private static function typeToString(?\ReflectionType $type): ?string
    {
        if ($type instanceof \ReflectionNamedType) {
            $name = $type->isBuiltin() ? $type->getName() : class_basename($type->getName());
            $nullable = $type->allowsNull() && ! in_array($type->getName(), ['null', 'mixed'], true) ? '?' : '';

            return $nullable.$name;
        }

        if ($type instanceof \ReflectionUnionType) {
            return implode('|', array_map(fn ($t) => self::typeToString($t), $type->getTypes()));
        }

        if ($type instanceof \ReflectionIntersectionType) {
            return implode('&', array_map(fn ($t) => self::typeToString($t), $type->getTypes()));
        }

        return null;
    }

    private static function renderValue(mixed $value): string
    {
        return match (true) {
            is_null($value) => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_string($value) => "'".$value."'",
            is_array($value) => '[…]',
            is_int($value), is_float($value) => (string) $value,
            default => '…',
        };
    }

    private static function visibility(\ReflectionMethod|\ReflectionProperty|\ReflectionClassConstant $member): string
    {
        return match (true) {
            $member->isPrivate() => 'private',
            $member->isProtected() => 'protected',
            default => 'public',
        };
    }

    private static function declarationLine(string $file, string $pattern): ?int
    {
        $lines = @file($file, FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            return null;
        }

        foreach ($lines as $index => $line) {
            if (preg_match($pattern, $line)) {
                return $index + 1;
            }
        }

        return null;
    }
}
