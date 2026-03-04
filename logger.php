<?php
/**
 * logger.php — SQLite search and download activity logger
 * Stores search queries, result counts, downloads, and IP addresses.
 *
 * © Ģirts Bebrovskis, 2025
 */

function getDb(): PDO {
    $dbPath = $_ENV['DB_PATH'] ?? __DIR__ . '/data/searches.db';

    // Ensure directory exists
    $dir = dirname($dbPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create tables if they don't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS searches (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            query       TEXT NOT NULL,
            results     INTEGER NOT NULL DEFAULT 0,
            language    TEXT NOT NULL DEFAULT 'lv',
            ip          TEXT,
            searched_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS downloads (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            filename     TEXT NOT NULL,
            ip           TEXT,
            downloaded_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");

    return $pdo;
}

function logSearch(string $query, int $resultCount, string $language = 'lv'): void {
    try {
        $db = getDb();
        $stmt = $db->prepare("
            INSERT INTO searches (query, results, language, ip)
            VALUES (:query, :results, :language, :ip)
        ");
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
        $db = getDb();
        $stmt = $db->prepare("
            INSERT INTO downloads (filename, ip)
            VALUES (:filename, :ip)
        ");
        $stmt->execute([
            ':filename' => $filename,
            ':ip'       => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Exception $e) {
        error_log("logDownload error: " . $e->getMessage());
    }
}

/**
 * Returns recent activity for the admin dashboard.
 */
function getRecentSearches(int $limit = 50): array {
    try {
        $db = getDb();
        return $db->query("
            SELECT query, results, language, ip, searched_at
            FROM searches
            ORDER BY searched_at DESC
            LIMIT $limit
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("getRecentSearches error: " . $e->getMessage());
        return [];
    }
}

function getRecentDownloads(int $limit = 50): array {
    try {
        $db = getDb();
        return $db->query("
            SELECT filename, ip, downloaded_at
            FROM downloads
            ORDER BY downloaded_at DESC
            LIMIT $limit
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("getRecentDownloads error: " . $e->getMessage());
        return [];
    }
}

function getStats(): array {
    try {
        $db = getDb();
        $totalSearches = $db->query("SELECT COUNT(*) FROM searches")->fetchColumn();
        $totalDownloads = $db->query("SELECT COUNT(*) FROM downloads")->fetchColumn();
        $todaySearches = $db->query("SELECT COUNT(*) FROM searches WHERE DATE(searched_at) = DATE('now')")->fetchColumn();
        $topQueries = $db->query("
            SELECT query, COUNT(*) as count
            FROM searches
            GROUP BY LOWER(query)
            ORDER BY count DESC
            LIMIT 5
        ")->fetchAll(PDO::FETCH_ASSOC);

        return compact('totalSearches', 'totalDownloads', 'todaySearches', 'topQueries');
    } catch (Exception $e) {
        error_log("getStats error: " . $e->getMessage());
        return [];
    }
}
