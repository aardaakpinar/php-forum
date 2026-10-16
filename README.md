<div align="center">

# PHP Forum System

**A lightweight, modern, and security-hardened discussion platform built with native PHP 8.1+ and MySQL / MariaDB.**

[![Docker Hub](https://img.shields.io/badge/Docker%20Hub-aardaakpinar%2Fphp--forum-2496ED?style=for-the-badge&logo=docker&logoColor=white)](https://hub.docker.com/r/aardaakpinar/php-forum)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D%208.1-777BB4?style=for-the-badge&logo=php&logoColor=white)](#requirements)
[![Database](https://img.shields.io/badge/MySQL%20%2F%20MariaDB-00618A?style=for-the-badge&logo=mysql&logoColor=white)](#database--environment-variables)
[![Architecture](https://img.shields.io/badge/Architecture-Custom%20MVC-f59e0b?style=for-the-badge)](#system-architecture)
[![License](https://img.shields.io/badge/License-MIT-emerald?style=for-the-badge)](#license)

</div>

---

## Table of Contents

- [Overview](#overview)
- [Key Features](#key-features)
- [Security Architecture & Hardening](#security-architecture--hardening)
- [Installation & Setup](#installation--setup)
    - [Requirements](#requirements)
    - [Option 1: Docker Compose (Recommended)](#option-1-docker-compose-recommended)
    - [Option 2: Manual Setup (Apache / Local Environment)](#option-2-manual-setup-apache--local-environment)
- [Configuration & Environment Variables](#configuration--environment-variables)
- [First Run & Admin Access](#first-run--admin-access)
- [Project Status](#project-status)
- [License](#license)

---

## Overview

**PHP Forum System** was initially engineered during an internship to explore deep practical implementations of modern PHP architecture, database scalability, and web application security standards.

The project features a **custom lightweight MVC framework** written in vanilla PHP with zero bloated dependencies. It combines a modern, responsive dark UI with strict defensive programming practices to mitigate common web security vulnerabilities out of the box.

---

## Key Features

### Community & Discussions

- **Topic Categories:** Categorized board discussions managed and sorted via admin controls.
- **Rich Thread Management:** Create discussions, post replies, and quote previous messages.
- **Markdown Formatting:** Interactive Markdown toolbar supporting lists, blockquotes, code blocks, and formatted text.
- **Engagement Tools:** Thread like/unlike system, view counts, and unread read-state tracking.
- **Subscriptions & Notifications:** Follow specific threads and receive real-time notification alerts.
- **Smart Filters & Sorting:** Filter threads by _Latest_, _New_, _Top_, _Most Viewed_, _Following_, _My Threads_, and _Unanswered_.
- **Full-Text Search:** Search thread titles and messages quickly from the header bar.

### User Profiles & Preferences

- Public profile pages with bio, join date, created threads, and user reply history.
- Self-service account settings (update bio, change password, account details).
- Uniform avatar badge generation with initials fallback.

### Administration & Moderation (`/admin`)

- **Dashboard Metrics:** Real-time statistics on total members, threads, replies, and categories.
- **Category Control:** Create, edit, reorder, or delete discussion categories.
- **User Management:** View member rosters, change roles, or assign administrative privileges.
- **Custom Branding:** Upload a header brand logo (PNG, JPEG, WebP, GIF) and, separately, a favicon (ICO, PNG, SVG, WebP, GIF) with strict MIME validation. The two are configured independently under Admin → Settings → General.
- **Version info:** The admin Overview shows the forum version (from the `VERSION` file or the `FORUM_VERSION` env var), schema version, PHP/database versions and upload-folder status.
- **Access Control:** Enable **Maintenance Mode** (admin-only access) or **Private Mode** (require authentication to read threads).
- **Auto-Bootstrapping:** The first registered account automatically becomes the Site Administrator.

### Design & Frontend

- Modern dark theme styled with the sleek Tailwind Zinc color palette.
- Self-hosted Inter font and embedded Lucide icons (no third-party CDN leaks).
- Fully responsive across desktop, tablet, and mobile layouts.
- Zero external JS framework dependencies; 100% CSP-safe without inline scripts.

---

## Security Architecture & Hardening

Security is treated as a first-class requirement across every layer of the application:

| Security Domain                       | Mitigation Strategy               | Implementation Details                                                                                                                                                                                                           |
| :------------------------------------ | :-------------------------------- | :------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **SQL Injection (SQLi)**              | Prepared Statements               | Native PDO with `PDO::ATTR_EMULATE_PREPARES => false`. All queries use parameterized inputs; raw string concatenation is prohibited.                                                                                             |
| **Cross-Site Scripting (XSS)**        | Output Encoding & Strict CSP      | Strict contextual HTML escaping (`e()` helper) across all views. Content Security Policy headers disallow inline scripts and restrict resource loading to `'self'`.                                                              |
| **Cross-Site Request Forgery (CSRF)** | Synchronizer Token Pattern        | Cryptographic per-session anti-CSRF tokens enforced on all state-changing POST requests. Cookie header set with `SameSite=Strict`.                                                                                               |
| **Session Security**                  | Strict Cookie & Fixation Guards   | `session.use_strict_mode = 1`, `session.cookie_httponly = 1`, and secure flags. Session IDs automatically regenerate on privilege elevation and every 15 minutes.                                                                |
| **Password Security**                 | Modern Hashing & Policies         | Secure hashing via `password_hash()` (Bcrypt / Argon2 default). Length and complexity enforcement on registration and password updates.                                                                                          |
| **Brute-Force Protection**            | Rate Limiting & Account Lockout   | Failed login attempts are throttled. Identical, timing-neutral error messages prevent username enumeration.                                                                                                                      |
| **Secure File Uploads**               | Strict Validation & Randomization | Admin logo upload validates MIME type via `finfo(FILEINFO_MIME_TYPE)` (not file extensions), enforces a 2 MB size ceiling, and stores files using cryptographically random 32-character hex names (`bin2hex(random_bytes(16))`). |
| **HTTP Security Headers**             | Defense-in-Depth Headers          | Headers enforced: `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: no-referrer`, `Permissions-Policy`, and `HSTS` (when served over HTTPS).                                                         |
| **Information Disclosure**            | Production Error Handling         | `display_errors = 0` and `log_errors = 1`. Unhandled exceptions show generic, user-safe error views while writing diagnostics to server logs.                                                                                    |
| **Directory & File Protection**       | Web Server Hardening              | `.htaccess` restricts access to `/app`, `/includes`, `.git`, `.env`, and config files. Directory indexing is permanently disabled (`Options -Indexes`).                                                                          |

---

## Installation & Setup

### Requirements

- **PHP:** 8.1 or higher (PHP 8.3 recommended)
- **Web Server:** Apache (with `mod_rewrite` and `mod_headers` enabled)
- **Database:** MySQL 5.7+ or MariaDB 10.3+ (InnoDB engine required)
- **PHP Extensions:** `pdo`, `pdo_mysql`, `fileinfo`
- **SSL / HTTPS:** Strongly recommended for production environments

---

### Option 1: Docker Compose (Recommended)

#### A. Run with Pre-built Docker Hub Image (Zero-Clone Setup)
You don't need to clone the repository. Simply create a `docker-compose.yml` file:

```yaml
services:
  web:
    image: aardaakpinar/php-forum:latest
    ports:
      - "8080:80"
    volumes:
      - uploads_data:/var/www/html/assets/uploads
    environment:
      DB_HOST: db
      DB_PORT: "3306"
      DB_NAME: forum
      DB_USER: forum
      DB_PASS: str0ng-p4ssw@rd
      FORUM_NAME: "My Community Forum"
    depends_on:
      db:
        condition: service_healthy
    restart: unless-stopped

  db:
    image: mariadb:11
    environment:
      MARIADB_DATABASE: forum
      MARIADB_USER: forum
      MARIADB_PASSWORD: str0ng-p4ssw@rd
      MARIADB_RANDOM_ROOT_PASSWORD: "1"
    volumes:
      - db_data:/var/lib/mysql
    healthcheck:
      test: ["CMD", "healthcheck.sh", "--connect", "--innodb_initialized"]
      interval: 5s
      timeout: 5s
      retries: 20
    restart: unless-stopped

volumes:
  db_data:
  uploads_data:
```

Then start the stack:
```bash
docker compose up -d
```

#### B. Build & Run from Source (Developers)
1. **Clone the repository:**
   ```bash
   git clone https://github.com/aardaakpinar/php-forum.git
   cd php-forum
   ```
2. **Start the containers:**
   ```bash
   docker compose up --build -d
   ```

3. **Access the application:**
   - Open [http://localhost:8080](http://localhost:8080) in your browser.
   - The MariaDB database container automatically initializes with health checks.
   - Database tables are automatically migrated on your first visit.

To stop the containers:
```bash
docker compose down
```

---

### Option 2: Manual Setup (Apache / Local Environment)

1. **Clone or move the project into your web server directory:**

    ```bash
    git clone https://github.com/aardaakpinar/php-forum.git /var/www/html/php-forum
    cd /var/www/html/php-forum
    ```

2. **Create the MySQL database and user:**

    ```sql
    CREATE DATABASE forum CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    CREATE USER 'forum'@'localhost' IDENTIFIED BY 'str0ng-p4ssw@rd';
    GRANT ALL PRIVILEGES ON forum.* TO 'forum'@'localhost';
    FLUSH PRIVILEGES;
    ```

3. **Set directory write permissions:**
   Ensure the web server can write to the logo upload directory:

    ```bash
    chmod -R 775 assets/uploads
    chown -R www-data:www-data assets/uploads  # On Linux / Apache
    ```

4. **Configure environment variables or Apache `SetEnv`:**
   Configure connection variables in your server configuration or `.env` file (see [Configuration](#configuration--environment-variables)).

5. **Start your server:**
   Navigate to the project URL in your browser. All database tables and default categories will be automatically generated.

> [!NOTE]
> You do **not** need to manually import an SQL dump file. The application executes automated, idempotent schema migrations on the first request via `app/core/schema.php`.

---

## Configuration & Environment Variables

The application reads its configuration from system environment variables in `config.php`. If variables are omitted, secure local defaults are applied:

| Variable        | Description                  | Default Value                      | Example              |
| :-------------- | :--------------------------- | :--------------------------------- | :------------------- |
| `DB_HOST`       | Database host address        | `127.0.0.1`                        | `localhost` or `db`  |
| `DB_PORT`       | Database server port         | `3306`                             | `3306`               |
| `DB_NAME`       | Database name                | `forum`                            | `forum_production`   |
| `DB_USER`       | Database username            | `forum`                            | `db_user`            |
| `DB_PASS`       | Database password            | `str0ng-p4ssw@rd`                  | `SecretP@ssw0rd!`    |
| `FORUM_NAME`    | Display name of the forum    | `Forum`                            | `DevCommunity`       |
| `FORUM_TAGLINE` | Subtitle displayed in header | `A community for open discussion.` | `Code, Learn, Share` |

---

## First Run & Admin Access

1. Open the forum in your browser.
2. Click on **Register** to create an account.
3. **The very first account registered is automatically granted the `admin` role.**
4. Once registered, access the administrative dashboard at `/admin` to configure site branding, categories, and moderation settings.

---

## Project Status

This project serves as a showcase of clean code architecture, lightweight custom framework patterns, and defensive web application engineering in modern PHP.

It is actively maintained as an educational reference and foundation for lightweight, security-first community discussion systems.

---

## License

The source code in this repository is distributed under the **[MIT License](LICENSE)**.

```
Copyright (c) 2026 Arda
```
