<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
if (!file_exists(__DIR__.'/../vendor/autoload.php')) {
    http_response_code(503);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>OpenMES — Setup required</title>
    <style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#f3f4f6}
    .box{background:#fff;border-radius:8px;padding:40px;max-width:520px;box-shadow:0 2px 12px rgba(0,0,0,.1)}
    h1{color:#1e40af;margin:0 0 12px}p{color:#374151;line-height:1.6}
    code{background:#f1f5f9;padding:2px 6px;border-radius:4px;font-size:.9em}
    pre{background:#1e293b;color:#e2e8f0;padding:16px;border-radius:6px;overflow:auto;font-size:.85em}</style>
    </head><body><div class="box">
    <h1>⚙️ OpenMES — Setup required</h1>
    <p>Dependencies are not installed. Run the following command in the <code>backend/</code> directory:</p>
    <pre>composer install --no-dev --optimize-autoloader</pre>
    <p>Then run migrations:</p>
    <pre>php artisan migrate --seed</pre>
    <p>For Docker installation use <code>docker compose up -d</code> from the root directory.</p>
    </div></body></html>';
    exit;
}
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
