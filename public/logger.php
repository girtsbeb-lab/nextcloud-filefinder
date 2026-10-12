<?php
/**
 * logger.php: MySQL search and download activity logger
 * © Ģirts Bebrovskis, 2025
 */
require_once '/var/www/conf/config.php';

function getDb(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $pdo = new PDO(DB_DSN, DB_USER, DB_PASSWORD, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}

function logSearch(string $query, int $resultCount, string $language = 'lv'): void {
    try {
        $stmt = getDb()->prepare(
            "INSERT INTO searches (query, results, language, ip)
             VALUES (:query, :results, :language, :ip)"
        );
        $stmt->execute([
            ':query'    => $query,
            ':results'  => $resultCount,
            ':language' => $language,
            ':ip'       => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Exception $e) {
        error_log("logSearch error: " . $e->getMessage());
    }
}

function logDownload(string $filename): void {
    try {
        $stmt = getDb()->prepare(
            "INSERT INTO downloads (filename, ip) VALUES (:filename, :ip)"
        );
        $stmt->execute([
            ':filename' => $filename,
            ':ip'       => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Exception $e) {
        error_log("logDownload error: " . $e->getMessage());
    }
}
