<?php

namespace OneLearningCommunity\LaravelModelExplorer\Http\Controllers;

use Illuminate\Contracts\View\View;

class ModelExplorerController
{
    public function __invoke(): View
    {
        return view('model-explorer::app');
    }
}
