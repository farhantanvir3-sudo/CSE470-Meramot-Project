<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;

/**
 * Serves static frontend assets (JS, CSS, images) directly from the root
 * `../frontend/` directory so `frontend/` and `backend/` remain cleanly
 * separated without duplicating client-side files inside `backend/public/`.
 */
class FrontendAssetController extends Controller
{
    public function js(string $filename)
    {
        $safeName = basename($filename);
        $path = base_path("../frontend/js/{$safeName}");

        if (!File::exists($path)) {
            abort(404);
        }

        return response(File::get($path), 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function style(string $filename)
    {
        $safeName = basename($filename);
        $path = base_path("../frontend/src/styles/{$safeName}");

        if (!File::exists($path)) {
            abort(404);
        }

        return response(File::get($path), 200, [
            'Content-Type' => 'text/css; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
