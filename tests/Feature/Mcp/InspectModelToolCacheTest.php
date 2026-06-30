<?php

use Laravel\Mcp\Server\McpServiceProvider;
use OneLearningCommunity\LaravelModelExplorer\Data\ModelData;
use OneLearningCommunity\LaravelModelExplorer\Mcp\ModelExplorerServer;
use OneLearningCommunity\LaravelModelExplorer\Mcp\Tools\InspectModelTool;
use OneLearningCommunity\LaravelModelExplorer\Services\FreshModelInspector;
use OneLearningCommunity\LaravelModelExplorer\Services\ModelInspector;
use Workbench\App\Models\Post;

beforeEach(function () {
    app()->register(McpServiceProvider::class);
});

it('serves a cached inspection but recomputes after the model file changes', function () {
    config(['model-explorer.mcp.cache.enabled' => true]);

    // Spy at the FreshModelInspector boundary, delegating in-process, so this test
    // isolates cache-key invalidation from the (separately tested) freshness routing.
    $spy = new class extends FreshModelInspector
    {
        public static int $calls = 0;

        public function __construct() {}

        public function inspect(string $className): ModelData
        {
            self::$calls++;

            return app(ModelInspector::class)->inspect($className);
        }
    };
    $spy::$calls = 0;
    app()->instance(FreshModelInspector::class, $spy);

    $file = (new ReflectionClass(Post::class))->getFileName();
    $original = filemtime($file);

    ModelExplorerServer::tool(InspectModelTool::class, ['model' => 'Post'])->assertOk();
    ModelExplorerServer::tool(InspectModelTool::class, ['model' => 'Post'])->assertOk();

    expect($spy::$calls)->toBe(1); // second call served from cache, not recomputed

    touch($file, $original + 10);
    ModelExplorerServer::tool(InspectModelTool::class, ['model' => 'Post'])->assertOk();
    touch($file, $original);

    expect($spy::$calls)->toBe(2); // changed file invalidated the cache → recomputed
});
