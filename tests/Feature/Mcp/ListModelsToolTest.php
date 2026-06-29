<?php

use OneLearningCommunity\LaravelModelExplorer\Mcp\ModelExplorerServer;
use OneLearningCommunity\LaravelModelExplorer\Mcp\Tools\ListModelsTool;

it('lists discovered models with class, name and table', function () {
    $response = ModelExplorerServer::tool(ListModelsTool::class, []);

    $response->assertOk()
        ->assertSee('Post')
        ->assertSee('posts')
        ->assertSee(addslashes(\Workbench\App\Models\User::class));
});
