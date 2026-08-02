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
     | Content-Security-Policy served with every viewer response. default-src
     | 'none' + connect-src 'self' keep the package fully offline (no external
     | CDNs / exfiltration); frame-ancestors is filled with the app origin so
     | only the MES app may embed it.
     */
    'viewer_csp' => "default-src 'none'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self' data:; connect-src 'self'; worker-src 'self' blob:; media-src 'self' blob:; object-src 'none'; base-uri 'none'; form-action 'none'",
];
