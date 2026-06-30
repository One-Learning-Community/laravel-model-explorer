<?php

use Laravel\Mcp\Server\McpServiceProvider;
use OneLearningCommunity\LaravelModelExplorer\Mcp\ModelExplorerServer;
use OneLearningCommunity\LaravelModelExplorer\Mcp\Tools\FindModelTool;

// McpServiceProvider is excluded from Testbench auto-discovery (ignorePackageDiscoveriesFrom
// returns ['*']). Its boot() registers the resolving(Request::class, ...) callback that
// populates the injected Request with arguments from the mcp.request binding. Without this,
// $request->get(...) sees an empty argument set and every filter test fails.
beforeEach(function () {
    app()->register(McpServiceProvider::class);
});

it('finds models by trait', function () {
    ModelExplorerServer::tool(FindModelTool::class, ['trait' => 'HasAuthor'])
        ->assertOk()
        ->assertSee('Post')
        ->assertSee('trait:');
});

it('finds models by base class via extends', function () {
    ModelExplorerServer::tool(FindModelTool::class, ['extends' => 'BasePost'])
        ->assertOk()
        ->assertSee('ExtendedPost');
});

it('finds models by relation target via relatesTo', function () {
    ModelExplorerServer::tool(FindModelTool::class, ['relatesTo' => 'User'])
        ->assertOk()
        ->assertSee('Post');
});

it('combines filters with AND', function () {
    ModelExplorerServer::tool(FindModelTool::class, [
        'trait' => 'HasAuthor',
        'hasColumn' => 'title',
    ])->assertOk()->assertSee('Post');
});

it('errors when no filter is provided', function () {
    ModelExplorerServer::tool(FindModelTool::class, [])->assertHasErrors();
});

it('finds models by a plain business method via definesMember', function () {
    ModelExplorerServer::tool(FindModelTool::class, ['definesMember' => 'activate'])
        ->assertOk()
        ->assertSee('Post')
        ->assertSee('definesMember:');
});

it('finds models by a trait-composed member via definesMember', function () {
    ModelExplorerServer::tool(FindModelTool::class, ['definesMember' => 'scopePublished'])
        ->assertOk()
        ->assertSee('Post');
});

it('returns no matches for definesMember when the name is not a member', function () {
    ModelExplorerServer::tool(FindModelTool::class, ['definesMember' => 'doesNotExistAnywhere'])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('count', 0)->where('models', [])->etc());
});
