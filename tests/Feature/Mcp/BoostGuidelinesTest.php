<?php

it('ships a Boost guidelines file naming every MCP tool', function () {
    $path = __DIR__.'/../../../resources/boost/guidelines/core.blade.php';

    expect(file_exists($path))->toBeTrue();

    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('model-explorer')
        ->toContain('list-models')
        ->toContain('inspect-model')
        ->toContain('find-model')
        ->toContain('model-source');
});
