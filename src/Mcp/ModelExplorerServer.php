<?php

namespace OneLearningCommunity\LaravelModelExplorer\Mcp;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Tool;
use OneLearningCommunity\LaravelModelExplorer\Mcp\Tools\FindModelTool;
use OneLearningCommunity\LaravelModelExplorer\Mcp\Tools\InspectModelTool;
use OneLearningCommunity\LaravelModelExplorer\Mcp\Tools\ListModelsTool;
use OneLearningCommunity\LaravelModelExplorer\Mcp\Tools\ModelNeighborsTool;
use OneLearningCommunity\LaravelModelExplorer\Mcp\Tools\ModelSourceTool;

#[Name('Model Explorer')]
#[Version('1.0.0')]
#[Instructions('Introspect the application\'s Eloquent models: list them, inspect one model\'s columns/relations/scopes/accessors/members, find models by structural criteria, fetch source snippets, and query a model\'s depth-1 relation neighborhood. Prefer these tools over reading model source files. They answer what is *defined* on a model and where — model structure, not code usage. To find where a model or method is *referenced or called* across the codebase (including non-PHP files), use a text search such as grep instead.')]
class ModelExplorerServer extends Server
{
    /**
     * @var array<int, class-string<Tool>>
     *
     * The whole-graph relationship-graph tool was retired (ADR-012): it could only
     * return the entire graph, which overflows the client at real scale. GraphBuilder
     * and the HTTP/SPA graph endpoint remain for the human surface. The scoped return
     * ADR-012 anticipated shipped as ModelNeighborsTool (ADR-013): a bounded, single-
     * model neighborhood instead of a dump. The laravel/mcp container resolves every
     * entry at request time.
     */
    protected array $tools = [
        ListModelsTool::class,
        InspectModelTool::class,
        FindModelTool::class,
        ModelSourceTool::class,
        ModelNeighborsTool::class,
    ];
}
