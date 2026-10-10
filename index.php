<?php
// Root entry point so the app runs at http://127.0.0.1/laravel/seller-automation/
// (no virtual host, no /public in the URL). Mirrors public/index.php with root-relative paths.
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = __DIR__.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/vendor/autoload.php';

(require_once __DIR__.'/bootstrap/app.php')
    ->handleRequest(Request::capture());
