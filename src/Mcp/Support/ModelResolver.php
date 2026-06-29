<?php

namespace OneLearningCommunity\LaravelModelExplorer\Mcp\Support;

use OneLearningCommunity\LaravelModelExplorer\Services\ModelDiscovery;

class ModelResolver
{
    public function __construct(private readonly ModelDiscovery $discovery) {}

    /**
     * Resolve an FQCN or short class name against the discovered model set.
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

        throw new \RuntimeException("No discovered model matches [{$input}].");
    }
}
