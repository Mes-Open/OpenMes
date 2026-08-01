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
];
