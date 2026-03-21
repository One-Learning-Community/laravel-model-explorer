<?php

use Illuminate\Support\Facades\Route;
use OneLearningCommunity\LaravelModelExplorer\Http\Controllers\AssetController;
use OneLearningCommunity\LaravelModelExplorer\Http\Controllers\ModelExplorerController;
use OneLearningCommunity\LaravelModelExplorer\Http\Middleware\Authorize;

// Asset route — no auth middleware, assets are not sensitive.
// Registered first so it takes priority over the SPA catch-all below.
Route::prefix(config('model-explorer.path') . '/assets')
    ->middleware('web')
    ->name('model-explorer.assets.')
    ->group(function () {
        Route::get('/{path}', AssetController::class)
            ->where('path', '.*')
            ->name('serve');
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
