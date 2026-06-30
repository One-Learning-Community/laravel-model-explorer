<?php

use Laravel\Mcp\Server\McpServiceProvider;
use OneLearningCommunity\LaravelModelExplorer\Mcp\ModelExplorerServer;
use OneLearningCommunity\LaravelModelExplorer\Mcp\Tools\ModelSourceTool;

// McpServiceProvider is excluded from Testbench auto-discovery (ignorePackageDiscoveriesFrom
// returns ['*']). Its boot() registers the resolving(Request::class, ...) callback that
// populates the injected Request with arguments from the mcp.request binding. Without this,
// $request->validate() sees an empty argument set and fails every tool call.
beforeEach(function () {
    app()->register(McpServiceProvider::class);
});

it('returns the trait-correct snippet for a relation', function () {
    $response = ModelExplorerServer::tool(ModelSourceTool::class, [
        'model' => 'Post',
        'kind' => 'relation',
        'name' => 'author',
    ]);

    $response->assertOk()
        ->assertSee('belongsTo')
        ->assertSee('HasAuthor.php');
});

it('returns the snippet for a scope', function () {
    ModelExplorerServer::tool(ModelSourceTool::class, [
        'model' => 'Post',
        'kind' => 'scope',
        'name' => 'recent',
    ])->assertOk()->assertSee('subDays');
});

it('errors and lists available names for an unknown definition', function () {
    ModelExplorerServer::tool(ModelSourceTool::class, [
        'model' => 'Post',
        'kind' => 'scope',
        'name' => 'nope',
    ])->assertHasErrors();
});

it('errors when no member matches the given name', function () {
    ModelExplorerServer::tool(ModelSourceTool::class, [
        'model' => 'Post',
        'kind' => 'column',
        'name' => 'title',
    ])->assertHasErrors();
});

it('fetches a plain business method by name without a kind', function () {
    ModelExplorerServer::tool(ModelSourceTool::class, [
        'model' => 'Post',
        'name' => 'activate',
    ])->assertOk()
        ->assertSee('is_published = true')
        ->assertSee('Post.php');
});

it('fetches a plain business method by name with kind business', function () {
    ModelExplorerServer::tool(ModelSourceTool::class, [
        'model' => 'Post',
        'kind' => 'business',
        'name' => 'activate',
    ])->assertOk()->assertSee('is_published = true');
});

it('fetches a relation by name without a kind, via the wider member list', function () {
    ModelExplorerServer::tool(ModelSourceTool::class, [
        'model' => 'Post',
        'name' => 'author',
    ])->assertOk()->assertSee('belongsTo');
});

it('fetches a config property declaration by name', function () {
    ModelExplorerServer::tool(ModelSourceTool::class, [
        'model' => 'Post',
        'kind' => 'config',
        'name' => 'fillable',
    ])->assertOk()->assertSee('fillable');
});

it('errors with a members hint when an unknown member name is given without a kind', function () {
    ModelExplorerServer::tool(ModelSourceTool::class, [
        'model' => 'Post',
        'name' => 'nopeNotAMember',
    ])->assertHasErrors();
});
