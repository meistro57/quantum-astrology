# Changelog

All notable changes to the Quantum Astrology project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0-alpha] - 2025-09-10

### Added
- **Core Application Infrastructure**
  - PHP 8+ application with PSR-4 autoloading under `QuantumAstrology\` namespace
  - Configuration system with environment variables (`.env` support)
  - Basic routing system for dashboard, API endpoints, and static assets
  - Comprehensive error handling and logging infrastructure
  
- **Professional Dashboard Interface**
  - Complete quantum-themed dashboard with Quantum Minds United branding
  - Glassmorphism design with backdrop-filter blur effects
  - Animated particle background with floating cosmic elements
  - Responsive grid layout optimized for desktop and mobile
  - Interactive carousels for featured charts with smooth scrolling
  - Professional statistics display and quick action buttons
  
- **CSS Architecture**
  - Modular CSS extracted to `/assets/css/quantum-dashboard.css`
  - Complete Quantum Design System with CSS custom properties
  - Responsive design with mobile-first approach
  - Smooth animations and hover effects throughout interface
  - Cross-browser compatible styling with fallbacks
  
- **Directory Structure**
  - Organized project structure following PHP best practices
  - Created directories for `pages/`, `storage/`, `classes/`, `assets/`
  - Proper separation of concerns between frontend and backend code
  
- **Development Tools**
  - `test-syntax.php` for validating application setup
  - Development server configuration for PHP built-in server
  - Environment configuration with `.env.example` template

### Technical Details
- **Entry Point**: `index.php` handles both dashboard rendering and API routing
- **Application Class**: Smart routing between dashboard, API, and asset requests
- **Error Handling**: Debug-aware error display with styled error containers
- **Asset Serving**: Built-in static file serving with proper MIME types
- **Performance**: Optimized CSS with efficient selectors and minimal reflows

### Documentation
- Comprehensive `README.md` with installation and usage instructions
- Detailed `CLAUDE.md` with development guidelines and project overview
- Step-by-step `INSTALL.md` with troubleshooting and configuration
- Environment configuration examples and development setup guides

## [1.1.0-alpha] - 2024-12-XX - User Authentication System

### Added
- **Complete User Authentication System**
  - User registration with username and email support
  - Secure login system with password hashing using `password_hash()`
  - User profile management with timezone and personal information settings
  - Session management with secure HTTP-only cookies
  - Authentication middleware for route protection (`Auth::requireLogin()`)
  - Flash message system for user feedback across page redirects

- **Database Infrastructure**
  - Database migration system for schema version control
  - Users table with proper indexing on username, email, and created_at
  - Migration runner script (`php tools/migrate.php`)
  - Database connection class with PDO and error handling
  - Migrations tracking table to prevent duplicate migrations

- **Authentication Pages**
  - Quantum-themed login page at `/login` with particle effects
  - Registration page at `/register` with comprehensive form validation
  - User profile management page at `/profile` for account settings
  - Automatic logout functionality at `/logout` with session cleanup

- **Security Features**
  - Password security with PHP's `PASSWORD_DEFAULT` algorithm
  - Session regeneration on successful login to prevent session fixation
  - Secure cookie configuration with SameSite and HttpOnly flags
  - HTTPS detection for secure cookie handling
  - Input validation and sanitization for all user inputs
  - Protection against SQL injection through parameterized queries

- **User Interface Enhancements**
  - User menu in navigation showing welcome message with username
  - Logout link prominently displayed in navigation
  - Dashboard now requires authentication to access
  - Responsive authentication forms matching quantum design system

### Changed
- **Routing System**: Enhanced Application class to handle authentication routes
- **Dashboard Access**: Main dashboard now requires user login
- **Navigation**: Updated header to include user information and authentication controls
- **Configuration**: Added session security settings and HTTPS detection
- **Index Page**: Added authentication checks and session initialization

### Technical Implementation
- **New Classes Added**:
  - `QuantumAstrology\Core\User` - Complete user model with CRUD operations
  - `QuantumAstrology\Core\Session` - Session management and flash messaging
  - `QuantumAstrology\Core\Auth` - Authentication helpers and middleware
  - `QuantumAstrology\Database\Migrator` - Database migration system
  - `QuantumAstrology\Database\Migrations\CreateUsersTable` - Users table schema

- **Database Schema**:
  - Users table: id, username, email, password_hash, first_name, last_name, timezone
  - Proper timestamps: email_verified_at, last_login_at, created_at, updated_at
  - Unique constraints on username and email fields
  - Indexes for performance on frequently queried fields

- **Security Measures**:
  - All passwords hashed before storage, never stored in plain text
  - Sessions use HTTP-only cookies to prevent XSS attacks
  - Proper SameSite cookie configuration for CSRF protection
  - Input validation prevents SQL injection and data corruption

## [Unreleased]

## [1.3.0-alpha] - 2025-12-19 - Transit UI & SQLite Reliability

### Added
- **Functional Transit Analysis UI**
  - Integrated `TransitService` with a new interactive frontend at `/charts/transits`.
  - Supports chart selection, custom date/time picking, and real-time computation of transiting planets.
  - Displays cross-aspects (natal vs transit) with calculated orbs and house placements.
- **Enhanced SVG Chart Wheel**
  - Added color-coded zodiac wedges for improved visual readability.
  - Implemented dynamic arc paths for all 12 signs in the base wheel.
- **Report Generation Scaffolding**
  - Initial `ReportGenerator` class utilizing `mPDF` for PDF exports.
  - Support for basic natal report templates with calculation metadata.
- **Infrastructure & Stability Scripts**
  - `install-and-test.sh`: Automated dependency installer, composer bootstrapper, and migration runner.
  - `start_server.sh`: Robust server launcher with `.env` port detection and `0.0.0.0` binding for better accessibility.

### Fixed
- **Database Connection Reliability**
  - Resolved connection hang by refactoring core `DB::conn()` to use the unified `Connection` manager.
  - Ensured immediate SQLite fallback when MySQL drivers or servers are unavailable.
- **User Authentication Logic**
  - Fixed registration failure in SQLite environments by replacing MySQL-specific `NOW()` with portable `CURRENT_TIMESTAMP`.
- **Routing & Server Looping**
  - Resolved infinite redirect loop in `index.php` by correctly bootstrapping the `Application` router.
  - Fixed asset serving conflicts between the built-in PHP server and the internal router.

### Changed
- Refactored `index.php` to serve as a clean entry point delegating to the `Core\Application` class.
- Updated `AGENTS.md` with comprehensive setup and maintenance instructions.
- Simplified core database access patterns to ensure architectural consistency.

## [1.2.1-alpha] - 2025-10-29 - Database Migration System Enhancement

### Added
- **Dual Database Support**
  - Complete MySQL and SQLite database compatibility
  - Automatic database type detection and SQL syntax adaptation
  - SQLite fallback when MySQL is unavailable for quick development setup
  - Database-specific migration SQL generation for both MySQL and SQLite

- **Enhanced Migration System**
  - Robust migration tracking with batch management
  - Idempotent migration execution (safe to run multiple times)
  - Comprehensive migration status reporting
  - Migration file auto-discovery from `classes/Database/Migrations/`
  - Database connection error handling with helpful setup instructions

- **Migration Tool Improvements (`tools/migrate.php`)**
  - Database configuration display at startup
  - Detailed error messages with troubleshooting guidance
  - Migration execution summary with counts and batch numbers
  - Support for foreign keys on both MySQL and SQLite
  - Separate index creation for SQLite compatibility

### Changed
- **Migration Files Updated** for dual database support:
  - `001_create_users_table.php` - Now supports both MySQL and SQLite
  - `002_create_charts_table.php` - Database-aware SQL generation
  - `003_create_birth_profiles_table.php` - Cross-database compatibility
  - `004_create_chart_sessions_table.php` - Unified schema across databases

- **Database Connection Strategy**:
  - Enhanced `tools/migrate.php` with MySQL primary, SQLite fallback
  - Improved error messages guide users through database setup
  - Clear instructions for both MySQL and SQLite configuration

### Technical Implementation
- **Database Abstraction**: Each migration detects driver using `PDO::ATTR_DRIVER_NAME`
- **SQLite Enhancements**:
  - Proper `PRAGMA foreign_keys = ON` for referential integrity
  - TEXT columns instead of JSON for data storage
  - INTEGER instead of BOOLEAN for compatibility
  - Separate CREATE INDEX statements for proper indexing

- **MySQL Optimizations**:
  - Native JSON column support for complex data structures
  - InnoDB engine with proper foreign key constraints
  - Inline index creation within table definitions
  - TIMESTAMP with ON UPDATE CURRENT_TIMESTAMP support

### Documentation
- **Updated `CLAUDE.md`** with comprehensive database setup instructions
  - Dual database configuration examples
  - SQLite installation guide for PHP 8.2+
  - MySQL user creation and privilege setup
  - Migration system architecture documentation
  - Database conventions for future migrations

### Developer Experience
- **Improved Setup Flow**:
  - One-command database initialization: `php tools/migrate.php`
  - Automatic SQLite database file creation in `./storage/`
  - Clear error messages with actionable next steps
  - No manual database setup required for SQLite development

- **Migration Best Practices**:
  - Each migration class must implement database detection
  - Provide SQL for both MySQL and SQLite in all new migrations
  - Test migrations on both database types before commit
  - Use `Connection::getInstance()` for PDO access

## [Unreleased]

### Planned for v1.3 (Phase 3 - Professional Features)
- Interactive transit timeline with date ranges
- Advanced aspect pattern recognition (Grand Trines, T-Squares, Yods)
- Modular interpretation system with customizable rules
- Professional chart comparison tools
- Advanced chart filtering and search capabilities

### Planned for v1.4+ (Phase 4 - Advanced Features)
- Mobile app development with React Native
- Advanced API integrations and third-party services
- Multi-language support for international users
- Advanced chart sharing and collaboration features
- Enterprise features for professional astrologers

## Development Notes

### Architecture Decisions
- **Single Entry Point**: `index.php` serves both dashboard and handles routing
- **No Framework Dependency**: Pure PHP with minimal external dependencies
- **Modular CSS**: Separated styling for easier maintenance and theming
- **Environment-Based Config**: Flexible configuration for different deployment scenarios

### Current Status
- ✅ **Foundation Complete**: Professional interface and core infrastructure ready
- ✅ **Database System**: Dual MySQL/SQLite support with robust migration system
- ✅ **Swiss Ephemeris Integration Complete**: Full chart calculation and visualization system
- 🔄 **Advanced Features In Development**: Transit calculations and professional features
- 📋 **Documentation**: Comprehensive guides for installation and development

---

*Built with precision and passion for the astrological arts.*