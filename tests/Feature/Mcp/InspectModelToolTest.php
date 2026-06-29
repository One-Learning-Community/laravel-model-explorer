<?php

use Laravel\Mcp\Server\McpServiceProvider;
use OneLearningCommunity\LaravelModelExplorer\Mcp\ModelExplorerServer;
use OneLearningCommunity\LaravelModelExplorer\Mcp\Tools\InspectModelTool;

// McpServiceProvider is excluded from Testbench auto-discovery (ignorePackageDiscoveriesFrom
// returns ['*']). Its boot() registers the resolving(Request::class, ...) callback that
// populates the injected Request with arguments from the mcp.request binding. Without this,
// $request->validate() sees an empty argument set and fails every tool call.
beforeEach(function () {
    app()->register(McpServiceProvider::class);
});

it('returns overview + columns + relations by default', function () {
    $response = ModelExplorerServer::tool(InspectModelTool::class, ['model' => 'Post']);

    $response->assertOk()
        ->assertSee('counts')
        ->assertSee('FK→User')
        ->assertSee('author');
});

it('honours an explicit include of a single section', function () {
    $response = ModelExplorerServer::tool(InspectModelTool::class, [
        'model' => 'Post',
        'include' => ['scopes'],
    ]);

    $response->assertOk()
        ->assertSee('published')
        ->assertSee('HasPublishedState.php');
});

it('include all returns every section', function () {
    $response = ModelExplorerServer::tool(InspectModelTool::class, [
        'model' => 'Post',
        'include' => ['all'],
    ]);

    $response->assertOk()
        ->assertSee('fillable')
        ->assertSee('summary');
});

it('errors with an actionable message for an unknown model', function () {
    ModelExplorerServer::tool(InspectModelTool::class, ['model' => 'Nope'])
        ->assertHasErrors();
});
