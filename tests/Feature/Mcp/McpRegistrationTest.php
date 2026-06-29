<?php

use OneLearningCommunity\LaravelModelExplorer\LaravelModelExplorerServiceProvider;

function mcpRegistrationProvider(): LaravelModelExplorerServiceProvider
{
    return new LaravelModelExplorerServiceProvider(app());
}

it('registers MCP when both flags are enabled', function () {
    config(['model-explorer.enabled' => true, 'model-explorer.mcp.enabled' => true]);

    expect(mcpRegistrationProvider()->shouldRegisterMcp())->toBeTrue();
});

it('does not register MCP when the package is disabled', function () {
    config(['model-explorer.enabled' => false, 'model-explorer.mcp.enabled' => true]);

    expect(mcpRegistrationProvider()->shouldRegisterMcp())->toBeFalse();
});

it('does not register MCP when mcp is disabled', function () {
    config(['model-explorer.enabled' => true, 'model-explorer.mcp.enabled' => false]);

    expect(mcpRegistrationProvider()->shouldRegisterMcp())->toBeFalse();
});
