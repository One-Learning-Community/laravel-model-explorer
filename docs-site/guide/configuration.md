# Configuration

Publish the config file if you need to customise any options:

```bash
php artisan vendor:publish --tag="model-explorer-config"
```

This creates `config/model-explorer.php` in your application.

## Options

### `enabled`

```php
'enabled' => env('MODEL_EXPLORER_ENABLED', true),
```

A kill switch. When `false`, all Model Explorer routes return 404 regardless of gate configuration. Useful for disabling the tool in specific environments via `.env`:

```env
MODEL_EXPLORER_ENABLED=false
```

### `path`

```php
'path' => env('MODEL_EXPLORER_PATH', '_model-explorer'),
```

The URL prefix under which Model Explorer is mounted. Change this if `_model-explorer` conflicts with an existing route in your application.

```env
MODEL_EXPLORER_PATH=dev/models
```

### `middleware`

```php
'middleware' => ['web'],
```

Middleware applied to all Model Explorer routes. The `Authorize` middleware (gate check) is always appended automatically. Add `auth` here if you want to require login before the gate is evaluated:

```php
'middleware' => ['web', 'auth'],
```

### `model_paths`

```php
'model_paths' => [
    'App' => app_path(),
],
```

Directories scanned for Eloquent models. The key is the root namespace for that path. Add additional entries if your models live outside `app/Models`:

```php
'model_paths' => [
    'App'     => app_path(),
    'Modules' => base_path('modules'),
],
```

### `excluded_trait_prefixes`

```php
'excluded_trait_prefixes' => [
    'Illuminate\\Database\\Eloquent\\Concerns\\',
    'Illuminate\\Database\\Eloquent\\HasCollection',
    'Illuminate\\Support\\Traits\\',
],
```

Trait class names beginning with any of these prefixes are hidden from the Model Detail view. The defaults suppress Laravel's internal model concern traits (e.g. `HasAttributes`, `HasRelationships`) while keeping useful traits like `SoftDeletes` and your own custom traits visible.

Add any package trait namespaces you want to suppress:

```php
'excluded_trait_prefixes' => [
    'Illuminate\\Database\\Eloquent\\Concerns\\',
    'Illuminate\\Database\\Eloquent\\HasCollection',
    'Illuminate\\Support\\Traits\\',
    'Spatie\\Activitylog\\Traits\\',
],
```
