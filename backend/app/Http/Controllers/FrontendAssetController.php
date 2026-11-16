<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

/**
 * Serves static frontend assets (JS, CSS) directly from the root
 * `../frontend/` directory with ETag & immutable caching support.
 */
class FrontendAssetController extends Controller
{
    public function js(Request $request, string $filename)
    {
        return $this->serveAsset(
            $request,
            base_path('../frontend/js/' . basename($filename)),
            basename($filename),
            'application/javascript; charset=UTF-8'
        );
    }

    public function style(Request $request, string $filename)
    {
        return $this->serveAsset(
            $request,
            base_path('../frontend/src/styles/' . basename($filename)),
            basename($filename),
            'text/css; charset=UTF-8'
        );
    }

    private function serveAsset(Request $request, string $path, string $safeName, string $contentType)
    {
        if (!File::exists($path)) {
            abort(404);
        }

        $mtime = File::lastModified($path);
        $etag = '"' . md5($safeName . '-' . $mtime) . '"';
        $cacheControl = $request->filled('v')
            ? 'public, max-age=31536000, immutable'
            : 'public, max-age=300';

        $headers = [
            'Content-Type' => $contentType,
            'ETag' => $etag,
            'Cache-Control' => $cacheControl,
        ];

        if ($request->header('If-None-Match') === $etag) {
            return response('', 304, $headers);
        }

        return response(File::get($path), 200, $headers);
    }
}
