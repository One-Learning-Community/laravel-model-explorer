<?php

namespace OneLearningCommunity\LaravelModelExplorer\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AssetController
{
    /** @var string[] */
    private const ALLOWED_EXTENSIONS = ['js', 'css', 'woff', 'woff2', 'ttf', 'svg', 'png', 'ico', 'map'];

    public function __invoke(string $path): BinaryFileResponse
    {
        $publicPath = realpath(__DIR__ . '/../../../public');
        $assetPath = realpath($publicPath . '/' . $path);

        if (! $assetPath || ! str_starts_with($assetPath, $publicPath)) {
            abort(404);
        }

        $extension = pathinfo($assetPath, PATHINFO_EXTENSION);

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, strict: true)) {
            abort(404);
        }

        return response()->file($assetPath, [
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
