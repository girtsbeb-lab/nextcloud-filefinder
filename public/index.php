<?php
/**
 * Certificate search and download system with hCaptcha protection
 * ==========================================================================
 * - Searches the public Nextcloud folder through the WebDAV API.
 * - Lets visitors download the files that were found.
 * - hCaptcha is required once per session period ($captcha_valid_duration).
 * - Supports Latvian / English.
 *
 * Secrets come from environment variables (see conf/.env.example):
 *   NC_USER, NC_APP_PASSWORD, HCAPTCHA_SECRET, DB_*
 *
 * © Ģirts Bebrovskis, 2025
 */
session_set_cookie_params([
    'httponly' => true,
    'secure'   => true,
    'samesite' => 'Lax',
]);
session_start();

require_once __DIR__ . '/logger.php';   // loads config.php, defines env()

if (isset($_GET['logout'])) {
    unset($_SESSION['hcaptcha_valid_until']);
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit;
}

$captcha_valid_duration = 1800; // 30 minutes
$captchaStillValid = isset($_SESSION['hcaptcha_valid_until'])
    && $_SESSION['hcaptcha_valid_until'] >= time();

// Nextcloud WebDAV configuration
$nextcloudHost = 'https://cloud.neo.lv';
$nextcloudUrl  = $nextcloudHost . '/remote.php/dav/files/public_search';
$username      = env('NC_USER');
$appPassword   = env('NC_APP_PASSWORD');
$hcaptchaSecret = env('HCAPTCHA_SECRET');

// Language
if (isset($_GET['lang']) && in_array($_GET['lang'], ['lv', 'en'], true)) {
    $_SESSION['lang'] = $_GET['lang'];
}
$language = $_SESSION['lang'] ?? 'lv';
$lang = include __DIR__ . "/lang_{$language}.php";

/** Strips namespace prefixes from the XML. */
function removeNamespacesUsingRegex($xmlString) {
    $xmlString = preg_replace('/<\?xml.*?\?>/', '', $xmlString);
    $xmlString = preg_replace('/(<\/?)(\w+):/', '$1', $xmlString);
    return trim($xmlString);
}

function downloadFile($fileUrl, $fileName, $username, $appPassword) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fileUrl);
    curl_setopt($ch, CURLOPT_USERPWD, $username . ":" . $appPassword);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

    $data = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || $data === false || $data === '') {
        http_response_code(404);
        echo 'File not found.';
        exit;
    }

    logDownload($fileName);

    $ascii = preg_replace('/[^A-Za-z0-9._-]/', '_', $fileName);
    header("Content-Type: application/octet-stream");
    header("Content-Disposition: attachment; filename=\"$ascii\"; filename*=UTF-8''" . rawurlencode($fileName));
    header("Content-Transfer-Encoding: binary");
    header("Content-Length: " . strlen($data));
    header("X-Content-Type-Options: nosniff");
    echo $data;
    exit;
}

function traverseDirectory($baseUrl, $username, $appPassword, $currentDir, &$visited, &$foundMatches, $depth, $searchString, $host) {
    if ($depth > 10) return;

    $url = $currentDir ?: $baseUrl;
    if (in_array($url, $visited, true)) return;
    $visited[] = $url;

    $currentPath = rtrim(parse_url($url, PHP_URL_PATH), '/');

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERPWD, $username . ":" . $appPassword);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PROPFIND");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Depth: 1"]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 207) return;

    $fixedXml = removeNamespacesUsingRegex($response);
    libxml_use_internal_errors(true);
    $sxml = simplexml_load_string($fixedXml);
    if (!$sxml) {
        libxml_clear_errors();
        return;
    }

    foreach ($sxml->response as $responseItem) {
        $href = (string)$responseItem->href;
        $normHref = rtrim($href, '/');
        if ($normHref === $currentPath) continue;

        $fileName = rawurldecode(basename($normHref));
        $isDirectory = stripos($responseItem->propstat->prop->resourcetype->asXML(), '<collection') !== false;
        $fullPath = $host . $href;

        if (!$isDirectory) {
            if ($searchString !== '' && stripos($fileName, $searchString) !== false) {
                $foundMatches[] = ['url' => $fullPath, 'name' => $fileName];
            }
        } else {
            traverseDirectory($baseUrl, $username, $appPassword, $fullPath, $visited, $foundMatches, $depth + 1, $searchString, $host);
        }
    }
}

function verifyHCaptcha($secret, $responseToken, $remoteIp = null) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => 'https://hcaptcha.com/siteverify',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'secret'   => $secret,
            'response' => $responseToken,
            'remoteip' => $remoteIp,
        ]),
        CURLOPT_TIMEOUT        => 30,
    ]);
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) return false;

    $resultData = json_decode($result, true);
    return $resultData['success'] ?? false;
}

function pageStart(string $title, string $language): void {
    echo '<!DOCTYPE html><html lang="' . htmlspecialchars($language) . '"><head><meta charset="UTF-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>' . htmlspecialchars($title) . '</title>'
       . '<link rel="stylesheet" href="styles.css"></head><body>';
}

function pageEnd(): void {
    echo '</body></html>';
}

function showMessage(string $html, array $lang, string $language): void {
    pageStart($lang['search_title'], $language);
    echo "<div class='container'><p>" . $html . "</p>";
    echo "<p><a href=\"" . htmlspecialchars($_SERVER['PHP_SELF']) . "\">" . htmlspecialchars($lang['back_to_search']) . "</a></p></div>";
    pageEnd();
}

function renderResults(array $matches, int $page, array $lang, string $language): void {
    $perPage    = 30;
    $total      = count($matches);
    $totalPages = max(1, (int)ceil($total / $perPage));
    $page       = min(max(1, $page), $totalPages);
    $start      = ($page - 1) * $perPage;
    $end        = min($start + $perPage, $total);

    pageStart($lang['search_title'], $language);
    echo "<div class='container'>";
    echo "<p>" . sprintf($lang['search_results'], $page, $totalPages) . "</p><ul>";
    for ($i = $start; $i < $end; $i++) {
        $f = $matches[$i];
        echo "<li><a href=\"?download=" . urlencode($f['url']) . "\">" . htmlspecialchars($f['name']) . "</a></li>";
    }
    echo "</ul></div><div class='pagination'>";
    if ($page > 1)           echo "<a href=\"?page=" . ($page - 1) . "\">" . $lang['previous_page'] . "</a> ";
    if ($page < $totalPages) echo "<a href=\"?page=" . ($page + 1) . "\">" . $lang['next_page'] . "</a>";
    echo "</div>";
    echo "<p><a href=\"" . htmlspecialchars($_SERVER['PHP_SELF']) . "\">" . htmlspecialchars($lang['back_to_search']) . "</a></p>";
    pageEnd();
}

// ---------------------------------------------------------------------
// Search (POST)
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$captchaStillValid) {
        $hcaptchaResponse = $_POST['h-captcha-response'] ?? "";
        if ($hcaptchaResponse === "" || !verifyHCaptcha($hcaptchaSecret, $hcaptchaResponse, $_SERVER['REMOTE_ADDR'] ?? null)) {
            showMessage(htmlspecialchars($lang['captcha_failed']), $lang, $language);
            exit;
        }
        $_SESSION['hcaptcha_valid_until'] = time() + $captcha_valid_duration;
    }

    $searchString = trim((string)($_POST['search_string'] ?? ""));
    if (mb_strlen($searchString) < 7 || mb_strlen($searchString) > 100) {
        showMessage(htmlspecialchars($lang['min_characters']), $lang, $language);
        exit;
    }

    $visited = [];
    $foundMatches = [];
    traverseDirectory($nextcloudUrl, $username, $appPassword, "", $visited, $foundMatches, 0, $searchString, $nextcloudHost);

    logSearch($searchString, count($foundMatches), $language);

    if (empty($foundMatches)) {
        unset($_SESSION['matches']);
        showMessage(sprintf($lang['no_results'], htmlspecialchars($searchString)), $lang, $language);
        exit;
    }

    // Post/Redirect/Get: results are shown on a GET page so that refresh and pagination work
    $_SESSION['matches'] = $foundMatches;
    header('Location: ?page=1');
    exit;
}

// ---------------------------------------------------------------------
// Download (GET)
// ---------------------------------------------------------------------
if (isset($_GET['download'])) {
    $fileUrl = (string)$_GET['download'];
    $prefix  = $nextcloudUrl . '/';

    if (!$captchaStillValid
        || strpos($fileUrl, $prefix) !== 0
        || strpos(rawurldecode($fileUrl), '..') !== false) {
        http_response_code(400);
        exit;
    }

    $fileName = rawurldecode(basename(parse_url($fileUrl, PHP_URL_PATH)));
    $fileName = str_replace(['"', "\r", "\n", '/', '\\'], '', $fileName);
    downloadFile($fileUrl, $fileName, $username, $appPassword);
    exit;
}

// ---------------------------------------------------------------------
// Results pages (GET)
// ---------------------------------------------------------------------
if (isset($_GET['page']) && !empty($_SESSION['matches'])) {
    renderResults($_SESSION['matches'], (int)$_GET['page'], $lang, $language);
    exit;
}

// ---------------------------------------------------------------------
// Search form
// ---------------------------------------------------------------------
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($language); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($lang['search_title']); ?></title>
    <link rel="stylesheet" href="styles.css">
    <?php if (!$captchaStillValid): ?>
      <script src="https://js.hcaptcha.com/1/api.js?hl=<?php echo htmlspecialchars($language); ?>" async defer></script>
    <?php endif; ?>
</head>
<body>
<div class="container">
    <div class="language-switch">
      <div class="logo">
        <img src="logo.png" alt="Logo" style="width: 157px; height: auto;">
      </div>
        <form method="get" id="languageForm">
            <label for="lang-select"><?php echo $language === 'lv' ? 'Valoda | Language:' : 'Language | Valoda:'; ?></label>
            <select name="lang" id="lang-select" onchange="document.getElementById('languageForm').submit();">
                <option value="lv" <?php echo $language === 'lv' ? 'selected' : ''; ?>>Latviešu</option>
                <option value="en" <?php echo $language === 'en' ? 'selected' : ''; ?>>English</option>
            </select>
        </form>
    </div>
    <h1><?php echo htmlspecialchars($lang['search_title']); ?></h1>
    <form method="POST" action="">
        <div class="description_text"><?php echo htmlspecialchars($lang['search_label']); ?></div>
        <input type="text" id="search_string" name="search_string" minlength="7" maxlength="100" required>
           <?php if (!$captchaStillValid): ?>
              <div class="h-captcha" data-sitekey="7150845c-2fd0-42d6-98ba-00ea777cc490"></div>
           <?php endif; ?>
        <button type="submit"><?php echo htmlspecialchars($lang['search_button']); ?></button>
    </form>
    <div class="description_text"><?php echo htmlspecialchars($lang['description_text']); ?></div>
</div>

<script type="text/javascript">
    const translations = {
        validation_message: <?= json_encode($lang['validation_message']) ?>,
        validation_empty_field: <?= json_encode($lang['validation_empty_field']) ?>
    };
</script>
<script>
    document.addEventListener("DOMContentLoaded", function () {
        const searchInput = document.getElementById("search_string");

        function validateField() {
            const value = searchInput.value.trim();
            const enteredCharacters = value.length;

            if (!value) {
                searchInput.setCustomValidity(translations.validation_empty_field);
            } else if (enteredCharacters < 7) {
                searchInput.setCustomValidity(
                    translations.validation_message.replace("${enteredCharacters}", enteredCharacters)
                );
            } else {
                searchInput.setCustomValidity("");
            }
        }

        searchInput.addEventListener("input", validateField);
        searchInput.form.addEventListener("submit", function (e) {
            validateField();
            if (!searchInput.checkValidity()) {
                searchInput.reportValidity();
                e.preventDefault();
            }
        });
    });
</script>
</body>
</html>
