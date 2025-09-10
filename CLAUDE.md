# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Quantum Astrology is a professional astrology software suite built with PHP 8+ and vanilla JavaScript. It uses Swiss Ephemeris for precise astrological calculations and follows PSR-4 autoloading standards with the `QuantumAstrology\` namespace.

## Development Commands

- **Install dependencies**: `composer install`
- **Setup database**: `php tools/setup-database.php` (coming soon)
- **Generate test chart**: `php tools/test-chart-generation.php` (coming soon)
- **Clear cache**: `php tools/clear-cache.php` (coming soon)
- **Database backup**: `php tools/backup-database.php` (coming soon)
- **Run development server**: `php -S localhost:8080 index.php`
- **Syntax check**: `php test-syntax.php` (validates setup)

Note: Tool scripts will be created in upcoming development phases.

## Architecture

### Core Application Flow

Entry point is `index.php` which:
1. Loads configuration from `config.php`
2. Sets up PSR-4 autoloading for `QuantumAstrology\` namespace
3. Initializes `QuantumAstrology\Core\Application` class for API routing
4. For dashboard requests, renders the complete HTML interface directly
5. Handles API endpoints through the Application router
6. Serves static assets (CSS, JS, images) via Application class

### Directory Structure

- `classes/` - PSR-4 autoloaded PHP classes (Core, Charts, Database, Interpretations, Reports)
  - `Core/` - Application.php, Logger.php (✅ implemented)
  - `Database/` - Connection.php (✅ implemented)
- `pages/` - UI components organized by feature (charts, dashboard, forecasting, reports, timing)
  - Directory structure created, awaiting page implementations
- `assets/` - Frontend assets organized by type
  - `css/` - quantum-dashboard.css (✅ implemented with complete quantum design system)
  - `js/` - JavaScript modules (coming soon)
  - `images/`, `fonts/` - Static assets (coming soon)
- `storage/` - Generated content directories (✅ created)
  - `charts/`, `reports/`, `logs/`, `cache/`, `audio/`
- `data/` - Static data (cities, ephemeris, interpretations) - coming soon

### Key Components (Current Implementation)

- **Dashboard Interface**: ✅ Complete quantum-themed dashboard with:
  - Glassmorphism cards and particle animation background
  - Responsive grid layout for chart management
  - Interactive carousels for featured charts
  - Professional stats display and quick actions
- **Routing System**: ✅ Application class handles:
  - Dashboard rendering (index.php)
  - API endpoint routing (/api/*)
  - Static asset serving (/assets/*)
  - 404 handling with appropriate responses
- **Configuration**: ✅ Environment-based config system
- **Error Handling**: ✅ Comprehensive logging and error display
- **CSS Architecture**: ✅ Modular quantum design system

### Components (Coming Soon)

- **Chart calculations**: Swiss Ephemeris PHP library integration
- **Database**: MySQL with JSON columns for astrological data
- **SVG Charts**: Interactive chart wheels and aspects
- **Reports**: HTML/PDF generation with QMU branding

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

### Updated Integration Method

Use the PHP Swiss Ephemeris library from https://github.com/arturania/swisseph for direct PHP integration:

```php
use SwissEph\SwissEph;

$swisseph = new SwissEph();
$positions = $swisseph->calc_ut($julian_day, $planet, $flags);
```

This provides better performance than command-line interface calls and integrates cleanly with the existing PHP architecture.

### Fallback Command-Line Interface

If PHP library is not available, use command-line interface:

```php
$command = "swetest -b{$jd} -p{$planets} -f{$format} -sid{$sidereal}";
$positions = shell_exec($command);
```

### Caching Strategy

Cache results using MD5 hash of birth data + chart type + house system to avoid recalculation of identical charts.

## API Design

RESTful endpoints follow this pattern:

- `POST /api/charts/natal` - Generate natal chart
- `GET /api/charts/{id}` - Retrieve chart data  
- `POST /api/transits/calculate` - Calculate transit aspects
- `GET /api/users/{id}/charts` - List user's charts
- `POST /api/reports/generate` - Create detailed report

### API Response Format

```json
{
    "status": "success",
    "data": {
        "chart_id": "12345",
        "planets": {...},
        "houses": {...},
        "aspects": [...]
    },
    "meta": {
        "calculation_time": "234ms",
        "cached": false
    }
}
```

## Swiss Ephemeris Implementation Priority

1. **First Choice**: Integrate PHP Swiss Ephemeris library from arturania/swisseph repository
2. **Second Choice**: Use command-line swetest interface if PHP library unavailable
3. **Fallback**: Built-in analytical ephemeris (lower precision but no external dependencies)

## Quantum Design System Integration

### Color Scheme

```css
:root {
    --quantum-primary: #4A90E2;
    --quantum-gold: #FFD700;
    --quantum-dark: #1a1a2e;
    --quantum-darker: #070709;
    --quantum-text: #ffffff;
    --quantum-blue: #00d4ff;
    --quantum-purple: #8b5cf6;
}
```

### Component Patterns

- **Quantum Cards**: Glassmorphism effect with backdrop-filter blur
- **Featured Items**: Golden gradient badges for newest content
- **Carousel Navigation**: Horizontal scrolling with snap-to-card behavior
- **Particle Effects**: Floating cosmic particles in background
- **Hover States**: Smooth transform animations on interaction

### UI Consistency

Match the existing QMU ecosystem design patterns:
- Same carousel styling as video/audio/book libraries
- Consistent navigation and breadcrumb patterns
- Golden accent cards for featured content
- Dark cosmic theme with particle effects

## Environment Requirements

- **PHP**: 8.0+ with PDO, JSON extensions
- **Database**: MySQL 5.7+ or MariaDB 10.3+
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **Swiss Ephemeris**: PHP library preferred, command-line tools as fallback
- **Composer**: For dependency management and autoloading

## Development Priorities

### ✅ Phase 0 (Foundation Complete)
1. Core application infrastructure with PSR-4 autoloading
2. Professional quantum-themed dashboard interface
3. Responsive CSS architecture with particle effects
4. Basic routing system for pages and API endpoints
5. Configuration and error handling systems

### 🔄 Phase 1 (Core Calculations - Current Focus)
1. Swiss Ephemeris PHP integration
2. Database schema implementation
3. Basic natal chart calculation engine
4. User authentication system
5. SVG chart wheel generation

### 📋 Phase 2 (Professional Features)
1. Transit calculations with interactive timeline
2. Multiple house system support (Placidus, Whole Sign, Equal, Koch)
3. Aspect calculation engine with configurable orbs
4. Modular interpretation system
5. PDF report generation with QMU branding

### 🚀 Phase 3 (Advanced Features)
1. Secondary progressions and solar returns
2. Synastry and composite chart analysis
3. Advanced interpretation AI integration
4. Audio report narration with voice synthesis
5. Mobile app development and API expansion

## Security Considerations

- **Birth Data Protection**: Encrypt sensitive personal information
- **Input Validation**: Sanitize all user inputs, especially coordinates and dates
- **SQL Injection Prevention**: Use parameterized queries exclusively
- **Rate Limiting**: Implement API rate limiting for calculation endpoints
- **Authentication**: Secure user sessions and password storage

## Performance Optimization

- **Calculation Caching**: Cache Swiss Ephemeris results for identical requests
- **Database Indexing**: Index frequently queried columns (user_id, chart_type, birth_date)
- **SVG Optimization**: Minimize chart SVG file sizes
- **Asset Compression**: Compress CSS/JS assets for faster loading
- **Query Optimization**: Use efficient database queries with proper joins

## Testing Strategy

- **Chart Accuracy**: Verify calculations against known reference charts
- **Edge Cases**: Test leap years, DST transitions, extreme coordinates
- **Performance**: Benchmark calculation times and database queries
- **Cross-browser**: Ensure SVG charts render correctly across browsers
- **Mobile Responsiveness**: Test on various device sizes
