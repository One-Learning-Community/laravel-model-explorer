# Getting Started

Model Explorer for Laravel is a zero-config developer tool that gives AI coding agents and developers a live, structural view of your Eloquent models — through an MCP server for agents and a browser-based UI for humans. No `vendor:publish`, no frontend tooling required in your app.

## Requirements

- PHP 8.3+
- Laravel 11, 12, or 13

## Installation

Install the package via Composer:

```bash
composer require --dev onelearningcommunity/laravel-model-explorer
```

The package auto-discovers and registers itself. No service provider needs to be added manually.

## Accessing the UI

Once installed, visit `/_model-explorer` in your browser:

```
https://your-app.test/_model-explorer
```

## AI Model Introspection (MCP)

Model Explorer also ships a local [`laravel/mcp`](https://laravel.com/docs/mcp) server, `model-explorer`, that lets AI coding agents query your models' columns, relations, scopes, accessors, and trait-correct source directly — without scanning your source files. Register it with your AI client:

```json
{
  "mcpServers": {
    "model-explorer": {
      "command": "php",
      "args": ["artisan", "mcp:start", "model-explorer"]
    }
  }
}
```

This is independent of the browser UI's `viewModelExplorer` gate below — the server runs as a local stdio process in your own shell, the same trust boundary as `tinker`.

See the [AI Model Introspection guide](/guide/mcp) for the full tool reference (`list-models`, `inspect-model`, `find-model`, `model-source`, `model-neighbors`) and configuration options.

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

- [AI Model Introspection (MCP)](/guide/mcp) — full tool reference and configuration
- [Configuration](/guide/configuration) — change the URL path, add middleware, or restrict model paths
- [Model List](/guide/model-list) — what you see when you first open the UI
