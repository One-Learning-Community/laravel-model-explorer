<?php

namespace OneLearningCommunity\LaravelModelExplorer\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AssetController
{
    /** @var array<string, string> */
    private const MIME_TYPES = [
        'js'    => 'application/javascript',
        'css'   => 'text/css',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf'   => 'font/ttf',
        'svg'   => 'image/svg+xml',
        'png'   => 'image/png',
        'ico'   => 'image/x-icon',
        'map'   => 'application/json',
    ];

    public function __invoke(string $path): BinaryFileResponse
    {
        $publicPath = realpath(__DIR__.'/../../../public');
        $assetPath = realpath($publicPath.'/'.$path);

        if (! $assetPath || ! str_starts_with($assetPath, $publicPath)) {
            abort(404);
        }

        $extension = pathinfo($assetPath, PATHINFO_EXTENSION);
        $mimeType = self::MIME_TYPES[$extension] ?? null;

        if ($mimeType === null) {
            abort(404);
        }

        return response()->file($assetPath, [
            'Content-Type'  => $mimeType,
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
