# Laravel Model Explorer

[![Latest Version on Packagist](https://img.shields.io/packagist/v/onelearningcommunity/laravel-model-explorer.svg?style=flat-square)](https://packagist.org/packages/onelearningcommunity/laravel-model-explorer)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/one-learning-community/laravel-model-explorer/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/one-learning-community/laravel-model-explorer/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/onelearningcommunity/laravel-model-explorer.svg?style=flat-square)](https://packagist.org/packages/onelearningcommunity/laravel-model-explorer)

A developer tool for Laravel that gives you a browsable UI to explore your Eloquent models — their attributes, casts, relations, scopes, traits, and live data — without reading code.

**Zero setup beyond Composer install.** No `vendor:publish`, no frontend tooling required in your application.

## Requirements

- PHP 8.4+
- Laravel 11, 12, or 13

## Features

- **Model list** — searchable grid of all discovered Eloquent models
- **Model detail** — DB columns, casts, fillable/hidden/guarded, relations with type badges and foreign keys, scopes with source snippets, traits, and accessor snippets
- **Record lookup** — find any record by primary key or unique field; browse raw attributes, lazy-loaded accessor values, and expandable relations with drill-down navigation and breadcrumb trail
- **Relationship graph** — interactive force-directed SVG graph of all model relationships

## Installation

```bash
composer require --dev onelearningcommunity/laravel-model-explorer
```

The package auto-registers via Laravel's package discovery — no additional setup required.

## Usage

Visit `/_model-explorer` in your application. In local environments, access is granted by default.

## Authorization

Access is controlled by the `viewModelExplorer` gate, which defaults to allowing access in `local` environments only. Override it in your `AuthServiceProvider` to control access elsewhere:

```php
Gate::define('viewModelExplorer', function (User $user): bool {
    return $user->isAdmin();
});
```

To disable the explorer entirely regardless of the gate, set the environment variable:

```env
MODEL_EXPLORER_ENABLED=false
```

## Configuration

Publish the config file to customise behaviour:

```bash
php artisan vendor:publish --tag="model-explorer-config"
```

```php
return [
    // Set to false to disable entirely (e.g. force off in production regardless of gate)
    'enabled' => env('MODEL_EXPLORER_ENABLED', true),

    // URL prefix for all Model Explorer routes
    'path' => env('MODEL_EXPLORER_PATH', '_model-explorer'),

    // Middleware applied to all routes ('web' is required)
    'middleware' => ['web'],

    // Directories scanned for Eloquent models (add more for DDD layouts, packages, etc.)
    // Keys are the root namespace, values are absolute directory paths.
    'model_paths' => [
        'App'             => app_path('Models'),
        // 'Domain\\Billing' => base_path('src/Billing/Models'),
    ],

    // Traits whose FQN begins with these prefixes will be hidden in the UI
    'excluded_trait_prefixes' => [
        'Illuminate\Database\Eloquent\Concerns\\',
        'Illuminate\Database\Eloquent\HasCollection',
        'Illuminate\Support\Traits\\',
    ],
];
```

## Security

Laravel Model Explorer is intended for development use. The `viewModelExplorer` gate should prevent access in production environments.

All record reads are wrapped in a rolled-back transaction with `Model::withoutEvents()` to prevent accidental writes from observers or model events. Note that non-database side effects from accessor methods (HTTP calls, cache writes, queue pushes) are **not** prevented.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
