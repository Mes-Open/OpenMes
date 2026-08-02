<?php

namespace App\Http\Controllers;

use App\Enums\EngineeringPackageType;
use App\Models\EngineeringDocument;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Serves the files of an extracted interactive-HTML package (#179 Phase 3) as
 * ACTIVE content, isolated from the app. Reached only through a short-lived
 * SIGNED URL (no session — so the app's auth cookie is never handed to the
 * package), with a strict Content-Security-Policy, `nosniff`, and
 * `frame-ancestors` pinned to the app origin so only the MES app may embed it.
 * Meant to be loaded inside a sandboxed <iframe>.
 */
class EngineeringViewerController extends Controller
{
    /** Content types the viewer will label; anything else is served as octet-stream + nosniff. */
    private const TYPES = [
        'html' => 'text/html', 'htm' => 'text/html',
        'js' => 'text/javascript', 'mjs' => 'text/javascript',
        'css' => 'text/css', 'json' => 'application/json', 'map' => 'application/json',
        'wasm' => 'application/wasm', 'xml' => 'application/xml', 'txt' => 'text/plain',
        'svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'gif' => 'image/gif', 'webp' => 'image/webp', 'ico' => 'image/x-icon', 'bmp' => 'image/bmp',
        'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf', 'otf' => 'font/otf',
        'glb' => 'model/gltf-binary', 'gltf' => 'model/gltf+json',
    ];

    public function serve(EngineeringDocument $engineeringDocument, string $path): Response
    {
        abort_unless($engineeringDocument->package_type === EngineeringPackageType::InteractiveHtml, 404);

        // Path-traversal guard: only clean, package-relative paths.
        $clean = str_replace('\\', '/', $path);
        abort_if(str_starts_with($clean, '/') || str_contains($clean, '../'), 404);

        $disk = Storage::disk(config('engineering.disk', 'local'));
        $full = "engineering/interactive/{$engineeringDocument->id}/{$clean}";
        abort_unless($disk->exists($full), 404);

        $ext = strtolower(pathinfo($clean, PATHINFO_EXTENSION));
        $contentType = self::TYPES[$ext] ?? 'application/octet-stream';

        // frame-ancestors is appended at request time so the app origin stays
        // configurable and external network access remains disabled by default.
        $csp = config('engineering.viewer_csp').'; frame-ancestors '.config('app.url');

        return response($disk->get($full), 200, [
            'Content-Type' => $contentType,
            'Content-Security-Policy' => $csp,
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            'Cache-Control' => 'private, max-age=60',
            'X-Frame-Options' => 'SAMEORIGIN',
        ]);
    }
}
