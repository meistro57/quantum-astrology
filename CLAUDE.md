# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Quantum Astrology is a professional astrology software suite built with PHP 8+ and vanilla JavaScript. It uses Swiss Ephemeris for precise astrological calculations and follows PSR-4 autoloading standards with the `QuantumAstrology\` namespace.

## Development Commands

### Essential Setup Commands
- **Install dependencies**: `composer install`
- **Run database migrations**: `php tools/migrate.php` (creates all tables with migration tracking)
  - Supports both MySQL and SQLite databases
  - Automatically detects database type and uses appropriate SQL syntax
  - Idempotent: can be run multiple times safely
  - Tracks executed migrations to avoid duplicates
- **Run development server**: `php -S localhost:8080 index.php`
- **Syntax check**: `php test-syntax.php` (validates setup)

### User System Commands
- **Create first user account**: Navigate to `http://localhost:8080/register` in browser
- **Login to system**: Navigate to `http://localhost:8080/login` in browser
- **Access dashboard**: `http://localhost:8080/` (requires authentication)
- **Manage profile**: `http://localhost:8080/profile` (when logged in)

### Chart System Commands
- **Create natal chart**: Navigate to `http://localhost:8080/charts/create`
- **View chart library**: Navigate to `http://localhost:8080/charts`
- **View specific chart**: `http://localhost:8080/charts/view?id={chart_id}`
- **Chart wheel API**: `http://localhost:8080/api/charts/{id}/wheel` (SVG format)

### Development Tools (Coming Soon)
- **Setup database**: `php tools/setup-database.php`
- **Generate test chart**: `php tools/test-chart-generation.php`
- **Clear cache**: `php tools/clear-cache.php`
- **Database backup**: `php tools/backup-database.php`

## User System Setup

### First Time Setup

1. **Database Configuration**: Choose your database backend:

   **Option A: SQLite (Quick Setup for Development)**
   - Requires: `php-sqlite3` extension
   - Install: `sudo apt-get install php8.2-sqlite3`
   - No additional configuration needed - SQLite database will be auto-created at `./storage/database.sqlite`

   **Option B: MySQL (Recommended for Production)**
   - Ensure your `.env` file has correct database credentials:
   ```env
   DB_HOST=localhost
   DB_NAME=quantum_astrology
   DB_USER=your_username
   DB_PASS=your_password
   ```
   - Create the database and user:
   ```bash
   sudo mysql -u root
   CREATE DATABASE quantum_astrology;
   CREATE USER 'qauser'@'localhost' IDENTIFIED BY 'password';
   GRANT ALL ON quantum_astrology.* TO 'qauser'@'localhost';
   FLUSH PRIVILEGES;
   ```

2. **Run Migrations**: Initialize the database schema:
   ```bash
   php tools/migrate.php
   ```
   The migration tool will:
   - Auto-detect MySQL or fallback to SQLite
   - Create all required tables (users, charts, birth_profiles, chart_sessions)
   - Set up proper indexes and foreign keys
   - Track migrations to prevent duplicate execution

3. **Start Development Server**:
   ```bash
   php -S localhost:8080 index.php
   ```

4. **Create Admin Account**: Navigate to `/register` and create your first user account.

### Authentication System Features

- **Secure Registration**: Username, email, password with validation
- **Login System**: Supports login with either username or email
- **Profile Management**: Users can update personal information and timezone
- **Session Management**: Secure sessions with automatic logout
- **Route Protection**: Dashboard and sensitive areas require authentication
- **Password Security**: Passwords are hashed using PHP's `password_hash()`

### User Roles and Permissions

The system is designed for future role expansion:
- **Current**: All registered users have access to full system
- **Planned**: Admin, User, and Guest roles with different permissions
- **Future**: Chart sharing permissions and collaboration features

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

- **Swiss Ephemeris Integration**: ✅ Professional astrological calculations with:
  - Command-line swetest integration with analytical fallback
  - Support for all major planets, moon nodes, and asteroids (Chiron, Lilith)
  - Multiple house systems (Placidus, Koch, Equal, Whole Sign, Campanus, etc.)
  - Precise Julian day calculations and coordinate transformations
  - Comprehensive error handling and precision validation
  
- **Chart Calculation Engine**: ✅ Complete natal chart generation with:
  - Planetary position calculations with degrees and zodiac signs
  - House cusp calculations for 12 house systems
  - Major aspect calculation engine (conjunction, sextile, square, trine, opposition)
  - Aspect orb calculations with configurable tolerances
  - Chart data caching for performance optimization

- **Chart Visualization System**: ✅ Professional SVG chart wheels with:
  - Astronomical Unicode symbols for planets and signs
  - Interactive chart wheel generation with house divisions
  - Aspect lines with color-coded major aspects
  - Cached SVG generation served via API endpoints
  - Responsive chart display with zoom and interaction capabilities

- **Chart Management System**: ✅ Complete chart CRUD operations with:
  - Chart creation forms with comprehensive birth data validation
  - Chart viewing interface with detailed planetary positions
  - Chart library with grid layout and search functionality
  - Public/private chart sharing with access control
  - Chart editing and deletion with user permissions

- **Database Architecture**: ✅ Professional astrological database with:
  - Dual database support: MySQL (production) and SQLite (development)
  - Automatic database detection and appropriate SQL syntax generation
  - Charts table with JSON/TEXT columns for complex astrological data
  - Birth profiles table for reusable person data
  - Chart sessions table for user interaction tracking
  - Robust migration system (`tools/migrate.php`) with:
    - Migration tracking and batch management
    - Idempotent execution (safe to run multiple times)
    - Database-specific SQL generation
    - Foreign key support on both MySQL and SQLite
  - Proper indexing on frequently queried astrological data

- **User Authentication System**: ✅ Complete user management with:
  - Secure registration and login functionality
  - Password hashing and session management
  - User profile management and settings
  - Authentication middleware for route protection
  - Role-based access control ready for expansion

- **Professional UI System**: ✅ Quantum-themed interface with:
  - Chart creation forms with timezone and coordinate inputs
  - Interactive chart viewing with planetary position displays
  - Chart management dashboard with visual previews
  - Responsive design optimized for astrological data
  - Glassmorphism effects and particle animations

- **API Infrastructure**: ✅ RESTful endpoints with:
  - Chart wheel SVG generation at `/api/charts/{id}/wheel`
  - Chart data export and import capabilities
  - Authentication-based access control
  - Proper HTTP status codes and error handling
  - Caching headers for performance optimization

- **Configuration & Error Handling**: ✅ Production-ready systems with:
  - Environment-based configuration with Swiss Ephemeris paths
  - Comprehensive logging for astrological calculations
  - Error fallbacks for missing Swiss Ephemeris installation
  - Debug modes for development and calculation verification

### Components (Coming Soon)

- **Advanced Chart Types**: Transit charts, progressions, solar returns, composites
- **Interpretation System**: AI-powered chart readings and aspect interpretations
- **Report Generation**: Professional PDF reports with QMU branding
- **Audio Synthesis**: Voice narration of chart interpretations
- **Advanced Visualization**: Interactive chart wheels with zoom and aspect overlays
- **Chart Comparison**: Synastry analysis and relationship compatibility
- **Timing Analysis**: Transit tracking and forecasting tools

## Code Standards

### PHP Requirements

- PHP 8.0+ with strict typing: `declare(strict_types=1)`
- PSR-4 autoloading under `QuantumAstrology\` namespace
- Error handling with try-catch for external calls (especially Swiss Ephemeris)
- Input validation and sanitization for all user data
- PHPDoc documentation for public methods

### Database Conventions

- Table names in snake_case
- JSON columns (MySQL) or TEXT columns (SQLite) for complex astrological structures (planet positions, aspects, interpretations)
- Foreign key constraints properly defined (both MySQL and SQLite)
- Indexes on frequently queried columns
- Sequential numbered migration files in `classes/Database/Migrations/`
- Each migration class must:
  - Detect database driver using `PDO::ATTR_DRIVER_NAME`
  - Provide database-specific SQL for both MySQL and SQLite
  - Implement both `up()` and `down()` methods
  - Use `Connection::getInstance()` to get PDO instance

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
- **Database**:
  - MySQL 5.7+ or MariaDB 10.3+ with `pdo_mysql` extension (recommended for production)
  - SQLite 3.0+ with `pdo_sqlite` extension (supported for development)
  - System automatically detects and adapts to available database
- **Web Server**: Apache 2.4+ or Nginx 1.18+ (or PHP built-in server for development)
- **Swiss Ephemeris**: PHP library preferred, command-line tools as fallback
- **Composer**: For dependency management and autoloading

## Development Priorities

### ✅ Phase 0 (Foundation Complete)
1. Core application infrastructure with PSR-4 autoloading
2. Professional quantum-themed dashboard interface
3. Responsive CSS architecture with particle effects
4. Basic routing system for pages and API endpoints
5. Configuration and error handling systems
6. Complete user authentication system with registration, login, and profile management
7. Session management and authentication middleware
8. Robust database migration system with dual MySQL/SQLite support

### ✅ Phase 1.2 (Swiss Ephemeris Integration Complete)
1. Swiss Ephemeris integration with command-line and analytical fallback
2. Complete chart database schema with JSON columns for complex astrological data
3. Professional natal chart calculation engine with planetary positions and houses
4. SVG chart wheel generation with astronomical symbols and aspect lines
5. Chart storage and management system with CRUD operations
6. Chart creation UI with comprehensive birth data forms
7. Chart viewing interface with detailed planetary information
8. Chart library management with public/private sharing
9. API endpoints for chart wheel generation and data export
10. Caching system for chart wheel performance optimization

### 🔄 Phase 2 (Advanced Features - Next Focus)
1. Transit calculations with real-time planetary movements
2. Secondary progressions and solar return calculations
3. Synastry and composite chart analysis
4. Advanced interpretation system with AI integration
5. Professional PDF report generation with QMU branding

### 📋 Phase 3 (Professional Features)
1. Interactive transit timeline with date ranges
2. Advanced aspect pattern recognition (Grand Trines, T-Squares, Yods)
3. Modular interpretation system with customizable rules
4. Professional chart comparison tools
5. Advanced chart filtering and search capabilities

### 🚀 Phase 4 (Advanced Features)
1. Mobile app development with React Native
2. Advanced API integrations and third-party services
3. Multi-language support for international users
4. Advanced chart sharing and collaboration features
5. Enterprise features for professional astrologers

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
