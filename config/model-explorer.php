<?php

// config for OneLearningCommunity/LaravelModelExplorer
return [

    /*
    |--------------------------------------------------------------------------
    | Enabled
    |--------------------------------------------------------------------------
    | When set to false, the Model Explorer will return 404 for all routes.
    | Use this to disable the tool in environments where it should never
    | be accessible, regardless of gate configuration.
    */
    'enabled' => env('MODEL_EXPLORER_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Path
    |--------------------------------------------------------------------------
    | The URL prefix under which Model Explorer will be available.
    | e.g. https://your-app.test/_model-explorer
    */
    'path' => env('MODEL_EXPLORER_PATH', '_model-explorer'),

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    | Middleware applied to all Model Explorer routes. The Authorize middleware
    | is always appended automatically — add any additional middleware here
    | (e.g. 'auth', 'throttle').
    */
    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Model Paths
    |--------------------------------------------------------------------------
    | Directories that will be scanned for Eloquent models. Paths should be
    | absolute. Defaults to the standard app/Models directory.
    */
    'model_paths' => [
        'App' => app_path('Models'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Excluded Trait Prefixes
    |--------------------------------------------------------------------------
    | Traits whose fully-qualified class name begins with any of these prefixes
    | will be hidden from the Model Explorer UI. Add prefixes for any packages
    | that introduce internal traits you don't want surfaced (e.g. ORMs,
    | audit packages, or code-generation tools).
    |
    | The default excludes Laravel's internal model concern traits (HasAttributes,
    | HasRelationships, etc.) while keeping useful traits like SoftDeletes visible.
    */
    'excluded_trait_prefixes' => [
        'Illuminate\\Database\\Eloquent\\Concerns\\',
        'Illuminate\\Database\\Eloquent\\HasCollection',
        'Illuminate\\Support\\Traits\\',
    ],

];
