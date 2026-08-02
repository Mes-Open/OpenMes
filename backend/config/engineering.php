<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Engineering documents (#179)
    |--------------------------------------------------------------------------
    | Limits and allowlists for CAD / engineering-document uploads. The
    | extraction limits and the interactive-HTML viewer land in a later phase;
    | Phase 1 stores every package type as a downloadable blob.
    */

    'disk' => env('ENGINEERING_DISK', 'local'), // private disk (storage/app/private)

    'max_upload_bytes' => (int) env('ENGINEERING_MAX_UPLOAD_BYTES', 100 * 1024 * 1024),      // 100 MB
    'max_extracted_bytes' => (int) env('ENGINEERING_MAX_EXTRACTED_BYTES', 500 * 1024 * 1024), // 500 MB (Phase 3)
    // Per-file cap on a single extracted entry. Enforced while streaming the
    // entry out (never trusting the archive's declared size), so a zip-bomb
    // entry is aborted after this many bytes instead of inflating into memory.
    'max_extracted_file_bytes' => (int) env('ENGINEERING_MAX_EXTRACTED_FILE_BYTES', 64 * 1024 * 1024), // 64 MB
    'max_files' => (int) env('ENGINEERING_MAX_FILES', 2000),                                  // (Phase 3)

    /*
     | Extension allowlist -> package type. The upload is rejected unless its
     | extension is listed here. Keys are lower-case, no dot.
     */
    'extensions' => [
        'step' => 'neutral_cad',
        'stp' => 'neutral_cad',
        'iges' => 'neutral_cad',
        'igs' => 'neutral_cad',
        'eprt' => 'edrawings_native',
        'easm' => 'edrawings_native',
        'edrw' => 'edrawings_native',
        'pdf' => 'pdf',
        'jpg' => 'image',
        'jpeg' => 'image',
        'png' => 'image',
        'gif' => 'image',
        'webp' => 'image',
        'zip' => 'interactive_html',
        'html' => 'interactive_html',
    ],

    /*
     | Interactive-HTML viewer (Phase 3). A zip package is validated (zip-slip,
     | counts, size, inner-extension allowlist) and extracted to an isolated
     | per-document directory, then served through a signed, CSP-locked route.
     */
    'inner_extensions' => [
        'html', 'htm', 'js', 'mjs', 'css', 'json', 'map', 'wasm',
        'svg', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'ico', 'bmp',
        'woff', 'woff2', 'ttf', 'otf', 'eot',
        'bin', 'glb', 'gltf', 'obj', 'stl', 'txt', 'xml',
    ],

    // Short-lived signed viewer URLs (seconds).
    'viewer_url_ttl' => (int) env('ENGINEERING_VIEWER_TTL', 300),

    /*
     | Content-Security-Policy served with every viewer response. `{origin}` is
     | substituted at request time with the app's scheme://host[:port] (from
     | APP_URL). We name the explicit origin rather than 'self' on purpose: the
     | package is framed in a sandbox WITHOUT allow-same-origin, so its document
     | origin is opaque and 'self' would match nothing — an explicit host still
     | authorises the package's own (signed) subresources. `connect-src 'none'`
     | means package JS cannot call the app's API at all (there is no supported
     | dynamic-fetch path), so even if the frame ever shared the app origin it
     | could not act against `/api/*`. `default-src 'none'` blocks external
     | loads/exfiltration; frame-ancestors is appended with the app origin so
     | only the MES app may embed it.
     */
    'viewer_csp' => "default-src 'none'; script-src {origin}; style-src {origin} 'unsafe-inline'; img-src {origin} data: blob:; font-src {origin} data:; connect-src 'none'; worker-src {origin} blob:; media-src {origin} blob:; object-src 'none'; base-uri 'none'; form-action 'none'",
];
