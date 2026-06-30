<?php

use Laravel\Mcp\Server\McpServiceProvider;
use OneLearningCommunity\LaravelModelExplorer\Mcp\ModelExplorerServer;
use OneLearningCommunity\LaravelModelExplorer\Mcp\Tools\ModelNeighborsTool;
use Workbench\App\Models\User;

// McpServiceProvider is excluded from Testbench auto-discovery (ignorePackageDiscoveriesFrom
// returns ['*']). Its boot() registers the resolving(Request::class, ...) callback that
// populates the injected Request with arguments from the mcp.request binding. Without this,
// $request->validate() sees an empty argument set and fails every tool call.
beforeEach(function () {
    app()->register(McpServiceProvider::class);
});

function neighborsStructured(array $arguments): array
{
    $structured = null;

    ModelExplorerServer::tool(ModelNeighborsTool::class, $arguments)
        ->assertOk()
        ->assertStructuredContent(function ($json) use (&$structured) {
            $structured = $json->toArray();
            $json->etc();
        });

    return $structured;
}

it('defaults to the incoming direction, finding every model that points at the root', function () {
    $structured = neighborsStructured(['model' => 'User']);

    expect($structured['root'])->toBe(User::class)
        ->and($structured['direction'])->toBe('incoming')
        ->and(collect($structured['edges'])->pluck('from'))->toContain('Post', 'BasePost', 'ExtendedPost', 'MemberShowcase')
        ->and(collect($structured['edges'])->every(fn ($e) => $e['direction'] === 'incoming'))->toBeTrue();
});

it('returns outgoing edges when requested', function () {
    $structured = neighborsStructured(['model' => 'User', 'direction' => 'outgoing']);

    expect(collect($structured['edges'])->contains(fn ($e) => $e['from'] === 'User' && $e['to'] === 'Post' && $e['name'] === 'posts'))->toBeTrue()
        ->and(collect($structured['edges'])->every(fn ($e) => $e['direction'] === 'outgoing'))->toBeTrue();
});

it('merges both directions when direction=both', function () {
    $structured = neighborsStructured(['model' => 'User', 'direction' => 'both']);

    $directions = collect($structured['edges'])->pluck('direction')->unique()->all();

    expect($directions)->toContain('incoming')->toContain('outgoing');
});

it('includes a trait-correct defined_in pointer on an incoming edge', function () {
    $structured = neighborsStructured(['model' => 'User']);

    $authorEdge = collect($structured['edges'])->firstWhere('name', 'author');

    expect($authorEdge)->not->toBeNull()
        ->and($authorEdge['defined_in'])->toContain('HasAuthor.php');
});

it('caps edges at limit and sets truncated', function () {
    $structured = neighborsStructured(['model' => 'User', 'limit' => 1]);

    expect($structured['edges'])->toHaveCount(1)
        ->and($structured['count'])->toBe(1)
        ->and($structured['truncated'])->toBeTrue();
});

it('does not truncate when every edge fits under the limit', function () {
    $structured = neighborsStructured(['model' => 'User']);

    expect($structured['truncated'])->toBeFalse();
});

it('rejects a depth other than 1', function () {
    ModelExplorerServer::tool(ModelNeighborsTool::class, ['model' => 'User', 'depth' => 2])
        ->assertHasErrors();
});

it('errors for an unknown model', function () {
    ModelExplorerServer::tool(ModelNeighborsTool::class, ['model' => 'Nope'])
        ->assertHasErrors();
});

it('rejects an invalid direction', function () {
    ModelExplorerServer::tool(ModelNeighborsTool::class, ['model' => 'User', 'direction' => 'sideways'])
        ->assertHasErrors();
});
