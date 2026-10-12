<?php
/**
 * admin.php — Password-protected admin dashboard
 * © Ģirts Bebrovskis, 2025
 */
session_start();

// -------------------------------------------------------
// Admin password
$adminPassword = "...";

// MySQL connection
$db_config = [
    'host'    => 'localhost',
    'name'    => '...',
    'user'    => '...',
    'pass'    => '...',
    'charset' => 'utf8mb4',
];
// -------------------------------------------------------

function getDb(array $cfg): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dsn = "mysql:host={$cfg['host']};dbname={$cfg['name']};charset={$cfg['charset']}";
    $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}

function getStats(PDO $db): array {
    try {
        $totalSearches  = $db->query("SELECT COUNT(*) FROM searches")->fetchColumn();
        $totalDownloads = $db->query("SELECT COUNT(*) FROM downloads")->fetchColumn();
        $todaySearches  = $db->query("SELECT COUNT(*) FROM searches WHERE DATE(searched_at) = CURDATE()")->fetchColumn();
        $topQueries     = $db->query("
            SELECT query, COUNT(*) AS count
            FROM searches
            GROUP BY LOWER(query)
            ORDER BY count DESC
            LIMIT 5
        ")->fetchAll();
        return compact('totalSearches', 'totalDownloads', 'todaySearches', 'topQueries');
    } catch (Exception $e) {
        error_log("getStats error: " . $e->getMessage());
        return [];
    }
}

function getRecentSearches(PDO $db, int $limit = 100): array {
    try {
        $stmt = $db->prepare("
            SELECT query, results, language, ip, searched_at
            FROM searches
            ORDER BY searched_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("getRecentSearches error: " . $e->getMessage());
        return [];
    }
}

function getRecentDownloads(PDO $db, int $limit = 100): array {
    try {
        $stmt = $db->prepare("
            SELECT filename, ip, downloaded_at
            FROM downloads
            ORDER BY downloaded_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("getRecentDownloads error: " . $e->getMessage());
        return [];
    }
}

// Handle login / logout
if (isset($_POST['password'])) {
    if ($_POST['password'] === $adminPassword) {
        $_SESSION['admin'] = true;
    } else {
        $loginError = true;
    }
}
if (isset($_GET['logout'])) {
    unset($_SESSION['admin']);
    header('Location: admin.php');
    exit;
}

$isLoggedIn = $_SESSION['admin'] ?? false;

if (!$isLoggedIn): ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Login</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .login-box {
            max-width: 360px;
            margin: 100px auto;
            padding: 2rem;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
        }
        .error { color: #c0392b; font-size: 14px; margin-top: 8px; }
    </style>
</head>
<body>
<div class="login-box">
    <h2 style="text-align:center;color:#ff6a00">Admin Login</h2>
    <form method="POST">
        <input type="password" name="password" placeholder="Password" required autofocus>
        <button type="submit">Login</button>
        <?php if (!empty($loginError)): ?>
            <p class="error">Incorrect password.</p>
        <?php endif; ?>
    </form>
</div>
</body>
</html>
<?php exit; endif;

$db        = getDb($db_config);
$stats     = getStats($db);
$searches  = getRecentSearches($db, 100);
$downloads = getRecentDownloads($db, 100);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body { background: #f4f4f4; }
        .dashboard { max-width: 960px; margin: 40px auto; padding: 0 20px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 32px; }
        .stat-card { background: #fff; border-radius: 12px; padding: 20px; text-align: center; box-shadow: 0 4px 12px rgba(0,0,0,0.06); }
        .stat-card .number { font-size: 36px; font-weight: 700; color: #ff6a00; }
        .stat-card .label { font-size: 13px; color: #888; margin-top: 4px; }
        .section { background: #fff; border-radius: 12px; padding: 24px; margin-bottom: 24px; box-shadow: 0 4px 12px rgba(0,0,0,0.06); }
        h2 { color: #333; font-size: 18px; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th { text-align: left; padding: 10px 12px; background: #fff8f4; color: #ff6a00; border-bottom: 2px solid #ffe0cc; }
        td { padding: 10px 12px; border-bottom: 1px solid #f0f0f0; color: #444; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #fffaf7; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-found { background: #e6f9ee; color: #27ae60; }
        .badge-none  { background: #fdecea; color: #c0392b; }
        .badge-lang  { background: #eef4ff; color: #3b82f6; }
        .top-queries { list-style: none; padding: 0; margin: 0; }
        .top-queries li { padding: 8px 0; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; }
        .top-queries li:last-child { border: none; }
        .logout { font-size: 14px; color: #999; }
        .logout a { color: #ff6a00; text-decoration: none; }
    </style>
</head>
<body>
<div class="dashboard">

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px">
        <h1 style="color:#ff6a00;margin:0">📊 Dashboard</h1>
        <span class="logout"><a href="?logout=1">Logout</a></span>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="number"><?= number_format($stats['totalSearches'] ?? 0) ?></div>
            <div class="label">Total Searches</div>
        </div>
        <div class="stat-card">
            <div class="number"><?= number_format($stats['totalDownloads'] ?? 0) ?></div>
            <div class="label">Total Downloads</div>
        </div>
        <div class="stat-card">
            <div class="number"><?= number_format($stats['todaySearches'] ?? 0) ?></div>
            <div class="label">Searches Today</div>
        </div>
    </div>

    <!-- Top Queries -->
    <?php if (!empty($stats['topQueries'])): ?>
    <div class="section">
        <h2>🔥 Top Search Queries</h2>
        <ul class="top-queries">
            <?php foreach ($stats['topQueries'] as $q): ?>
            <li>
                <span><?= htmlspecialchars($q['query']) ?></span>
                <strong style="color:#ff6a00"><?= $q['count'] ?>×</strong>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Recent Searches -->
    <div class="section">
        <h2>🔍 Recent Searches</h2>
        <table>
            <thead>
                <tr><th>Query</th><th>Results</th><th>Lang</th><th>IP</th><th>Time</th></tr>
            </thead>
            <tbody>
            <?php foreach ($searches as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['query']) ?></td>
                    <td>
                        <span class="badge <?= $row['results'] > 0 ? 'badge-found' : 'badge-none' ?>">
                            <?= (int)$row['results'] ?>
                        </span>
                    </td>
                    <td><span class="badge badge-lang"><?= htmlspecialchars($row['language']) ?></span></td>
                    <td><?= htmlspecialchars($row['ip'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($row['searched_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Recent Downloads -->
    <div class="section">
        <h2>📥 Recent Downloads</h2>
        <table>
            <thead>
                <tr><th>Filename</th><th>IP</th><th>Time</th></tr>
            </thead>
            <tbody>
            <?php foreach ($downloads as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['filename']) ?></td>
                    <td><?= htmlspecialchars($row['ip'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($row['downloaded_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>
</body>
</html>
