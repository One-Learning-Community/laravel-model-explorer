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
use OneLearningCommunity\LaravelModelExplorer\Mcp\Tools\ModelSourceTool;

#[Name('Model Explorer')]
#[Version('1.0.0')]
#[Instructions('Introspect the application\'s Eloquent models: list them, inspect one model\'s columns/relations/scopes/accessors, find models by structural criteria, and fetch source snippets. Prefer these tools over reading model source files.')]
class ModelExplorerServer extends Server
{
    /**
     * @var array<int, class-string<Tool>>
     *
     * The relationship-graph tool was retired (ADR-012): it could only return the
     * whole graph, which overflows the client at real scale. GraphBuilder and the
     * HTTP/SPA graph endpoint remain for the human surface; a scoped graph tool may
     * return later. The laravel/mcp container resolves every entry at request time.
     */
    protected array $tools = [
        ListModelsTool::class,
        InspectModelTool::class,
        FindModelTool::class,
        ModelSourceTool::class,
    ];
}
