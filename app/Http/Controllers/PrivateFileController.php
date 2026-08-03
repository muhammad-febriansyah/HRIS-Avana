<?php

namespace App\Http\Controllers;

use App\Support\PrivateFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serve a privately stored file to whoever holds a valid signed link.
 *
 * The `signed` middleware is the gate: the URL is minted by a screen that has
 * already established who may see the file, and cannot be edited to point at
 * another one without invalidating the signature.
 */
class PrivateFileController extends Controller
{
    public function __invoke(Request $request, string $path): StreamedResponse
    {
        // Belt and braces: the signature already covers the path, so this can
        // only fire on a link this application never minted.
        abort_if(str_contains($path, '..'), 404);

        abort_unless(PrivateFile::exists($path), 404);

        return Storage::disk(PrivateFile::DISK)->response($path, null, [
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
