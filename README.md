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
- 📊 **Search & download logging** — all activity stored in a SQLite database
- 🖥️ **Admin dashboard** — password-protected view of stats, recent searches, and downloads
- 🐳 **Docker support** — single command deployment

---

## 📁 File Structure

```
nextcloud-filefinder/
├── index.php           # Main application logic and HTML frontend
├── config.php          # Loads all settings from .env
├── logger.php          # SQLite logging for searches and downloads
├── admin.php           # Password-protected admin dashboard
├── lang_lv.php         # Latvian translations
├── lang_en.php         # English translations
├── styles.css          # Frontend styling
├── logo.png            # Company logo
├── Dockerfile          # PHP 8.2 + Apache container definition
├── docker-compose.yml  # Container orchestration
├── .env.example        # Environment variable template
├── .gitignore          # Excludes .env and other sensitive files
├── .dockerignore       # Excludes unnecessary files from Docker build
└── icons/              # Social media icons
```

---

## ⚙️ Configuration

All settings are managed via a `.env` file. Copy the template and fill in your values:

```bash
cp .env.example .env
nano .env
```

### `.env` reference

```env
# Nextcloud WebDAV
NEXTCLOUD_URL=https://your-nextcloud.com/remote.php/dav/files/your_user
NEXTCLOUD_USERNAME=your_username
NEXTCLOUD_APP_PASSWORD=your_app_password   # Use an App Password, not your main password

# hCaptcha (https://www.hcaptcha.com/)
HCAPTCHA_SECRET=your_hcaptcha_secret
HCAPTCHA_SITEKEY=your_hcaptcha_sitekey

# App settings
CAPTCHA_VALID_DURATION=1800   # seconds (30 min)
SEARCH_MIN_LENGTH=7
RESULTS_PER_PAGE=30
MAX_TRAVERSE_DEPTH=10

# Admin dashboard password
ADMIN_PASSWORD=change_me_to_a_strong_password

# SQLite database path (inside container)
DB_PATH=/var/www/data/searches.db

# Host port for Docker
APP_PORT=8080
```

> 💡 Generate a Nextcloud **App Password** under *Settings → Security → App passwords* instead of using your main account password.

---

## 🐳 Docker Deployment (recommended)

### Requirements
- [Docker](https://docs.docker.com/get-docker/) and [Docker Compose](https://docs.docker.com/compose/)

### Start

```bash
cp .env.example .env
nano .env                  # fill in all values

docker compose up -d       # build image and start container
docker compose logs -f     # follow logs
```

The app will be available at `http://your-server:8080`

### Stop / restart

```bash
docker compose down        # stop
docker compose up -d       # start again (data is preserved in volume)
```

### Rebuild after code changes

```bash
docker compose up -d --build
```

---

## 🗄️ SQLite Database

The database is created **automatically** on the first search — no setup needed.

It lives inside a Docker volume at `/var/www/data/searches.db` and persists across container restarts.

### Accessing the database

**Via admin dashboard** (easiest):
```
http://your-server:8080/admin.php
```

**Via terminal:**
```bash
# Open a shell inside the container
docker exec -it nextcloud-filefinder bash

# Query the database
sqlite3 /var/www/data/searches.db
SELECT * FROM searches ORDER BY searched_at DESC LIMIT 20;
SELECT * FROM downloads ORDER BY downloaded_at DESC LIMIT 20;
```

**Copy the DB file to your host:**
```bash
docker cp nextcloud-filefinder:/var/www/data/searches.db ./searches.db
```

**Use a host folder instead of a Docker volume** — change `docker-compose.yml`:
```yaml
volumes:
  - ./data:/var/www/data    # file will appear at ./data/searches.db
```

### Database tables

| Table | Columns |
|---|---|
| `searches` | `id`, `query`, `results`, `language`, `ip`, `searched_at` |
| `downloads` | `id`, `filename`, `ip`, `downloaded_at` |

---

## 🖥️ Admin Dashboard

Access at `http://your-server:8080/admin.php`

Login with the `ADMIN_PASSWORD` set in your `.env`. The dashboard shows:
- Total searches and downloads
- Searches performed today
- Top 5 most searched queries
- Full recent search and download history with IP and timestamp

---

## 🚀 Manual Deployment (without Docker)

### Requirements
- PHP 8.0+ with `curl`, `simplexml`, and `pdo_sqlite` extensions
- Apache or Nginx web server

### Steps

1. Upload all files to your web server's public directory (e.g. `/var/www/html/certificates/`)
2. Create a writable `data/` directory for the database: `mkdir data && chown www-data:www-data data`
3. Copy and fill in `.env`: `cp .env.example .env`
4. Visit the URL in a browser — the search form should appear

---

## 🔒 Security Notes

- The `.env` file is excluded by `.gitignore` — never commit real credentials
- hCaptcha session is valid for **30 minutes** — configurable via `CAPTCHA_VALID_DURATION`
- Search requires a minimum of **7 characters** to prevent broad queries
- Directory traversal is capped at **10 levels** deep
- Admin dashboard is protected by `ADMIN_PASSWORD` — use a strong password

---

## 🌍 Internationalization

Language files: `lang_lv.php` (Latvian) and `lang_en.php` (English). To add a new language:

1. Copy `lang_en.php` to `lang_xx.php` (replace `xx` with your language code)
2. Translate all values
3. Add the option to the `<select>` dropdown in `index.php`

---

## 📜 License

© Ģirts Bebrovskis, 2025. All rights reserved.
