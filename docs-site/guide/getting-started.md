# Getting Started

Laravel Model Explorer is a zero-config developer tool that gives you a browser-based UI for exploring your Eloquent models. No `vendor:publish`, no frontend tooling required in your app.

## Requirements

- PHP 8.3+
- Laravel 11, 12, or 13

## Installation

Install the package via Composer:

```bash
composer require onelearningcommunity/laravel-model-explorer
```

The package auto-discovers and registers itself. No service provider needs to be added manually.

## Accessing the UI

Once installed, visit `/_model-explorer` in your browser:

```
https://your-app.test/_model-explorer
```

## Authorization

By default, Model Explorer is only accessible in **local environments** (`app.env === 'local'`). This is enforced by a `viewModelExplorer` gate that is registered automatically.

To grant access in other environments, define the gate in your `AppServiceProvider`:

```php
use Illuminate\Support\Facades\Gate;

Gate::define('viewModelExplorer', function ($user) {
    return in_array($user->email, [
        'you@example.com',
    ]);
});
```

::: warning
Never open Model Explorer to unauthenticated users in a production environment. Always restrict access via the gate or the `enabled` config option.
:::

## Next Steps

- [Configuration](/guide/configuration) — change the URL path, add middleware, or restrict model paths
- [Model List](/guide/model-list) — what you see when you first open the UI
