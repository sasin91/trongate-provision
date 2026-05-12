<?php
// ── Edit this file to configure your deployment ──────────────────
// Inspired by Laravel Envoy: https://laravel.com/docs/envoy

// Server connection
$host = '1.2.3.4';
$user = 'root';
$port = 22;

// Application source
$repo   = 'https://github.com/you/app.git';
$branch = 'main';

// Server paths
$webroot       = '/var/www/html';
$releases_path = '/var/www/releases';

// Domain (leave empty to skip vhost setup)
$domain = 'example.com';

// PHP version
$php_version = '8.4';

// Database
$db_name = 'myapp';
$db_user = 'myapp';
$db_pass = 'secret';

// Extra environment variables exported into the release (optional)
$env_vars = [
    // 'APP_KEY' => 'base64:...',
];
