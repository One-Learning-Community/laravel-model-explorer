<?php

namespace OneLearningCommunity\LaravelModelExplorer\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class Authorize
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('model-explorer.enabled', true)) {
            abort(404);
        }

        if (! Gate::check('viewModelExplorer')) {
            abort(403);
        }

        return $next($request);
    }
}
