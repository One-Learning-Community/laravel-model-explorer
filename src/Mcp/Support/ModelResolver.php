<?php

namespace OneLearningCommunity\LaravelModelExplorer\Mcp\Support;

use Illuminate\Database\Eloquent\Model;
use OneLearningCommunity\LaravelModelExplorer\Services\ModelDiscovery;

class ModelResolver
{
    public function __construct(private readonly ModelDiscovery $discovery) {}

    /**
     * Resolve an FQCN or short class name against the discovered model set.
     *
     * When `mcp.allow_undiscovered` is enabled, a fully-qualified name that
     * resolves to an Eloquent model outside `model_paths` (e.g. a vendor model)
     * is accepted too — short names stay bounded to the discovered set.
     *
     * @return class-string
     *
     * @throws \RuntimeException When unknown or ambiguous.
     */
    public function resolve(string $input): string
    {
        $needle = ltrim(trim($input), '\\');
        $discovered = $this->discovery->discoverAll();

        foreach ($discovered as $class) {
            if (strcasecmp($class, $needle) === 0) {
                return $class;
            }
        }

        $matches = array_values(array_filter(
            $discovered,
            fn (string $class) => strcasecmp(class_basename($class), $needle) === 0,
        ));

        if (count($matches) === 1) {
            return $matches[0];
        }

        if (count($matches) > 1) {
            throw new \RuntimeException(
                "Ambiguous model name [{$input}]. Use a fully-qualified class name; candidates: ".implode(', ', $matches).'.'
            );
        }

        if (class_exists($needle) && is_subclass_of($needle, Model::class)) {
            if (! config('model-explorer.mcp.allow_undiscovered', false)) {
                throw new \RuntimeException(
                    "[{$input}] is an Eloquent model but outside the configured model_paths. ".
                    'Set model-explorer.mcp.allow_undiscovered to true to inspect undiscovered models.'
                );
            }

            return (new \ReflectionClass($needle))->getName();
        }

        throw new \RuntimeException("No discovered model matches [{$input}].");
    }
}
