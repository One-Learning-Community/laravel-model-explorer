<?php

namespace OneLearningCommunity\LaravelModelExplorer;

use Illuminate\Support\Facades\Gate;
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
    }

    public function configurePackage(Package $package): void
    {
        $package
            ->name('model-explorer')
            ->hasConfigFile()
            ->hasViews()
            ->hasRoutes('web');
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
    }
}
