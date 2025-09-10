# Quantum Astrology Installation Guide

This guide will help you set up and run the Quantum Astrology application.

## Prerequisites

Before installing Quantum Astrology, ensure your system meets these requirements:

### Required Software
- **PHP 8.0+** with the following extensions:
  - PDO (for database connectivity)
  - JSON (for data handling)
  - OpenSSL (for security)
- **Web Server**: Apache 2.4+ or Nginx 1.18+ (or use PHP built-in server)
- **Git** for cloning the repository

### Optional (for full functionality)
- **MySQL 5.7+** or **MariaDB 10.3+** (for persistent data storage)
- **Composer** (for dependency management - coming in Phase 1)
- **Swiss Ephemeris** (for astrological calculations - coming in Phase 1)

## Quick Installation

### 1. Clone the Repository

```bash
git clone https://github.com/mestro57/quantum-astrology.git
cd quantum-astrology
```

### 2. Check System Requirements

Run the included syntax checker to verify your setup:

```bash
php test-syntax.php
```

This will verify:
- ✅ Configuration files exist
- ✅ Core PHP classes are present
- ✅ Directory structure is complete
- ✅ Assets are in place

### 3. Start Development Server

```bash
php -S localhost:8080 index.php
```

### 4. Access the Application

Open your browser and navigate to:
```
http://localhost:8080
```

You should see the beautiful Quantum Astrology dashboard with:
- 🌟 Quantum-themed interface with particle effects
- 📊 Interactive dashboard cards
- 📈 Statistics display
- 🎯 Quick action buttons

## Advanced Installation

### Using Apache Web Server

1. **Configure Virtual Host**

Create a new virtual host configuration:

```apache
<VirtualHost *:80>
    ServerName quantum-astrology.local
    DocumentRoot /path/to/quantum-astrology
    
    <Directory "/path/to/quantum-astrology">
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/quantum-astrology-error.log
    CustomLog ${APACHE_LOG_DIR}/quantum-astrology-access.log combined
</VirtualHost>
```

2. **Add to hosts file** (optional):
```bash
echo "127.0.0.1 quantum-astrology.local" | sudo tee -a /etc/hosts
```

3. **Restart Apache**:
```bash
sudo systemctl restart apache2
```

### Using Nginx

1. **Create Nginx configuration**:

```nginx
server {
    listen 80;
    server_name quantum-astrology.local;
    root /path/to/quantum-astrology;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location /assets/ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }
}
```

2. **Enable site and restart Nginx**:
```bash
sudo ln -s /etc/nginx/sites-available/quantum-astrology /etc/nginx/sites-enabled/
sudo systemctl restart nginx
```

## Environment Configuration

### 1. Create Environment File

Copy the example environment file:
```bash
cp .env.example .env
```

### 2. Edit Configuration

Edit `.env` file with your settings:

```env
# Application Settings
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8080
APP_TIMEZONE=America/New_York

# Database Configuration (for Phase 1)
DB_HOST=localhost
DB_NAME=quantum_astrology
DB_USER=your_username
DB_PASS=your_password

# Swiss Ephemeris Path (for Phase 1)
SWEPH_PATH=/usr/local/bin/swetest
SWEPH_DATA_PATH=/path/to/ephemeris/data

# Cache Settings
CACHE_ENABLED=true
CACHE_TTL=3600

# API Keys (optional - for Phase 2)
ELEVENLABS_API_KEY=your_api_key_here
```

## Troubleshooting

### Common Issues

#### 1. "PHP command not found"
**Solution**: Install PHP
```bash
# Ubuntu/Debian
sudo apt update && sudo apt install php php-cli

# CentOS/RHEL
sudo yum install php php-cli

# macOS with Homebrew
brew install php
```

#### 2. "Permission denied" errors
**Solution**: Set proper file permissions
```bash
chmod -R 755 quantum-astrology/
chmod -R 775 storage/
```

#### 3. CSS not loading
**Symptoms**: Dashboard appears unstyled
**Solution**: Check asset path in browser developer tools. Ensure:
- `assets/css/quantum-dashboard.css` exists
- Web server can serve static files
- No 404 errors in browser console

#### 4. Particle effects not working
**Symptoms**: Static background without animations
**Solution**: Check browser console for JavaScript errors. Ensure:
- Modern browser with ES6+ support
- JavaScript is enabled
- No Content Security Policy blocking inline scripts

### Browser Compatibility

The Quantum Astrology interface requires a modern browser with:
- CSS Grid support
- CSS Custom Properties (variables)
- ES6+ JavaScript
- SVG support

**Recommended browsers**:
- Chrome 60+
- Firefox 60+
- Safari 12+
- Edge 79+

### Performance Optimization

For better performance:

1. **Enable PHP OPcache** (production):
```ini
; In php.ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=4000
```

2. **Enable Gzip compression** (Apache):
```apache
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/css application/javascript
</IfModule>
```

3. **Browser caching** (Nginx example included above)

## Development Setup

For developers contributing to Quantum Astrology:

### 1. Install Development Dependencies

```bash
# Coming in Phase 1 - Composer integration
composer install --dev
```

### 2. Run Tests

```bash
# Syntax validation
php test-syntax.php

# Chart calculation tests (Phase 1)
php tools/test-chart-generation.php
```

### 3. Code Standards

Follow PSR-4 autoloading and maintain:
- Strict typing: `declare(strict_types=1)`
- Comprehensive error handling
- PHPDoc documentation
- Input validation and sanitization

## Next Steps

Once the application is running:

1. **Explore the Dashboard** - Navigate through the quantum-themed interface
2. **Check Logs** - Monitor `storage/logs/` for application messages
3. **Plan Phase 1** - Prepare for Swiss Ephemeris integration
4. **Join Community** - Connect with Quantum Minds United

## Getting Help

If you encounter issues:

1. Check the [troubleshooting section](#troubleshooting) above
2. Review browser developer console for errors
3. Check `storage/logs/` for application logs
4. Create an issue on GitHub with:
   - Operating system and PHP version
   - Browser and version
   - Error messages or screenshots
   - Steps to reproduce the issue

## Support

- **GitHub Issues**: [Create an issue](https://github.com/mestro57/quantum-astrology/issues)
- **Email**: mark@quantummindsunited.com
- **Website**: [Quantum Minds United](https://quantummindsunited.com)

---

Welcome to the Quantum Astrology community! 🌟