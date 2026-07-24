<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class KioskMediaController extends Controller
{
    /**
     * Serve public kiosk images with browser and intermediary cache support.
     */
    public function __invoke(Request $request, string $path): BinaryFileResponse
    {
        $path = trim($path, '/');
        $segments = explode('/', $path);

        abort_if(
            $path === ''
            || str_contains($path, "\0")
            || str_contains($path, '\\')
            || in_array('.', $segments, true)
            || in_array('..', $segments, true),
            404
        );

        $disk = Storage::disk('public');
        abort_unless($disk->exists($path), 404);

        $storageRoot = realpath($disk->path(''));
        $file = realpath($disk->path($path));
        $allowedExtensions = ['avif', 'gif', 'jpeg', 'jpg', 'png', 'webp'];

        abort_unless(
            $storageRoot
            && $file
            && is_file($file)
            && str_starts_with($file, $storageRoot.DIRECTORY_SEPARATOR)
            && in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), $allowedExtensions, true),
            404
        );

        $response = response()->file($file);
        $response->setPublic();
        $response->setMaxAge(31536000);
        $response->setSharedMaxAge(31536000);
        $response->setImmutable();
        $response->setAutoEtag();
        $response->setAutoLastModified();
        $response->isNotModified($request);

        return $response;
    }
}
