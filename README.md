[![PHP Composer](https://github.com/meistro57/quantum-astrology/actions/workflows/php.yml/badge.svg)](https://github.com/meistro57/quantum-astrology/actions/workflows/php.yml)
## IMPORTANT NOTE... THIS PROJECT IS VERY YOUNG AND IT HAS A WAYZ TO GO. 

# Quantum Astrology <img width="133" height="172" alt="image" src="https://github.com/user-attachments/assets/bf5c3fdb-5e4d-4d28-8a65-b7232a3583ae" />

Professional astrology software suite with Quantum Minds United branding. Swiss Ephemeris–powered chart calculations with clean, responsive SVG chart wheels.

<img width="906" height="1602" alt="image" src="https://github.com/user-attachments/assets/74b635dc-6f2e-47e4-93f5-2683c6d385ff" />

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
- **Profile Enhancements** — saved birth data, city/state coordinate auto-fill, and in-profile password change

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
php -S localhost:8080 index.php
```

### Environment Variables

Consult [`docs/environment.md`](docs/environment.md) for a complete catalogue of supported environment variables, including
database credentials, Swiss Ephemeris paths, caching controls, and session tuning. The guide outlines defaults, production
considerations, and a sample `.env` layout to accelerate both local prototyping and secure deployments.

### Maintenance Utilities

- `php tools/clear-cache.php` — clears the application cache using the shared storage maintenance routines.
- `php tools/manage-storage.php --list` — inspects cache, ephemeris, chart, and report directories with a friendly audit summary.
- `php tools/manage-storage.php --purge --target=ephemeris --older-than=30` — prunes cached ephemeris files older than 30 days, keeping recent calculations intact.
