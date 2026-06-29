<?php

use OneLearningCommunity\LaravelModelExplorer\Mcp\ModelExplorerServer;
use OneLearningCommunity\LaravelModelExplorer\Mcp\Tools\RelationshipGraphTool;

it('returns nodes and edges for the model graph', function () {
    $response = ModelExplorerServer::tool(RelationshipGraphTool::class, []);

    $response->assertOk()
        ->assertSee('nodes')
        ->assertSee('edges')
        ->assertSee('belongsTo')
        ->assertSee('hasMany');
});
