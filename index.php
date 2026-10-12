<?php
/**
 * Sertifikātu meklēšanas un lejupielādes sistēma ar hCaptcha aizsardzību
 * ==========================================================================
 * Šī PHP skripta galvenās funkcijas:
 * - Veic meklēšanu caur Nextcloud WebDAV API publiskajā direktorijā.
 * - Nodrošina lejupielādi atrastajiem failiem.
 * - Iekļauj hCaptcha verifikāciju, lai novērstu ļaunprātīgus pieprasījumus.
 * - hCaptcha tiek prasīta tikai vienu reizi uz 15 minūtēm (sesijā).
 * - Atbalsta valodu maiņu (latviešu / angļu).
 * - Iespēja manuāli atiestatīt hCaptcha sesiju ar pogu.
 *
 * Drošība:
 * - hCaptcha validācija tiek saglabāta sesijā: $_SESSION['hcaptcha_valid_until']
 * - Piekļuve Nextcloud tiek veikta ar statisku lietotājvārdu un lietotni (app password).
 *
 * Lietošanas gadījumi:
 * - Sertifikātu meklēšana pēc faila nosaukuma fragmenta (min. 7 simboli)
 * - Sertifikātu lejupielāde
 * - Lietotājam draudzīga valodu pārslēgšana un kļūdu paziņojumi
 *
 * Konfigurācijas parametri:
 * - Nextcloud WebDAV URL: $nextcloudUrl
 * - Nextcloud lietotājs: $username
 * - App parole: $appPassword
 * - hCaptcha slepenā atslēga: $hcaptchaSecret
 * - hCaptcha sitekey: HCAPTCHA_SITEKEY (no config.php / .env)
 * - CAPTCHA derīguma ilgums: $captcha_valid_duration (sekundēs)
 *
 * © Ģirts Bebrovskis, 2025
 */
session_start();
if (isset($_GET['logout'])) {
    unset($_SESSION['hcaptcha_valid_until']);
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit;
}

$captchaStillValid = isset($_SESSION['hcaptcha_valid_until']) && $_SESSION['hcaptcha_valid_until'] >= time();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/logger.php';

$nextcloudUrl       = NEXTCLOUD_URL;
$username           = NEXTCLOUD_USERNAME;
$appPassword        = NEXTCLOUD_PASSWORD;
$hcaptchaSecret     = HCAPTCHA_SECRET;
$captcha_valid_duration = CAPTCHA_VALID_DURATION;

// Valodas noteikšana un saglabāšana sesijā
if (isset($_GET['lang']) && in_array($_GET['lang'], ['lv', 'en'])) {
    $_SESSION['lang'] = $_GET['lang'];
}

$language = $_SESSION['lang'] ?? 'lv';
$lang = include __DIR__ . "/lang_{$language}.php";

/**
 * Noņem nosaukumu prefiksus no XML.
 */
function removeNamespacesUsingRegex($xmlString) {
    $xmlString = preg_replace('/<\?xml.*?\?>/', '', $xmlString);
    $xmlString = preg_replace('/(<\/?)(\w+):/', '$1', $xmlString);
    return trim($xmlString);
}

function downloadFile($fileUrl, $fileName, $username, $appPassword) {
    if (!ob_get_level()) {
        ob_start();
    }

    $downloadCh = curl_init();
    curl_setopt($downloadCh, CURLOPT_URL, $fileUrl);
    curl_setopt($downloadCh, CURLOPT_USERPWD, $username . ":" . $appPassword);
    curl_setopt($downloadCh, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($downloadCh, CURLOPT_TIMEOUT, 30);

    $data = curl_exec($downloadCh);
    $httpCode = curl_getinfo($downloadCh, CURLINFO_HTTP_CODE);
    curl_close($downloadCh);

    if ($httpCode === 200 && !empty($data)) {
        // Log the download
        logDownload($fileName);

        header("Content-Description: Faila pārsūtīšana");
        header("Content-Type: application/octet-stream");
        header("Content-Disposition: attachment; filename=\"$fileName\"");
        header("Content-Transfer-Encoding: binary");
        header("Content-Length: " . strlen($data));

        if (ob_get_level()) {
            ob_clean();
        }
        flush();
        echo $data;
        exit;
    }
}

function traverseDirectory($baseUrl, $username, $appPassword, $currentDir, &$visited, &$foundMatches, $depth, $searchString) {
    if ($depth > MAX_TRAVERSE_DEPTH) return;

    $url = $currentDir ?: $baseUrl;
    if (in_array($url, $visited)) return;
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

        $fileName = basename($normHref);
        $isDirectory = stripos($responseItem->propstat->prop->resourcetype->asXML(), '<collection') !== false;
        $fullPath = "https://cloud.neo.lv" . $href;

        if (!$isDirectory) {
            if (!empty($searchString) && stripos($fileName, $searchString) !== false) {
                $foundMatches[] = ['url' => $fullPath, 'name' => $fileName];
            }
        } else {
            traverseDirectory($baseUrl, $username, $appPassword, $fullPath, $visited, $foundMatches, $depth + 1, $searchString);
        }
    }
}

function verifyHCaptcha($secret, $responseToken, $remoteIp = null) {
    $url = 'https://hcaptcha.com/siteverify';
    $data = [
        'secret'   => $secret,
        'response' => $responseToken,
        'remoteip' => $remoteIp
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_TIMEOUT        => 30
    ]);
    $result   = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) return false;

    $resultData = json_decode($result, true);
    return $resultData['success'] ?? false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Check if hCaptcha was recently verified
    if (!isset($_SESSION['hcaptcha_valid_until']) || $_SESSION['hcaptcha_valid_until'] < time()) {
        $hcaptchaResponse = $_POST['h-captcha-response'] ?? "";
        if (empty($hcaptchaResponse) || !verifyHCaptcha($hcaptchaSecret, $hcaptchaResponse, $_SERVER['REMOTE_ADDR'])) {
            echo "<p>" . htmlspecialchars($lang['captcha_failed']) . "</p>";
            echo "<p><a href=\"" . htmlspecialchars($_SERVER['PHP_SELF']) . "\">" . htmlspecialchars($lang['back_to_search']) . "</a></p>";
            exit;
        }
        $_SESSION['hcaptcha_valid_until'] = time() + $captcha_valid_duration;
    }

    $searchString = trim($_POST['search_string'] ?? "");
    if (strlen($searchString) < SEARCH_MIN_LENGTH) {
        echo "<p>" . htmlspecialchars($lang['min_characters']) . "</p>";
        echo "<p><a href=\"" . htmlspecialchars($_SERVER['PHP_SELF']) . "\">" . htmlspecialchars($lang['back_to_search']) . "</a></p>";
        exit;
    }

    $visited      = [];
    $foundMatches = [];
    traverseDirectory($nextcloudUrl, $username, $appPassword, "", $visited, $foundMatches, 0, $searchString);

    // Log the search
    logSearch($searchString, count($foundMatches), $language);

    if (empty($foundMatches)) {
        echo "<p>" . sprintf($lang['no_results'], htmlspecialchars($searchString)) . "</p>";
        echo "<p><a href=\"" . htmlspecialchars($_SERVER['PHP_SELF']) . "\">" . htmlspecialchars($lang['back_to_search']) . "</a></p>";
    } else {
        $resultsPerPage = RESULTS_PER_PAGE;
        $totalResults   = count($foundMatches);
        $totalPages     = ceil($totalResults / $resultsPerPage);
        $currentPage    = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $startIndex     = ($currentPage - 1) * $resultsPerPage;
        $endIndex       = min($startIndex + $resultsPerPage, $totalResults);

        echo "<div class='container'>";
        echo "<p>" . sprintf($lang['search_results'], $currentPage, $totalPages) . "</p><ul>";

        for ($i = $startIndex; $i < $endIndex; $i++) {
            $file = $foundMatches[$i];
            echo "<li><a href=\"?download=" . urlencode($file['url']) . "\">" . htmlspecialchars($file['name']) . "</a></li>";
        }

        echo "</ul></div><div class='pagination'>";
        if ($currentPage > 1) echo "<a href=\"?page=" . ($currentPage - 1) . "\">" . $lang['previous_page'] . "</a> ";
        if ($currentPage < $totalPages) echo "<a href=\"?page=" . ($currentPage + 1) . "\">" . $lang['next_page'] . "</a>";
        echo "</div>";
        echo "<p><a href=\"" . htmlspecialchars($_SERVER['PHP_SELF']) . "\">" . htmlspecialchars($lang['back_to_search']) . "</a></p>";
    }

} elseif (isset($_GET['download'])) {
    $fileUrl  = $_GET['download'];
    $fileName = basename(parse_url($fileUrl, PHP_URL_PATH));
    downloadFile($fileUrl, $fileName, $username, $appPassword);
    exit;

} else {
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($language); ?>">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($lang['search_title']); ?></title>
    <link rel="stylesheet" href="styles.css">
    <?php if (!$captchaStillValid): ?>
      <script src="https://js.hcaptcha.com/1/api.js?hl=<?php echo $language; ?>" async defer></script>
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
        <input type="text" id="search_string" name="search_string" minlength="<?php echo SEARCH_MIN_LENGTH; ?>" required>
        <?php if (!$captchaStillValid): ?>
            <div class="h-captcha" data-sitekey="<?php echo htmlspecialchars(HCAPTCHA_SITEKEY); ?>"></div>
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
            } else if (enteredCharacters < <?= SEARCH_MIN_LENGTH ?>) {
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
<?php } ?>
