# Quantum Astrology - Agent Guide

This repository contains a professional astrology software suite powered by Swiss Ephemeris and featuring a Quantum Minds United aesthetic.

## 🛠 Essential Commands

- **Full Setup & Test**: `bash install-and-test.sh`
  - Installs Composer locally if missing.
  - Installs PHP dependencies.
  - Runs database migrations.
  - Executes syntax and functionality tests.
- **Setup & Migration**: `php tools/migrate.php`
  - Handles both MySQL and SQLite automatically.
  - Track migrations in the `migrations` table.
- **Development Server**: `bash start_server.sh` or `php -S localhost:8080 index.php`
- **Tests**: `composer test` or `vendor/bin/phpunit`
- **Syntax Check**: `php test-syntax.php`
- **Storage Maintenance**: `php tools/manage-storage.php --list`
- **Clear Cache**: `php tools/clear-cache.php`

## 🏗 Project Structure

- `api/`: Procedural API endpoints (e.g., `chart_svg.php`, `chart_create.php`).
- `assets/`: Frontend assets (CSS, JS, images).
- `classes/`: PSR-4 discovery root (`QuantumAstrology`).
  - `Core/`: Foundational classes (Application, Auth, DB, Env, Session).
  - `Charts/`: Astrological logic (SwissEphemeris, Chart, ChartWheel, Transit, etc.).
  - `Database/`: Connection management and migrations.
  - `Interpretations/`: Engines for reading astrological data.
- `pages/`: UI page components (Dashboard, Auth, Charts, Reports).
- `storage/`: Data directories for logs, cache, generated charts, and uploads.
- `tools/`: CLI utilities for maintenance and setup.
- `data/`: Static astrological data (ephemeris, cities, interpretations).

## 🧩 Key Conventions & Patterns

### PSR-4 Autoloading
All logic resides in the `classes/` directory under the `QuantumAstrology` namespace.
Use `require __DIR__ . '/classes/autoload.php';` in entry points if `vendor/autoload.php` isn't available.

### Database Strategy (Dual Support)
The system supports both MySQL (production) and SQLite (development/fallback).
- Use `QuantumAstrology\\Core\\DB::conn()` or `QuantumAstrology\\Database\\Connection::getInstance()` to get a PDO instance.
- Migrations must be idempotent and support both SQL dialects (MySQL/SQLite).

### Swiss Ephemeris Integration
Astrological calculations rely on the `swetest` CLI tool.
- Path defined by `SWEPH_PATH` in `.env`.
- `QuantumAstrology\\Charts\\SwissEphemeris` handles command execution.
- **Critical Gotcha**: House calculation output from `swetest` can be finicky. The system includes a heuristic to ensure ASC aligns with Cusp 1 and MC aligns with Cusp 10.

### Configuration
Use `QuantumAstrology\\Core\\Env::get($key, $default)` for environment variables.
Core defines are also available via `config.php`.

### Design System
Follow the **Quantum Minds United** aesthetic:
- **Colors**: Dark cosmic theme (`#0b0e14`), glassmorphism, gold accents.
- **CSS**: Located in `assets/css/quantum-dashboard.css`.
- **UI Components**: Cards, pills, and stats with specific gradients and blurs.

## ⚠️ Gotchas & Tips

1. **Authentication**: `index.php` (the portal) requires a user session. Use `/login` or `/register` to start.
2. **Precision**: When modifying chart calculations, always verify orbs and house cusp alignment.
3. **Paths**: Use absolute paths derived from `ROOT_PATH` or `__DIR__` to avoid issues with different entry points (root vs `api/`).
4. **SVG Generation**: `ChartWheel.php` generates SVG directly. Always test changes across different screen sizes as the chart is intended to be responsive.
5. **CSRF**: The application uses `$_SESSION['csrf_token']`. Verify it's present in AJAX requests to API endpoints like `chart_delete.php`.

## 🧪 Testing Patterns

- Unit tests reside in `tests/`.
- Use `php tools/test-chart-generation.php` for a quick end-to-end check of the calculation engine.
- Verify API health via `/api/health.php`.

## 🗺 Roadmap Reference
Consult `ROADMAP.md` and `TODO.md` for current development focus. Phase 2 (Advanced Features like Synastry and AI Interpretations) is currently in development.
