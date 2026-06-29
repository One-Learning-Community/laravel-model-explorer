<?php

namespace OneLearningCommunity\LaravelModelExplorer\Mcp;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use OneLearningCommunity\LaravelModelExplorer\Mcp\Tools\InspectModelTool;
use OneLearningCommunity\LaravelModelExplorer\Mcp\Tools\ListModelsTool;
use OneLearningCommunity\LaravelModelExplorer\Mcp\Tools\RelationshipGraphTool;

#[Name('Model Explorer')]
#[Version('1.0.0')]
#[Instructions('Introspect the application\'s Eloquent models: list them, inspect one model\'s columns/relations/scopes/accessors, view the relationship graph, find models by structural criteria, and fetch source snippets. Prefer these tools over reading model source files.')]
class ModelExplorerServer extends Server
{
    /**
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     *
     * Tools are registered here as they are implemented (Tasks 5–9).
     * Tasks 6–9 each add InspectModelTool, RelationshipGraphTool, FindModelTool,
     * ModelSourceTool once those classes exist — the laravel/mcp container resolves
     * every entry at request time, so absent classes cause BindingResolutionException.
     */
    protected array $tools = [
        ListModelsTool::class,
        InspectModelTool::class,
        RelationshipGraphTool::class,
        // FindModelTool::class,        — added in Task 8
        // ModelSourceTool::class,      — added in Task 9
    ];
}
