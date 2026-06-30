<?php

namespace OneLearningCommunity\LaravelModelExplorer;

use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Facades\Mcp;
use OneLearningCommunity\LaravelModelExplorer\Console\ClearCacheCommand;
use OneLearningCommunity\LaravelModelExplorer\Console\InspectCommand;
use OneLearningCommunity\LaravelModelExplorer\Mcp\ModelExplorerServer;
use OneLearningCommunity\LaravelModelExplorer\Services\ExplorerCache;
use OneLearningCommunity\LaravelModelExplorer\Services\FreshModelInspector;
use OneLearningCommunity\LaravelModelExplorer\Services\ModelDiscovery;
use OneLearningCommunity\LaravelModelExplorer\Services\ModelInspector;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelModelExplorerServiceProvider extends PackageServiceProvider
{
    public function register(): void
    {
        parent::register();

        $this->app->singleton(ModelDiscovery::class);
        $this->app->singleton(ModelInspector::class);
        $this->app->singleton(ExplorerCache::class);

        // Singleton so its "which classes have I loaded, at what mtime" registry
        // persists across calls within the long-lived MCP server process.
        $this->app->singleton(FreshModelInspector::class);
    }

    public function configurePackage(Package $package): void
    {
        $package
            ->name('model-explorer')
            ->hasConfigFile()
            ->hasViews()
            ->hasRoutes('web')
            ->hasCommand(ClearCacheCommand::class)
            ->hasCommand(InspectCommand::class);
    }

    public function packageBooted(): void
    {
        /**
         * Register a default gate that allows access in local environments only.
         *
         * Override this in your AuthServiceProvider to grant access elsewhere:
         *
         *   Gate::define('viewModelExplorer', function (User $user) {
         *       return $user->isAdmin();
         *   });
         */
        Gate::define('viewModelExplorer', function ($user = null): bool {
            return app()->environment('local');
        });

        $this->registerMcpServer();
    }

    /**
     * The MCP server registers only when the package and its MCP feature are both
     * enabled (and laravel/mcp is installed). This is the agent-surface kill switch.
     */
    public function shouldRegisterMcp(): bool
    {
        return (bool) config('model-explorer.enabled', true)
            && (bool) config('model-explorer.mcp.enabled', true)
            && class_exists(Mcp::class);
    }

    private function registerMcpServer(): void
    {
        if ($this->shouldRegisterMcp()) {
            Mcp::local('model-explorer', ModelExplorerServer::class);
        }
    }
}
