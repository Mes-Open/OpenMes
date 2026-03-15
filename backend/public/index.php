<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Check PHP version requirement
if (version_compare(PHP_VERSION, '8.2.0', '<')) {
    http_response_code(503);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>OpenMES — PHP upgrade required</title>
    <style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#f3f4f6}
    .box{background:#fff;border-radius:8px;padding:40px;max-width:520px;box-shadow:0 2px 12px rgba(0,0,0,.1)}
    h1{color:#b91c1c;margin:0 0 12px}p{color:#374151;line-height:1.6}
    code{background:#f1f5f9;padding:2px 6px;border-radius:4px;font-size:.9em}
    .badge{display:inline-block;background:#fee2e2;color:#b91c1c;padding:4px 12px;border-radius:20px;font-weight:bold}</style>
    </head><body><div class="box">
    <h1>PHP upgrade required</h1>
    <p>OpenMES requires <strong>PHP 8.2 or higher</strong>.</p>
    <p>You are running: <span class="badge">PHP '.PHP_VERSION.'</span></p>
    <p>Please upgrade PHP in your XAMPP / server settings, or use the <strong>Docker</strong> installation which includes the correct PHP version automatically.</p>
    </div></body></html>';
    exit;
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

// Auto-create .env from .env.example if missing so Laravel can boot into the installer
$envPath    = __DIR__.'/../.env';
$envExample = __DIR__.'/../.env.example';
if (!file_exists($envPath) && file_exists($envExample)) {
    copy($envExample, $envPath);
}

// Auto-generate APP_KEY if missing so Laravel can boot without "No application encryption key" error
if (file_exists($envPath)) {
    $envContent = file_get_contents($envPath);
    if (!preg_match('/^APP_KEY=base64:.+/m', $envContent)) {
        $key        = 'base64:' . base64_encode(random_bytes(32));
        $envContent = preg_replace('/^APP_KEY=.*$/m', 'APP_KEY=' . $key, $envContent);
        file_put_contents($envPath, $envContent);
    }
}

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
