<?php

use Illuminate\Support\Facades\Route;
use OneLearningCommunity\LaravelModelExplorer\Http\Controllers\Api\GraphController;
use OneLearningCommunity\LaravelModelExplorer\Http\Controllers\Api\ModelsController;
use OneLearningCommunity\LaravelModelExplorer\Http\Controllers\AssetController;
use OneLearningCommunity\LaravelModelExplorer\Http\Controllers\ModelExplorerController;
use OneLearningCommunity\LaravelModelExplorer\Http\Middleware\Authorize;

// Asset route — no auth middleware, assets are not sensitive.
// Registered first so it takes priority over the SPA catch-all below.
Route::prefix(config('model-explorer.path').'/assets')
    ->middleware('web')
    ->name('model-explorer.assets.')
    ->group(function () {
        Route::get('/{path}', AssetController::class)
            ->where('path', '.*')
            ->name('serve');
    });

// API routes — JSON endpoints for model discovery and inspection.
Route::prefix(config('model-explorer.path').'/api')
    ->middleware([...config('model-explorer.middleware', ['web']), Authorize::class])
    ->name('model-explorer.api.')
    ->group(function () {
        Route::get('/models', [ModelsController::class, 'index'])->name('models.index');
        Route::get('/models/{model}', [ModelsController::class, 'show'])->name('models.show');
        Route::get('/graph', GraphController::class)->name('graph');
    });

// SPA shell — all non-asset requests are handled by the Vue router client-side.
Route::prefix(config('model-explorer.path'))
    ->middleware([...config('model-explorer.middleware', ['web']), Authorize::class])
    ->name('model-explorer.')
    ->group(function () {
        Route::get('/{any?}', ModelExplorerController::class)
            ->where('any', '.*')
            ->name('index');
    });
