<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PublicMediaController extends Controller
{
    private const BLOCKED_EXTENSIONS = ['php', 'phtml', 'phar', 'sh', 'bat', 'cmd', 'exe'];

    public function __invoke(Request $request, ?string $path = null): BinaryFileResponse
    {
        $rawPath = $path ?? (string) $request->query('file', '');

        $cleanPath = trim(str_replace('\\', '/', $rawPath), '/');
        if (str_starts_with($cleanPath, 'storage/')) {
            $cleanPath = substr($cleanPath, strlen('storage/'));
        }

        if ($cleanPath === '' || str_contains($cleanPath, "\0")) {
            abort(404);
        }

        foreach (explode('/', $cleanPath) as $segment) {
            if ($segment === '' || str_starts_with($segment, '.')) {
                abort(404);
            }
        }

        $extension = strtolower(pathinfo($cleanPath, PATHINFO_EXTENSION));
        if (in_array($extension, self::BLOCKED_EXTENSIONS, true)) {
            abort(404);
        }

        $safeFilename = addslashes(basename($cleanPath));
        $headers = [
            'Cache-Control' => 'public, max-age=86400',
            'Content-Disposition' => 'inline; filename="' . $safeFilename . '"',
        ];

        // 1. Primary: public storage disk (storage/app/public/...)
        $disk = Storage::disk('public');
        if ($disk->exists($cleanPath)) {
            $diskRoot = realpath($disk->path(''));
            $diskFile = realpath($disk->path($cleanPath));

            if (
                $diskRoot !== false
                && $diskFile !== false
                && str_starts_with($diskFile, $diskRoot . DIRECTORY_SEPARATOR)
                && is_file($diskFile)
            ) {
                return response()->file($diskFile, $headers);
            }
        }

        // 2. Fallback: static public/storage/... directory if present
        $publicStorageRoot = realpath(public_path('storage'));
        if ($publicStorageRoot !== false) {
            $publicStorageFile = realpath($publicStorageRoot . DIRECTORY_SEPARATOR . $cleanPath);
            if (
                $publicStorageFile !== false
                && str_starts_with($publicStorageFile, $publicStorageRoot . DIRECTORY_SEPARATOR)
                && is_file($publicStorageFile)
            ) {
                return response()->file($publicStorageFile, $headers);
            }
        }

        // 3. Fallback: bundled platform logos in public/images/...
        if (str_starts_with($cleanPath, 'platforms/')) {
            $publicImagesRoot = realpath(public_path('images'));
            if ($publicImagesRoot !== false) {
                $publicImageFile = realpath($publicImagesRoot . DIRECTORY_SEPARATOR . basename($cleanPath));
                if (
                    $publicImageFile !== false
                    && str_starts_with($publicImageFile, $publicImagesRoot . DIRECTORY_SEPARATOR)
                    && is_file($publicImageFile)
                ) {
                    return response()->file($publicImageFile, $headers);
                }
            }
        }

        abort(404);
    }
}
