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

it('returns the members section with kinds and provenance when requested', function () {
    $response = ModelExplorerServer::tool(InspectModelTool::class, [
        'model' => 'Post',
        'include' => ['members'],
    ]);

    $response->assertOk()
        ->assertSee('members')
        ->assertSee('[business]')        // activate() — a plain method
        ->assertSee('[relation]')        // user()/author()
        ->assertSee('HasAuthor.php')     // trait-provided member points at the trait file
        ->assertDontSee('newQuery');     // inherited framework methods are excluded
});

it('omits the members section from the default include', function () {
    ModelExplorerServer::tool(InspectModelTool::class, ['model' => 'Post'])
        ->assertOk()
        ->assertDontSee('[business]');
});

it('filters the members section to a single kind via members:<kind>', function () {
    $response = ModelExplorerServer::tool(InspectModelTool::class, [
        'model' => 'Post',
        'include' => ['members:business'],
    ]);

    $response->assertOk()
        ->assertSee('[business]')
        ->assertDontSee('[relation]')
        ->assertDontSee('[config]');
});

it('filters the members section to multiple kinds via members:<kind1>,<kind2>', function () {
    $response = ModelExplorerServer::tool(InspectModelTool::class, [
        'model' => 'Post',
        'include' => ['members:relation,business'],
    ]);

    $response->assertOk()
        ->assertSee('[business]')
        ->assertSee('[relation]')
        ->assertDontSee('[config]');
});

it('filters the members section by declaring file via members:file=<substring>', function () {
    $response = ModelExplorerServer::tool(InspectModelTool::class, [
        'model' => 'Post',
        'include' => ['members:file=HasAuthor.php'],
    ]);

    $response->assertOk()
        ->assertSee('HasAuthor.php')
        ->assertDontSee('[business]');
});

it('does not let a members filter shrink the overview member count', function () {
    $structured = null;

    ModelExplorerServer::tool(InspectModelTool::class, [
        'model' => 'Post',
        'include' => ['members:business'],
    ])->assertOk()->assertStructuredContent(function ($json) use (&$structured) {
        $structured = $json->toArray();
        $json->etc();
    });

    expect($structured['counts']['members'])->toBeGreaterThan(count($structured['members']['methods']));
});

it('errors with an actionable message for an unknown model', function () {
    ModelExplorerServer::tool(InspectModelTool::class, ['model' => 'Nope'])
        ->assertHasErrors();
});
