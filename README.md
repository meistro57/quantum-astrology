[![PHP Composer](https://github.com/meistro57/quantum-astrology/actions/workflows/php.yml/badge.svg)](https://github.com/meistro57/quantum-astrology/actions/workflows/php.yml)
## IMPORTANT NOTE... THIS PROJECT IS IN BETA. 

# Quantum Astrology <img width="133" height="172" alt="image" src="https://github.com/user-attachments/assets/bf5c3fdb-5e4d-4d28-8a65-b7232a3583ae" />

Professional astrology software suite with Quantum Minds United branding. Swiss Ephemeris–powered chart calculations with clean, responsive SVG chart wheels.

<img width="1246" height="1813" alt="image" src="https://github.com/user-attachments/assets/4882ceda-b18d-4163-b5c2-98ea8d50f9a7" />

<img width="1257" height="1780" alt="image" src="https://github.com/user-attachments/assets/3e84b4fd-dd16-43ba-9382-53ba404a2ede" />

<img width="1242" height="1799" alt="image" src="https://github.com/user-attachments/assets/75a14a54-321b-4f34-8d80-950084b60aaf" />

<img width="1224" height="496" alt="image" src="https://github.com/user-attachments/assets/2d6e37d9-f8e4-4275-82fa-08406fd5cf34" />

<img width="1222" height="642" alt="image" src="https://github.com/user-attachments/assets/f15b3825-b2ac-4c9a-84da-58e3b27c5ce1" />

<img width="1236" height="1821" alt="image" src="https://github.com/user-attachments/assets/22d3e0da-0219-43d3-96b8-9862abe93e65" />

---

## Overview

Quantum Astrology provides professional-grade astrological calculations and chart generation with a modern, intuitive interface. Built on the Swiss Ephemeris for astronomical accuracy, the system integrates seamlessly with the Quantum Minds United ecosystem.

---

## ✅ Current Features (v1.3)

- **Transit Analysis UI** — interactive frontend for real-time planetary transits
- **Swiss Ephemeris Integration** — precise planetary positions and house cusps
- **Natal Chart Generation** — complete planetary positions, houses, and aspects
- **House Alignment** — ASC≈cusp 1 and MC≈cusp 10 verified across swetest outputs
- **SVG Chart Wheels** — polished wheel with **zodiac wedges**, planets, and aspect chords
- **Aspect Engine** — configurable orbs and detection of major aspects
- **User Authentication** — secure registration, login, and profile management (MySQL/SQLite)
- **Chart Management API** — create, get, list, delete, export, and paginated chart endpoints
- **Validation** — strict input checks for date/time, timezone, lat/lon, and house systems
- **Database Migrations** — schema setup with version tracking and SQLite fallback
- **Report Scaffolding** — integrated PDF generation engine via mPDF
- **AI Summary Reports** — generate pretty in-app AI summary previews and download Markdown (`.md`) summary files
- **AI Governance Controls** — AI provider/model and summary prompt behavior are now admin-managed from `/admin`
- **Chart View AI Utilities** — AI reading panel supports quick **Copy** and **Download** actions
- **Chart Symbol Legend** — chart view includes an on-page glyph legend for planets, signs, and major aspects
- **Profile Enhancements** — saved birth data, city/state coordinate auto-fill, and in-profile password change
- **Report Privacy Controls** — profile checkboxes let users hide birth date/time and/or birth location in generated PDF reports
- **System Admin Panel (`/admin`)** — admin-only operational panel with system metrics, log tailing, cache maintenance actions, and user administration tools
- **Redis Dashboard (Admin)** — live Redis health/metrics panel in `/admin` with status, memory, key count, hit rate, ops/sec, and connection diagnostics
- **User Administration** — manually create users, reset passwords, and grant/revoke admin status from the admin panel
- **Admin System Operations** — one-click syntax/error check, chart smoke test, DB migration update, cache rebuild, and storage audit from `/admin`
- **GitHub Issues Shortcut** — configurable `/admin` link for opening your project issue tracker quickly
- **Persistent Login Sessions** — beta-friendly long-lived login cookies (configurable via `.env`) so users stay signed in across browser restarts
- **Report API Envelope Normalization** — `/api/reports/generate.php` now returns a single canonical JSON envelope (`success`, `data`, `meta`) to avoid duplicated response fields

---

## 🔜 Next Development Phase

- Progressions and solar returns hardening + test coverage  
- Expand report templates and interpretation depth  
- API consistency cleanup (response shape/versioning across legacy and newer endpoints)  
- End-to-end regression tests for chart create/list/delete/export flows  
- UX polish for large chart libraries (filters/search and richer pagination controls)  
- AI report quality controls (prompt templates, section tuning, and output style presets)

---

## 📌 Future Features

- Synastry & composite charts  
- PDF report generation with QMU branding  
- AI-powered interpretation system  
- Multi-language support  
- Mobile app integration (React Native)  

---

## Technology Stack

- **Backend**: PHP 8+ with PSR-4 autoloading  
- **Database**: MySQL 8+ with JSON column support  
- **Frontend**: Vanilla JS with Quantum UI components  
- **Charts**: SVG generation with responsive scaling  
- **Auth**: Session-based login with secure password hashing  
- **Calculations**: Swiss Ephemeris (swetest CLI)  

---

## Quick Start

### Prerequisites
- PHP 8.0 or higher  
- MySQL 5.7+ or MariaDB 10.3+  
- Apache 2.4+ or Nginx 1.18+  
- Composer for dependency management  

### Installation
```bash
git clone https://github.com/meistro57/quantum-astrology.git
cd quantum-astrology
composer install
cp .env.example .env
# edit .env with your database + swetest path
php tools/migrate.php
bash start_server.sh

# production (docker compose)
bash startup.sh
# stop production containers
bash shutdown.sh
```

### Production Docker Setup (Explicit)

Use this checklist for a production-safe deployment:

1. Copy and edit environment settings:
   ```bash
   cp .env.example .env
   ```
2. Set secure production values in `.env` (minimum):
   ```env
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://your-domain.example
   SERVER_NAME=your-domain.example
   DB_DRIVER=mysql
   DB_HOST=db
   DB_PORT=3306
   DB_NAME=quantum_astrology
   DB_USER=quantum_app
   DB_PASS=replace_with_strong_password
   MYSQL_ROOT_PASSWORD=replace_with_strong_root_password
   HTTP_PORT=80
   HTTPS_PORT=443
   TLS_MODE=real
   TLS_CERT_FILE=server.crt
   TLS_KEY_FILE=server.key
   ```
3. Provide TLS material:
   - `TLS_MODE=self-signed`: `startup.sh` auto-generates cert/key in `docker/certs/`.
   - `TLS_MODE=real`: place cert/key files at `docker/certs/${TLS_CERT_FILE}` and `docker/certs/${TLS_KEY_FILE}`.
4. Start the stack:
   ```bash
   bash startup.sh
   ```
5. Verify containers and app health:
   ```bash
   docker compose -f docker-compose.prod.yml ps
   curl -k https://your-domain.example/api/health.php
   ```
6. Stop services when needed:
   ```bash
   bash shutdown.sh
   ```

`startup.sh` enforces TLS mode checks and the Nginx proxy redirects HTTP (`80`) to HTTPS (`443`).
For full details and troubleshooting, see [`docs/production-docker.md`](docs/production-docker.md).

### Pre-Push Documentation Guard

Enable the tracked pre-push hook so code changes require corresponding doc updates before push:

```bash
git config core.hooksPath .githooks
```

The hook blocks pushes when source files change without updates to docs such as `README.md`, `INSTALL.md`,
`CHANGELOG.md`, `ROADMAP.md`, `TODO.md`, `SYSTEM_STATUS.md`, `AGENTS.md`, or files under `docs/`.

If you need a one-off bypass:

```bash
SKIP_DOCS_CHECK=1 git push
```

Production note: `docker-compose.prod.yml` includes default credentials for first boot only.
Always rotate `DB_PASS` and `MYSQL_ROOT_PASSWORD` before exposing the stack publicly.

### Admin Panel Access

`/admin` is restricted to admin users. A user is treated as admin when any of the following is true:

- User ID is `1`
- `users.is_admin = 1`
- User email is listed in `ADMIN_EMAILS` (comma-separated) or `ADMIN_EMAIL`

Once an admin is signed in, user administration (create/reset/grant/revoke) is available directly inside `/admin`.

`/admin` also controls system AI settings, including:
- master AI provider/model/API key
- AI summary system prompt/style/length/focus template

When set, these admin AI settings are treated as the system defaults used by report/chart AI flows.

`/admin` also includes:
- system operations buttons for syntax checks, migrations, cache rebuild, storage audit, and chart smoke checks
- database backup create/list/download/delete controls
- a quick link to GitHub Issues (configured via `GITHUB_ISSUES_URL`)

### Environment Variables

Consult [`docs/environment.md`](docs/environment.md) for a complete catalogue of supported environment variables, including
database credentials, Swiss Ephemeris paths, caching controls, and session tuning. The guide outlines defaults, production
considerations, and a sample `.env` layout to accelerate both local prototyping and secure deployments.

### Maintenance Utilities

- `php tools/clear-cache.php` — clears the application cache using the shared storage maintenance routines.
- `php tools/manage-storage.php --list` — inspects cache, ephemeris, chart, and report directories with a friendly audit summary.
- `php tools/manage-storage.php --purge --target=ephemeris --older-than=30` — prunes cached ephemeris files older than 30 days, keeping recent calculations intact.

