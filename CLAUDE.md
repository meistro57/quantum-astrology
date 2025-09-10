# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Quantum Astrology is a professional astrology software suite built with PHP 8+ and vanilla JavaScript. It uses Swiss Ephemeris for precise astrological calculations and follows PSR-4 autoloading standards with the `QuantumAstrology\` namespace.

## Development Commands

- **Install dependencies**: `composer install`
- **Setup database**: `php tools/setup-database.php`
- **Generate test chart**: `php tools/test-chart-generation.php`
- **Clear cache**: `php tools/clear-cache.php`
- **Database backup**: `php tools/backup-database.php`
- **Run development server**: Start Apache/Nginx pointing to project root

Note: Many tool scripts referenced in documentation may not yet exist - create them as needed.

## Architecture

### Core Application Flow
Entry point is `index.php` which loads the `QuantumAstrology\Core\Application` class. The application uses a simple router that handles requests and includes appropriate page files.

### Directory Structure
- `classes/` - PSR-4 autoloaded PHP classes (Core, Charts, Database, Interpretations, Reports)
- `api/` - REST API endpoints for calculations, interpretations, and reports
- `pages/` - UI components organized by feature (charts, dashboard, forecasting, reports, timing)
- `assets/` - Frontend assets (CSS with quantum theme, JS modules, images, fonts)
- `data/` - Static data (cities, ephemeris, interpretations by category, report templates)
- `storage/` - Generated content (audio, cache, charts, logs, reports)

### Key Components
- **Chart calculations**: Uses Swiss Ephemeris via command-line interface for precision
- **Database**: MySQL with JSON columns for complex astrological data structures
- **Frontend**: Quantum UI framework with dark cosmic theme and particle effects
- **Reports**: HTML/PDF generation with QMU branding integration

## Code Standards

### PHP Requirements
- PHP 8.0+ with strict typing: `declare(strict_types=1)`
- PSR-4 autoloading under `QuantumAstrology\` namespace
- Error handling with try-catch for external calls (especially Swiss Ephemeris)
- Input validation and sanitization for all user data
- PHPDoc documentation for public methods

### Database Conventions
- Table names in snake_case
- JSON columns for complex astrological structures (planet positions, aspects, interpretations)
- Foreign key constraints properly defined
- Indexes on frequently queried columns
- Sequential numbered migration files in `classes/Database/Migrations/`

### Frontend Standards
- Quantum Design System matching existing QMU aesthetic
- Responsive, mobile-first design
- Particle effects and smooth animations
- SVG generation for astrological chart wheels

## Swiss Ephemeris Integration

Use command-line interface for calculations:
```php
$command = "swetest -b{$jd} -p{$planets} -f{$format} -sid{$sidereal}";
$positions = shell_exec($command);
```

Cache results using MD5 hash of birth data + chart type + house system.

## API Design

RESTful endpoints follow this pattern:
- `POST /api/charts/natal` - Generate natal chart
- `GET /api/charts/{id}` - Retrieve chart data  
- `POST /api/transits/calculate` - Calculate transit aspects
- `GET /api/users/{id}/charts` - List user's charts
- `POST /api/reports/generate` - Create detailed report

## Environment Requirements

- **PHP**: 8.0+ with PDO, JSON extensions
- **Database**: MySQL 5.7+ or MariaDB 10.3+
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **Swiss Ephemeris**: Command-line tools or PHP extension