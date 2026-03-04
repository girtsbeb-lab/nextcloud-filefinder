<?php
/**
 * config.php — Centralized configuration loader
 * Reads settings from a .env file in the same directory.
 * Copy .env.example to .env and fill in your real values.
 *
 * © Ģirts Bebrovskis, 2025
 */

function loadEnv(string $path): void {
    if (!file_exists($path)) {
        error_log("config.php: .env file not found at $path");
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (str_starts_with(trim($line), '#')) continue;

        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if (!empty($key)) {
            $_ENV[$key] = $value;
        }
    }
}

loadEnv(__DIR__ . '/.env');

// Nextcloud WebDAV
define('NEXTCLOUD_URL',      $_ENV['NEXTCLOUD_URL']      ?? '');
define('NEXTCLOUD_USERNAME', $_ENV['NEXTCLOUD_USERNAME'] ?? '');
define('NEXTCLOUD_PASSWORD', $_ENV['NEXTCLOUD_APP_PASSWORD'] ?? '');

// hCaptcha
define('HCAPTCHA_SECRET',  $_ENV['HCAPTCHA_SECRET']  ?? '');
define('HCAPTCHA_SITEKEY', $_ENV['HCAPTCHA_SITEKEY'] ?? '');

// App settings
define('CAPTCHA_VALID_DURATION', (int)($_ENV['CAPTCHA_VALID_DURATION'] ?? 1800));
define('SEARCH_MIN_LENGTH',      (int)($_ENV['SEARCH_MIN_LENGTH']      ?? 7));
define('RESULTS_PER_PAGE',       (int)($_ENV['RESULTS_PER_PAGE']       ?? 30));
define('MAX_TRAVERSE_DEPTH',     (int)($_ENV['MAX_TRAVERSE_DEPTH']     ?? 10));
