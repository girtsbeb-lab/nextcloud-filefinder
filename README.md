# 📄 Nextcloud File Finder — Certificate Search & Download

A lightweight **PHP 8.4** web application for searching and downloading quality certificates stored in a **Nextcloud** instance via WebDAV, backed by a **MySQL 8.4** database.

---

## ✨ Features

- **Full directory search**: Recursively traverses Nextcloud WebDAV folders to find matching files.
- **Direct file download**: Serves files to the user straight from Nextcloud.
- **hCaptcha protection**: Bot prevention with secure session handling.
- **Bilingual UI**: Latvian and English language support.
- **Search & download logging**: Activity stored securely in a MySQL database.
- **Admin dashboard**: Password-protected view for stats and recent activity.
- **Docker support**: Single-command deployment with persistent storage.

---

## 📁 Project Structure

sertifikati/
├── conf/
│   └── .env.example       # Environment configuration template
├── schema.sql             # MySQL database structure (downloads & searches)
├── Dockerfile             # PHP 8.4 + Apache container definition
├── docker-compose.yml     # Multi-container stack orchestration
├── logo.png               # Tracked project branding logo
└── [PHP application files & assets]

---

⚙️ Quick Start & Deployment
1. Requirements
Docker and Docker Compose installed on your server.

2. Configure Environment
Copy the configuration template and populate your credentials:

Bash
cp conf/.env.example .env
nano .env

3. Start the Application
Bash
docker compose up -d --build
Maintenance Commands
Stop stack: docker compose down

View logs: docker compose logs -f

---

🗄️ Database Setup
The database uses MySQL 8.4. Table schemas for tracking searches and downloads are defined in schema.sql.

---

🔒 Security
Sensitive credentials remain isolated in .env (excluded via .gitignore).

Admin authentication uses secure password hashing.

---

📜 License
© Ģirts Bebrovskis, 2026. All rights reserved.
