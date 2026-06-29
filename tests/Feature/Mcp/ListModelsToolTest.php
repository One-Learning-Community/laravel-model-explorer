<?php

use OneLearningCommunity\LaravelModelExplorer\Mcp\ModelExplorerServer;
use OneLearningCommunity\LaravelModelExplorer\Mcp\Tools\ListModelsTool;
use Workbench\App\Models\User;

it('lists discovered models with class, name and table', function () {
    $response = ModelExplorerServer::tool(ListModelsTool::class, []);

    $response->assertOk()
        ->assertSee('Post')
        ->assertSee('posts')
        ->assertSee(addslashes(User::class));
});
