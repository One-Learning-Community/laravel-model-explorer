<?php

namespace OneLearningCommunity\LaravelModelExplorer\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use OneLearningCommunity\LaravelModelExplorer\LaravelModelExplorerServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'OneLearningCommunity\\LaravelModelExplorer\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app): array
    {
        return [
            LaravelModelExplorerServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }
}
