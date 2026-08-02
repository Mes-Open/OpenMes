<?php

namespace App\Http\Controllers;

use App\Enums\EngineeringPackageType;
use App\Models\EngineeringDocument;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Serves the files of an extracted interactive-HTML package (#179 Phase 3) as
 * ACTIVE content, isolated from the app. Reached only through a short-lived
 * SIGNED URL (no session — so the app's auth cookie is never handed to the
 * package), with a strict Content-Security-Policy, `nosniff`, and
 * `frame-ancestors` pinned to the app origin so only the MES app may embed it.
 * Meant to be loaded inside a sandboxed <iframe>.
 *
 * The browser fetches a page's relative assets (css/js/images) WITHOUT the
 * entry's signature, so for HTML we rewrite each static `src`/`href` to its own
 * short-lived signed viewer URL — every request stays individually signed.
 * (Assets pulled in dynamically by JS are not rewritten; package those inline
 * or reference them from markup.)
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

        $clean = $this->safePath($path);

        $disk = Storage::disk(config('engineering.disk', 'local'));
        $full = "engineering/interactive/{$engineeringDocument->id}/{$clean}";
        abort_unless($disk->exists($full), 404);

        $ext = strtolower(pathinfo($clean, PATHINFO_EXTENSION));
        $contentType = self::TYPES[$ext] ?? 'application/octet-stream';

        $body = $disk->get($full);
        if (in_array($ext, ['html', 'htm'], true)) {
            $body = $this->signAssetUrls($body, $engineeringDocument->id, $clean);
        }

        // frame-ancestors is appended at request time so the app origin stays
        // configurable and external network access remains disabled by default.
        $csp = config('engineering.viewer_csp').'; frame-ancestors '.config('app.url');

        return response($body, 200, [
            'Content-Type' => $contentType,
            'Content-Security-Policy' => $csp,
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            'Cache-Control' => 'private, max-age=60',
            'X-Frame-Options' => 'SAMEORIGIN',
        ]);
    }

    /** Reject traversal / absolute / null-byte paths; return the cleaned package-relative path. */
    private function safePath(string $path): string
    {
        $clean = str_replace('\\', '/', $path);
        abort_if(
            $clean === '' || str_starts_with($clean, '/') || str_contains($clean, '../') || str_contains($clean, "\0"),
            404,
        );

        return $clean;
    }

    /**
     * Rewrite static, package-relative src/href references in an HTML page to
     * their own short-lived signed viewer URLs. Absolute/protocol-relative,
     * data:, blob:, mailto:, root-absolute and fragment refs are left untouched.
     */
    private function signAssetUrls(string $html, int $id, string $entryPath): string
    {
        $expires = now()->addSeconds(max((int) config('engineering.viewer_url_ttl', 300), 60));
        $baseDir = str_contains($entryPath, '/') ? substr($entryPath, 0, strrpos($entryPath, '/')) : '';

        $rewritten = preg_replace_callback(
            '/\b(src|href)\s*=\s*(["\'])(?!https?:|\/\/|data:|blob:|mailto:|#)([^"\']+)\2/i',
            function (array $m) use ($id, $expires, $baseDir) {
                $refPath = preg_split('/[?#]/', $m[3])[0];
                if ($refPath === '' || str_starts_with($refPath, '/')) {
                    return $m[0];
                }
                $resolved = $this->resolveRelative($baseDir, $refPath);
                if ($resolved === null) {
                    return $m[0];
                }
                $url = URL::temporarySignedRoute('engineering.viewer', $expires, [
                    'engineeringDocument' => $id,
                    'path' => $resolved,
                ]);

                return $m[1].'='.$m[2].$url.$m[2];
            },
            $html,
        );

        return $rewritten ?? $html;
    }

    /** Join a relative ref onto the entry's directory and normalise; null if it escapes the package. */
    private function resolveRelative(string $baseDir, string $ref): ?string
    {
        $parts = [];
        $combined = ($baseDir !== '' ? $baseDir.'/' : '').$ref;
        foreach (explode('/', $combined) as $seg) {
            if ($seg === '' || $seg === '.') {
                continue;
            }
            if ($seg === '..') {
                if (empty($parts)) {
                    return null;
                }
                array_pop($parts);

                continue;
            }
            $parts[] = $seg;
        }

        return $parts === [] ? null : implode('/', $parts);
    }
}
