<?php

use OneLearningCommunity\LaravelModelExplorer\LaravelModelExplorerServiceProvider;

function provider(): LaravelModelExplorerServiceProvider
{
    return new LaravelModelExplorerServiceProvider(app());
}

it('registers MCP when both flags are enabled', function () {
    config(['model-explorer.enabled' => true, 'model-explorer.mcp.enabled' => true]);

    expect(provider()->shouldRegisterMcp())->toBeTrue();
});

it('does not register MCP when the package is disabled', function () {
    config(['model-explorer.enabled' => false, 'model-explorer.mcp.enabled' => true]);

    expect(provider()->shouldRegisterMcp())->toBeFalse();
});

it('does not register MCP when mcp is disabled', function () {
    config(['model-explorer.enabled' => true, 'model-explorer.mcp.enabled' => false]);

    expect(provider()->shouldRegisterMcp())->toBeFalse();
});
