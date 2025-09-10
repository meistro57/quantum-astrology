<img width="799" height="1031" alt="image" src="https://github.com/user-attachments/assets/bf5c3fdb-5e4d-4d28-8a65-b7232a3583ae" />

# Quantum Astrology

Professional astrology software suite with Quantum Minds United branding. Swiss Ephemeris-powered chart calculations with beautiful, responsive interface.

## Overview

Quantum Astrology provides professional-grade astrological calculations and chart generation with a modern, intuitive interface. Built with Swiss Ephemeris for astronomical accuracy and designed to integrate seamlessly with the Quantum Minds United ecosystem.

## Features

- **Natal Chart Generation** - Precise birth chart calculations with multiple house systems
- **Transit Analysis** - Real-time planetary movement tracking and forecasting
- **Progressions & Returns** - Secondary progressions, solar returns, lunar returns
- **Synastry & Composite** - Relationship compatibility analysis
- **Professional Reports** - Detailed PDF reports with optional AI narration
- **Interactive Charts** - Beautiful SVG chart wheels with zoom and interaction
- **Multiple Chart Types** - Natal, transit, progressed, harmonic, and electional charts
- **Advanced Techniques** - Arabic parts, fixed stars, midpoints, and harmonics

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
   php tools/setup-database.php
   ```

5. **Configure web server**
   Point your web server document root to the project directory.

### Development Server

For development, you can use PHP's built-in server:

```bash
php -S localhost:8000
```

Visit `http://localhost:8000` to access the application.

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

### Basic Chart Generation

1. Navigate to the dashboard
2. Click "Create New Chart"
3. Enter birth data (date, time, location)
4. Select chart type and house system
5. Generate and view your chart

### API Usage

The application provides RESTful API endpoints:

```bash
# Generate natal chart
curl -X POST /api/charts/natal \
  -H "Content-Type: application/json" \
  -d '{"birth_date":"1990-01-01","birth_time":"12:00","latitude":40.7128,"longitude":-74.0060}'

# Calculate transits
curl -X POST /api/transits/calculate \
  -H "Content-Type: application/json" \
  -d '{"chart_id":"12345","date":"2024-01-01"}'
```

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

- **Chart Calculator**: Swiss Ephemeris integration for planetary positions
- **House Systems**: Support for Placidus, Whole Sign, Equal, Koch, and others
- **Aspect Engine**: Configurable orbs and aspect patterns
- **Interpretation System**: Modular interpretation database
- **Report Generator**: PDF and HTML report creation
- **User Management**: Authentication and chart storage

## Development

### Code Standards

- PSR-4 autoloading with `QuantumAstrology\` namespace
- Strict typing enabled in all PHP files
- Comprehensive error handling and logging
- Input validation and sanitization
- PHPDoc documentation for all public methods

### Database Schema

The application uses a normalized database structure:

- **astro_users**: User accounts and preferences
- **birth_profiles**: Birth data for individuals
- **calculated_charts**: Cached chart calculations
- **chart_sessions**: User interaction tracking

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

## Roadmap

### Version 1.0 (Current)
- ✅ Core infrastructure
- ✅ Database schema
- ✅ Basic chart calculation
- 🔄 User interface implementation
- 🔄 Swiss Ephemeris integration

### Version 1.1 (Planned)
- Transit calculations and timelines
- Secondary progressions
- Solar and lunar returns
- PDF report generation

### Version 1.2 (Future)
- Synastry and composite charts
- Advanced interpretation engine
- Mobile app development
- AI-powered insights

---

Built with precision and passion for the astrological arts.

