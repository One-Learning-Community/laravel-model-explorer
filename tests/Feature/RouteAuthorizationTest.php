<?php

use Illuminate\Support\Facades\Gate;

it('allows access in a local environment', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->get('/_model-explorer')
        ->assertOk();
});

it('denies access in a non-local environment without a gate override', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->get('/_model-explorer')
        ->assertForbidden();
});

it('allows access in a non-local environment when the gate is overridden to permit', function () {
    app()->detectEnvironment(fn () => 'production');

    // $user = null is required — Laravel's Gate short-circuits to false for
    // unauthenticated requests when the callback accepts no user parameter.
    Gate::define('viewModelExplorer', fn ($user = null) => true);

    $this->get('/_model-explorer')
        ->assertOk();
});

it('returns 404 when the package is disabled', function () {
    app()->detectEnvironment(fn () => 'local');
    config()->set('model-explorer.enabled', false);

    $this->get('/_model-explorer')
        ->assertNotFound();
});

it('serves assets without requiring authorization', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->get('/_model-explorer/assets/app.js')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/javascript');
});

it('returns 404 for asset path traversal attempts', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->get('/_model-explorer/assets/../composer.json')
        ->assertNotFound();
});

it('returns 404 for disallowed asset extensions', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->get('/_model-explorer/assets/composer.json')
        ->assertNotFound();
});

it('uses the default path prefix', function () {
    app()->detectEnvironment(fn () => 'local');

    // The default prefix is `_model-explorer` as defined in config/model-explorer.php
    $this->get('/_model-explorer')->assertOk();
    $this->get('/_model-explorer/some/nested/page')->assertOk();
});
