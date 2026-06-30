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
        'App' => app_path(),
    ],

    /*
    |--------------------------------------------------------------------------
    | Excluded Models
    |--------------------------------------------------------------------------
    | Model classes matching any of these patterns are hidden from the Model
    | Explorer, even when they live inside a scanned path. Useful for hiding
    | noise from third-party packages (Telescope, Passport, Horizon, etc.).
    |
    | Each entry is matched against the fully-qualified class name and may use
    | `*` as a wildcard. Leading backslashes are ignored. Examples:
    |   'App\\Models\\Internal\\AuditLog'   exact class
    |   'Laravel\\Telescope\\*'             whole namespace
    |   '*\\PersonalAccessToken'            any class with this short name
    */
    'excluded_models' => [
        // 'Laravel\\Telescope\\*',
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

    /*
    |--------------------------------------------------------------------------
    | Per Page
    |--------------------------------------------------------------------------
    | How many related records are shown per page when drilling into a to-many
    | relation in the record browser, and the maximum number of items returned
    | for a collection-returning accessor.
    */
    'per_page' => env('MODEL_EXPLORER_PER_PAGE', 15),

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    | Model discovery and inspection rely on filesystem scanning and reflection,
    | which can be slow in apps with many models. Enable caching to store those
    | results. Model detail pages auto-refresh when the model file changes; the
    | model list and graph are cached until the TTL expires or you run:
    |
    |     php artisan model-explorer:clear
    |
    | Leave disabled during active model development so changes appear instantly.
    */
    'cache' => [
        'enabled' => env('MODEL_EXPLORER_CACHE', false),

        // Cache store to use. Null uses the application's default store.
        'store' => env('MODEL_EXPLORER_CACHE_STORE'),

        // Time-to-live in seconds. Null caches forever (clear manually).
        'ttl' => env('MODEL_EXPLORER_CACHE_TTL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | MCP Server
    |--------------------------------------------------------------------------
    | A local laravel/mcp server ("model-explorer") exposes model-introspection
    | tools to AI agents. It registers only when both `enabled` and `mcp.enabled`
    | are true. Wire it into your AI client with:
    |
    |   { "mcpServers": { "model-explorer": {
    |       "command": "php", "args": ["artisan", "mcp:start", "model-explorer"] } } }
    |
    | The tools read live by default so an agent never reasons on stale structure
    | during active development. Enable `mcp.cache.enabled` only if you accept
    | staleness for speed on a very large model set.
    */
    'mcp' => [
        'enabled' => env('MODEL_EXPLORER_MCP', true),

        'cache' => [
            'enabled' => env('MODEL_EXPLORER_MCP_CACHE', false),
        ],

        // Allow `inspect-model` / `model-source` to introspect an Eloquent model
        // *outside* the configured `model_paths` when given its fully-qualified
        // class name (e.g. a vendor package's model). Off by default so the agent
        // surface stays bounded to the discovered set; `list-models`/`find-model`
        // are unaffected either way.
        'allow_undiscovered' => env('MODEL_EXPLORER_MCP_ALLOW_UNDISCOVERED', false),
    ],

];
