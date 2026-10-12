<?php
/**
 * Shared configuration. Every value comes from environment variables
 * (docker compose loads them from conf/.env). No secrets in this file.
 */
function env(string $key): string {
    $v = getenv($key);
    if ($v === false || $v === '') {
        error_log("Missing env var $key");
        http_response_code(500);
        exit('Server misconfigured');
    }
    return $v;
}

define('DB_DSN', 'mysql:host=' . env('DB_HOST') . ';dbname=' . env('DB_NAME') . ';charset=utf8mb4');
define('DB_USER', env('DB_USER'));
define('DB_PASSWORD', env('DB_PASS'));
