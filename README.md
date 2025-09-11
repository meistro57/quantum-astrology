[![PHP Composer](https://github.com/meistro57/quantum-astrology/actions/workflows/php.yml/badge.svg)](https://github.com/meistro57/quantum-astrology/actions/workflows/php.yml)


# Quantum Astrology <img width="133" height="172" alt="image" src="https://github.com/user-attachments/assets/bf5c3fdb-5e4d-4d28-8a65-b7232a3583ae" />

Professional astrology software suite with Quantum Minds United branding. Swiss Ephemeris-powered chart calculations with beautiful, responsive interface.
<img width="906" height="1602" alt="image" src="https://github.com/user-attachments/assets/74b635dc-6f2e-47e4-93f5-2683c6d385ff" />

## Overview

Quantum Astrology provides professional-grade astrological calculations and chart generation with a modern, intuitive interface. Built with Swiss Ephemeris for astronomical accuracy and designed to integrate seamlessly with the Quantum Minds United ecosystem.

## Features

### ✅ **Current Features**
- **Swiss Ephemeris Integration** - Professional astronomical calculations with command-line and analytical fallback
- **Natal Chart Generation** - Complete birth chart calculations with planetary positions and houses
- **Multiple House Systems** - Placidus, Koch, Equal, Whole Sign, Campanus, Regiomontanus, and more
- **SVG Chart Wheels** - Professional astrological chart visualization with astronomical symbols
- **Chart Management** - Create, view, edit, and organize personal chart libraries
- **User Authentication** - Secure registration, login, and profile management
- **Public/Private Sharing** - Chart sharing with proper access control
- **Aspect Calculations** - Major aspects with configurable orbs and aspect detection
- **Interactive UI** - Responsive interface optimized for astrological data
- **Database Migrations** - Professional schema management with rollback support

### 📋 **Planned Features**
- **Transit Analysis** - Real-time planetary movement tracking and forecasting
- **Progressions & Returns** - Secondary progressions, solar returns, lunar returns
- **Synastry & Composite** - Relationship compatibility analysis
- **Professional Reports** - Detailed PDF reports with QMU branding
- **Advanced Chart Types** - Transit, progressed, composite, and harmonic charts
- **Interpretation System** - AI-powered chart readings and aspect analysis

## Technology Stack

- **Backend**: PHP 8+ with PSR-4 autoloading
- **Database**: MySQL 8+ with JSON column support
- **Frontend**: Vanilla JavaScript with Quantum UI framework
- **Charts**: SVG generation with interactive overlays
- **Calculations**: Swiss Ephemeris for astronomical precision
- **Reports**: HTML/PDF generation with optional voice synthesis

## Quick Start

### Prerequisites

- PHP 8.0 or higher
- MySQL 5.7+ or MariaDB 10.3+
- Apache 2.4+ or Nginx 1.18+
- Composer for dependency management

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/mestro57/quantum-astrology.git
   cd quantum-astrology
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Configure environment**
   ```bash
   cp .env.example .env
   # Edit .env with your database credentials and settings
   ```

4. **Setup database**
   ```bash
   # Run database migrations to create user tables
   php tools/migrate.php
   ```

5. **Configure web server**
   Point your web server document root to the project directory.

### Development Server

For development, you can use PHP's built-in server:

```bash
php -S localhost:8080 index.php
```

Visit `http://localhost:8080` to access the quantum astrology dashboard.

**First Time Setup**: 
1. Navigate to `http://localhost:8080/register` to create your first user account
2. Login at `http://localhost:8080/login` to access the protected dashboard
3. Create your first natal chart at `http://localhost:8080/charts/create`
4. View your chart library at `http://localhost:8080/charts`
5. Manage your profile at `http://localhost:8080/profile`

**Note**: The system includes complete Swiss Ephemeris integration with professional natal chart generation, visualization, and user authentication.

## Configuration

### Environment Variables

Configure the application by editing the `.env` file:

```env
# Database Configuration
DB_HOST=localhost
DB_NAME=quantum_astrology
DB_USER=your_username
DB_PASS=your_password

# Application Settings
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost

# Swiss Ephemeris Path
SWEPH_PATH=/usr/local/bin/swetest

# API Keys (optional)
ELEVENLABS_API_KEY=your_api_key_here
```

### Swiss Ephemeris Setup

The application requires Swiss Ephemeris for astronomical calculations:

1. Download Swiss Ephemeris from [Astrodienst](https://www.astro.com/swisseph/)
2. Install the command-line tools or PHP extension
3. Update `SWEPH_PATH` in your `.env` file

## Usage

### Creating Your First Natal Chart

1. **Register and Login** - Create your account and sign in to the system
2. **Navigate to Chart Creation** - Click "Create New Chart" or visit `/charts/create`
3. **Enter Birth Information**:
   - Chart name (e.g., "John's Natal Chart")
   - Birth date and exact time
   - Birth timezone
   - Birth location with latitude/longitude coordinates
4. **Select Chart Settings**:
   - Choose house system (Placidus, Koch, Equal, Whole Sign, etc.)
   - Set chart privacy (public or private)
5. **Generate Chart** - The system calculates planetary positions using Swiss Ephemeris
6. **View Results** - Interactive chart wheel with detailed planetary positions and aspects

### Chart Management

- **View All Charts** - Access your chart library at `/charts`
- **Chart Details** - Click any chart to view detailed information and SVG wheel
- **Edit Charts** - Modify chart names, privacy settings, and metadata
- **Share Charts** - Public charts can be viewed by other users
- **Delete Charts** - Remove charts from your library with confirmation

### API Usage

The application provides RESTful API endpoints:

```bash
# Get chart wheel SVG
curl -H "Cookie: PHPSESSID=your_session_id" \
  http://localhost:8080/api/charts/123/wheel

# Health check
curl http://localhost:8080/api/health
```

**Available Endpoints**:
- `GET /api/health` - System health check
- `GET /api/charts/{id}/wheel` - SVG chart wheel generation (requires authentication)
- Additional chart data endpoints coming in future updates

## Architecture

### Project Structure

```
quantum-astrology/
├── api/                    # REST API endpoints
├── assets/                 # Frontend assets (CSS, JS, images)
├── classes/                # Core PHP classes (PSR-4)
├── data/                   # Ephemeris data and interpretations
├── pages/                  # UI pages and components
├── storage/                # Generated charts and cache
├── tools/                  # Setup and maintenance scripts
└── docs/                   # Documentation
```

### Core Components

- **Swiss Ephemeris Integration**: Command-line swetest with analytical fallback for planetary calculations
- **Chart Calculation Engine**: Complete natal chart generation with planetary positions and house cusps
- **SVG Chart Wheels**: Professional astrological visualization with astronomical symbols
- **House Systems**: Support for Placidus, Koch, Equal, Whole Sign, Campanus, and more
- **Aspect Engine**: Major aspects with configurable orbs (conjunction, sextile, square, trine, opposition)
- **Chart Management**: Full CRUD operations with public/private sharing
- **User Authentication**: Complete session-based authentication with profile management
- **Database Architecture**: Professional schema with JSON columns for complex astrological data

## Development

### Code Standards

- PSR-4 autoloading with `QuantumAstrology\` namespace
- Strict typing enabled in all PHP files
- Comprehensive error handling and logging
- Input validation and sanitization
- PHPDoc documentation for all public methods

### Database Schema

The application uses a normalized database structure:

- **users**: User accounts with authentication and profile data
- **charts**: Complete chart storage with birth data, planetary positions, houses, and aspects
- **birth_profiles**: Reusable person profiles with location and timezone data
- **chart_sessions**: User interaction tracking and chart-specific preferences
- **migrations**: Database schema version tracking and rollback support

### Testing

Run the test suite to verify chart accuracy:

```bash
php tools/test-chart-generation.php
```

### Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes following the coding standards
4. Run tests to ensure accuracy
5. Submit a pull request

## Swiss Ephemeris Integration

This software uses the Swiss Ephemeris for astronomical calculations, developed by Astrodienst AG. The Swiss Ephemeris provides:

- High precision planetary positions
- Support for historical and future dates
- Multiple coordinate systems
- Asteroid and fixed star positions

For commercial use, a license may be required. See [Swiss Ephemeris License](https://www.astro.com/swisseph/swephinfo_e.htm) for details.

## Security

- All user input is validated and sanitized
- Birth data is encrypted in the database
- API endpoints include rate limiting
- SQL injection protection through parameterized queries
- XSS protection with output encoding

## Performance

- Chart calculations are cached for instant retrieval
- SVG charts are optimized for fast rendering
- Database queries are indexed for performance
- Static assets are compressed and cached

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Support

For questions, issues, or feature requests:

- Create an issue on GitHub
- Visit [Quantum Minds United](https://quantummindsunited.com)
- Contact: mark@quantummindsunited.com

## Acknowledgments

- Swiss Ephemeris by Astrodienst AG for astronomical calculations
- Quantum Minds United for design system and branding
- The astrology community for interpretation knowledge and testing

## Current Implementation Status

### ✅ Production Ready Core Features
- **Core Application Infrastructure**
  - PHP 8+ with PSR-4 autoloading under `QuantumAstrology\` namespace
  - Configuration system with environment variables and `.env` support
  - Professional dashboard with Quantum Design System
  - Responsive UI with glassmorphism effects and particle animations
  - Enhanced routing system for chart-specific URLs and API endpoints

- **Complete User Authentication System** 
  - User registration with username/email and secure password hashing
  - Login system supporting both username and email authentication
  - User profile management with timezone and personal information
  - Session management with secure HTTP-only cookies
  - Authentication middleware protecting dashboard and chart access

- **Swiss Ephemeris Integration & Chart System**
  - Command-line swetest integration with analytical calculation fallback
  - Support for all major planets, moon nodes, Chiron, and Lilith
  - Multiple house systems (Placidus, Koch, Equal, Whole Sign, Campanus, etc.)
  - Complete natal chart generation with planetary positions and house cusps
  - Major aspect calculations with configurable orbs and detection
  - Professional SVG chart wheel generation with astronomical symbols

- **Chart Management System**
  - Chart creation forms with comprehensive birth data validation
  - Chart viewing interface with detailed planetary information
  - Chart library with grid layout and management features
  - Public/private chart sharing with proper access control
  - Chart editing and deletion with user permission validation

- **Database Architecture & API**
  - Charts table with JSON columns for complex astrological data
  - Birth profiles and chart sessions tables for enhanced functionality
  - Migration system with version control and rollback support
  - Chart wheel SVG API endpoint with authentication and caching
  - Performance optimization with chart wheel caching system

### 🔄 Next Development Phase
- Transit calculations with real-time planetary movements
- Secondary progressions and solar return calculations
- Advanced interpretation system with AI integration
- Interactive transit timeline with date ranges
- Advanced aspect pattern recognition (Grand Trines, T-Squares, Yods)

### 📋 Future Professional Features
- Professional PDF report generation with QMU branding
- Modular interpretation system with customizable rules
- Chart comparison tools and synastry analysis
- Mobile app development with React Native
- Advanced API integrations and third-party services
- Multi-language support for international users
- Enterprise features for professional astrologers

---

Built with precision and passion for the astrological arts.





