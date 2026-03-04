<?php
/**
 * admin.php — Simple password-protected dashboard
 * Shows recent searches, downloads, and usage stats.
 *
 * Protect with ADMIN_PASSWORD in your .env file.
 * © Ģirts Bebrovskis, 2025
 */
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/logger.php';

$adminPassword = $_ENV['ADMIN_PASSWORD'] ?? null;

if (!$adminPassword) {
    die('<p style="font-family:sans-serif;padding:2rem">ADMIN_PASSWORD is not set in .env</p>');
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
        .login-box { max-width: 360px; margin: 100px auto; padding: 2rem; background: #fff; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.08); }
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

$stats   = getStats();
$searches  = getRecentSearches(100);
$downloads = getRecentDownloads(100);
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
        .badge { display:inline-block; padding: 2px 8px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-found { background: #e6f9ee; color: #27ae60; }
        .badge-none  { background: #fdecea; color: #c0392b; }
        .badge-lang  { background: #eef4ff; color: #3b82f6; }
        .top-queries li { padding: 6px 0; border-bottom: 1px solid #f0f0f0; display:flex; justify-content: space-between; }
        .top-queries li:last-child { border: none; }
        .logout { float: right; font-size: 14px; color: #999; }
        .logout a { color: #ff6a00; }
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
        <ul class="top-queries" style="list-style:none;padding:0;margin:0">
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
                            <?= $row['results'] ?>
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
