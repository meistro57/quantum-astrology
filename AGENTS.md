# Quantum Astrology

Professional astrology software suite with Quantum Minds United branding. Swiss Ephemeris-powered chart calculations with beautiful, responsive UI matching the existing QMU ecosystem.

## Project Structure

```
quantum-astrology/
├── assets/                    # Frontend assets (CSS, JS, images, fonts)
├── api/                      # REST API endpoints for chart calculations
├── classes/                  # Core PHP classes (PSR-4 autoloaded)
├── pages/                    # UI pages and components
├── data/                     # Ephemeris data, interpretations, templates
├── storage/                  # Generated charts, reports, cache, logs
├── tools/                    # Setup and maintenance scripts
└── docs/                     # Documentation
```

## Core Commands

- **Setup database**: `php tools/setup-database.php`
- **Install dependencies**: `composer install`
- **Run development server**: Start Apache/Nginx pointing to project root
- **Generate test chart**: `php tools/test-chart-generation.php`
- **Clear cache**: `php tools/clear-cache.php`
- **Database backup**: `php tools/backup-database.php`

## Development Environment

- **PHP**: 8.0+ required (with PDO, JSON extensions)
- **Database**: MySQL 5.7+ or MariaDB 10.3+
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **Swiss Ephemeris**: Command-line tools or PHP extension
- **Composer**: For dependency management
- **phpMyAdmin**: For database administration

## Technology Stack

- **Backend**: PHP 8+ with PSR-4 autoloading
- **Database**: MySQL with JSON column support
- **Frontend**: Vanilla JavaScript + Quantum UI framework
- **Charts**: SVG generation for astrological wheels
- **Reports**: HTML/PDF generation with optional AI narration
- **APIs**: RESTful endpoints for all calculations

## Architecture Principles

### Chart Calculation Flow
1. User submits birth data → validation
2. Timezone/DST resolution → coordinate lookup
3. Swiss Ephemeris calculation → planet positions
4. House system calculation → chart structure
5. Aspect calculation → angular relationships
6. SVG chart generation → visual representation
7. Database storage → cache for future access

### Data Storage Strategy
- **User profiles**: Normalized relational tables
- **Chart data**: JSON columns for complex structures
- **Interpretations**: Modular JSON files by category
- **Generated assets**: File system with database references
- **Cache**: Redis-style approach using MySQL

## Code Style & Standards

### PHP Standards
- **PSR-4** autoloading: `QuantumAstrology\` namespace
- **Strict typing**: `declare(strict_types=1)` in all files
- **Error handling**: Try-catch blocks for all external calls
- **Validation**: Input sanitization for all user data
- **Documentation**: PHPDoc blocks for all public methods

### Frontend Standards
- **Quantum Design System**: Match existing QMU aesthetic
- **Responsive Design**: Mobile-first approach
- **Accessibility**: ARIA labels, keyboard navigation
- **Performance**: Lazy loading, optimized assets
- **Animation**: Smooth transitions, particle effects

### Database Conventions
- **Table names**: snake_case with descriptive prefixes
- **Foreign keys**: Always with proper constraints
- **Indexes**: On all frequently queried columns
- **JSON validation**: Schema validation for complex data
- **Migrations**: Sequential numbered files

## Swiss Ephemeris Integration

### Calculation Requirements
- **Precision**: Sub-arcsecond accuracy for professional use
- **Time handling**: UTC conversion, leap seconds, Delta T
- **Coordinate systems**: Multiple house systems supported
- **Ephemeris range**: 13,000 BCE to 17,000 CE
- **Bodies**: Planets, asteroids, fixed stars, Arabic parts

### Implementation Approach
```php
// Use command-line interface for reliability
$command = "swetest -b{$jd} -p{$planets} -f{$format} -sid{$sidereal}";
$positions = shell_exec($command);

// Cache results for performance
$cache_key = md5($birth_data . $chart_type . $house_system);
```

## Design System Integration

### Quantum Minds United Branding
- **Color Palette**: Dark cosmic theme with gold accents
- **Typography**: Inter font family, clean hierarchy
- **Components**: Carousel cards, featured badges, particle effects
- **Animations**: Smooth hover states, loading transitions
- **Layout**: Consistent with video/audio/book libraries

### UI Patterns to Follow
```css
/* Quantum card styling */
.quantum-card {
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(15px);
    border-radius: 16px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

/* Featured content */
.featured-badge {
    background: linear-gradient(135deg, var(--quantum-gold), #FFA500);
    position: absolute;
    top: 15px;
    right: 15px;
}
```

## Testing Strategy

### Chart Accuracy Validation
- **Known birth data**: Test against established charts
- **Edge cases**: Leap years, DST transitions, polar coordinates
- **House systems**: Compare Placidus, Whole Sign, Equal, Koch
- **Aspect calculations**: Verify orbs and exactness
- **Progression timing**: Test secondary progressions accuracy

### Performance Benchmarks
- **Chart generation**: < 500ms for natal charts
- **Database queries**: < 100ms for cached lookups
- **SVG rendering**: < 200ms for complex wheels
- **Report generation**: < 2s for detailed PDFs
- **API responses**: < 1s for all endpoints

## Security Considerations

### Data Protection
- **Birth data**: Encrypted storage for sensitive information
- **User authentication**: Secure password hashing
- **API endpoints**: Rate limiting and input validation
- **File uploads**: Sanitization and type checking
- **SQL injection**: Parameterized queries only

### Privacy Requirements
- **GDPR compliance**: Data export/deletion capabilities
- **Birth time sensitivity**: Optional anonymization
- **Chart sharing**: Granular privacy controls
- **Audit logging**: Track access to sensitive data

## Deployment Guidelines

### Production Requirements
- **PHP**: 8.1+ with OPcache enabled
- **MySQL**: 8.0+ with proper indexing
- **SSL**: Required for birth data transmission
- **Backups**: Daily automated database backups
- **Monitoring**: Application performance monitoring
- **CDN**: For static assets and generated charts

### Environment Configuration
```php
// Production settings
define('APP_ENV', 'production');
define('APP_DEBUG', false);
define('CACHE_ENABLED', true);
define('SWEPH_PATH', '/usr/local/bin/swetest');
```

## Feature Development Priorities

### Phase 1 (MVP)
1. **User registration/login** system
2. **Natal chart calculation** with Swiss Ephemeris
3. **Basic interpretation** engine
4. **SVG chart rendering** with interactive elements
5. **PDF report generation** with QMU branding

### Phase 2 (Professional Features)
1. **Transit calculations** with timeline visualization
2. **Secondary progressions** and symbolic timing
3. **Solar/Lunar returns** with relocation
4. **Synastry analysis** and composite charts
5. **Advanced house systems** and coordinate options

### Phase 3 (Advanced Analysis)
1. **Harmonic charts** and frequency analysis
2. **Arabic parts** calculation and interpretation
3. **Fixed stars** integration with magnitudes
4. **Electional astrology** timing tools
5. **AI-generated reports** with voice narration

## API Design Patterns

### RESTful Endpoints
```
POST /api/charts/natal          # Generate natal chart
GET  /api/charts/{id}           # Retrieve chart data
POST /api/transits/calculate    # Calculate transit aspects
GET  /api/users/{id}/charts     # List user's charts
POST /api/reports/generate      # Create detailed report
```

### Response Format
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

## Integration Goals

### Ecosystem Compatibility
- **Design consistency**: Match video/audio/book library aesthetics
- **User experience**: Familiar navigation patterns
- **Data sharing**: Export capabilities for external tools
- **Mobile optimization**: Responsive across all devices
- **Performance**: Load times comparable to media libraries

### Professional Standards
- **Accuracy**: Swiss Ephemeris precision for professional use
- **Reliability**: 99.9% uptime for calculation services
- **Scalability**: Handle multiple concurrent chart generations
- **Documentation**: Comprehensive API and user guides
- **Support**: Integration with existing QMU community

## Maintenance & Updates

### Regular Tasks
- **Ephemeris updates**: Annual Swiss Ephemeris data refresh
- **Timezone database**: Quarterly political/DST updates
- **Security patches**: Monthly dependency updates
- **Performance optimization**: Quarterly cache analysis
- **User feedback**: Continuous interpretation improvements

### Quality Assurance
- **Automated testing**: CI/CD pipeline for all changes
- **Chart validation**: Compare against reference software
- **User acceptance**: Beta testing with astrology professionals
- **Performance monitoring**: Real-time application metrics
- **Error tracking**: Comprehensive logging and alerting
