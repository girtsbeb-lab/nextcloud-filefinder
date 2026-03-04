# 📄 Nextcloud File Finder — Certificate Search & Download

A lightweight PHP web application for searching and downloading quality certificates stored in a **Nextcloud** instance via WebDAV. Designed for companies that provide electronic access to product analysis certificates, reducing paper usage and supporting sustainable operations.

---

## ✨ Features

- 🔍 **Full directory search** — recursively traverses Nextcloud WebDAV folders to find matching files
- 📥 **Direct file download** — serve files to the user straight from Nextcloud
- 🛡️ **hCaptcha protection** — bot prevention with a 30-minute session validity window (captcha shown only once per session)
- 🌐 **Bilingual UI** — Latvian and English language support with session-based switching
- 📄 **Paginated results** — 30 results per page for clean navigation
- 📱 **Responsive design** — mobile-friendly layout using Inter font and clean modern CSS

---

## 📁 File Structure

```
nextcloud-filefinder/
├── index.php        # Main application logic and HTML frontend
├── lang_lv.php      # Latvian translations
├── lang_en.php      # English translations
├── styles.css       # Frontend styling
├── logo.png         # Company logo
└── icons/           # Social media icons (used in branding/footer)
    ├── facebook.png
    ├── Instagram.png
    ├── linkedin.png
    ├── telegram.png
    ├── tiktok.png
    ├── whatsapp.png
    ├── x.png
    ├── youtube.png
    └── microsoft-teams.png
```

---

## ⚙️ Configuration

Open `index.php` and update the following variables near the top of the file:

```php
// Nextcloud WebDAV endpoint
$nextcloudUrl = "https://your-nextcloud.com/remote.php/dav/files/your_user";

// Nextcloud credentials
$username = "your_username";
$appPassword = "your_app_password"; // Use an App Password, not your main password

// hCaptcha secret key (from hcaptcha.com dashboard)
$hcaptchaSecret = "your_hcaptcha_secret";
```

Also update the **hCaptcha site key** in the HTML form section of `index.php`:

```html
<div class="h-captcha" data-sitekey="your-hcaptcha-site-key"></div>
```

---

## 🚀 Deployment

### Requirements
- PHP 7.4+ with `curl` and `simplexml` extensions enabled
- A web server (Apache / Nginx)
- A Nextcloud instance accessible over WebDAV
- An [hCaptcha](https://www.hcaptcha.com/) account (free tier works)

### Steps

1. Upload all files to your web server's public directory (e.g. `/var/www/html/`)
2. Set your configuration values in `index.php` as described above
3. Make sure PHP sessions are enabled on your server
4. Visit the URL in a browser — the search form should appear

> 💡 **Tip:** Generate a Nextcloud **App Password** under *Settings → Security → App passwords* instead of using your account password.

---

## 🔒 Security Notes

- Never commit real credentials to version control — use environment variables or a separate config file excluded via `.gitignore`
- The hCaptcha session is valid for **30 minutes** (`$captcha_valid_duration = 1800`) — adjust as needed
- Search requires a **minimum of 7 characters** to prevent overly broad queries
- Directory traversal depth is capped at **10 levels**

---

## 🌍 Internationalization

Language files are located in `lang_lv.php` (Latvian) and `lang_en.php` (English). To add a new language:

1. Copy `lang_en.php` to `lang_xx.php` (replace `xx` with your language code)
2. Translate the values
3. Add the language option to the `<select>` dropdown in `index.php`

---

## 📜 License

© Ģirts Bebrovskis, 2025. All rights reserved.
