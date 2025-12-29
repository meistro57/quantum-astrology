#!/bin/bash
# install-and-test.sh - Quantum Astrology Dependency Installer & Tester
# This script helps set up the environment and verify functionality.

set -e

# Colors for output
RED='\033[0-1;31m'
GREEN='\033[0-1;32m'
YELLOW='\033[0-1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}================================================${NC}"
echo -e "${YELLOW}  Quantum Astrology - Dependency Setup & Test  ${NC}"
echo -e "${YELLOW}================================================${NC}"

# 1. Check for PHP
echo -e "\n${YELLOW}[1/8] Checking for PHP...${NC}"
if ! command -v php &> /dev/null; then
    echo -e "${RED}❌ PHP NOT FOUND${NC}"
    echo "Please install PHP 8.0+ and required extensions:"
    echo -e "${YELLOW}sudo apt update && sudo apt install -y php-cli php-json php-pdo php-mbstring php-sqlite3 php-curl php-xml php-zip php-gd${NC}"
    exit 1
fi
PHP_VER=$(php -r 'echo PHP_VERSION;')
PHP_MAJOR=$(php -r 'echo PHP_MAJOR_VERSION;')
PHP_MINOR=$(php -r 'echo PHP_MINOR_VERSION;')
echo -e "${GREEN}✅ PHP $PHP_VER found.${NC}"

# 2. Check for required PHP extensions
echo -e "\n${YELLOW}[2/8] Checking required PHP extensions...${NC}"
MISSING_EXTS=""

# Check for GD extension (required by mpdf for PDF generation)
if ! php -m | grep -qi "^gd$"; then
    MISSING_EXTS="${MISSING_EXTS} php${PHP_MAJOR}.${PHP_MINOR}-gd"
fi

# Check for PDO SQLite or MySQL
if ! php -m | grep -qi "^pdo_sqlite$" && ! php -m | grep -qi "^pdo_mysql$"; then
    MISSING_EXTS="${MISSING_EXTS} php${PHP_MAJOR}.${PHP_MINOR}-sqlite3"
fi

# Check for mbstring
if ! php -m | grep -qi "^mbstring$"; then
    MISSING_EXTS="${MISSING_EXTS} php${PHP_MAJOR}.${PHP_MINOR}-mbstring"
fi

# Check for curl
if ! php -m | grep -qi "^curl$"; then
    MISSING_EXTS="${MISSING_EXTS} php${PHP_MAJOR}.${PHP_MINOR}-curl"
fi

# Check for xml
if ! php -m | grep -qi "^xml$"; then
    MISSING_EXTS="${MISSING_EXTS} php${PHP_MAJOR}.${PHP_MINOR}-xml"
fi

# Check for zip
if ! php -m | grep -qi "^zip$"; then
    MISSING_EXTS="${MISSING_EXTS} php${PHP_MAJOR}.${PHP_MINOR}-zip"
fi

if [ -n "$MISSING_EXTS" ]; then
    echo -e "${RED}❌ Missing required PHP extensions${NC}"
    echo -e "Please install the following extensions:"
    echo -e "${YELLOW}sudo apt update && sudo apt install -y${MISSING_EXTS}${NC}"
    echo ""
    echo "After installing, run this script again."
    exit 1
fi
echo -e "${GREEN}✅ All required PHP extensions found.${NC}"

# 3. Check for Composer
echo -e "\n${YELLOW}[3/8] Checking for Composer...${NC}"
if ! command -v composer &> /dev/null && [ ! -f "composer.phar" ]; then
    echo "Installing Composer locally..."
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    php composer-setup.php --quiet
    rm composer-setup.php
    COMPOSER_BIN="php composer.phar"
elif [ -f "composer.phar" ]; then
    COMPOSER_BIN="php composer.phar"
else
    COMPOSER_BIN="composer"
fi
echo -e "${GREEN}✅ Composer ready.${NC}"

# 4. Install Dependencies
echo -e "\n${YELLOW}[4/8] Installing PHP dependencies via Composer...${NC}"
$COMPOSER_BIN install --no-interaction --prefer-dist
echo -e "${GREEN}✅ Dependencies installed.${NC}"

# 5. Check for Swiss Ephemeris
echo -e "\n${YELLOW}[5/8] Checking for Swiss Ephemeris (swetest)...${NC}"
SWEPH_BIN=$(grep "SWEPH_PATH" .env 2>/dev/null | cut -d '=' -f2 || echo "/usr/local/bin/swetest")
if [ ! -f "$SWEPH_BIN" ] && ! command -v swetest &> /dev/null; then
    echo -e "${YELLOW}⚠️  swetest not found at $SWEPH_BIN${NC}"
    echo "Please install swetest for full astrological precision."
    echo "The application will fall back to analytical approximations."
else
    echo -e "${GREEN}✅ Swiss Ephemeris tool found.${NC}"
fi

# 6. Environment & Database
echo -e "\n${YELLOW}[6/8] Setting up environment and database...${NC}"
if [ ! -f .env ]; then
    echo "Creating .env from .env.example..."
    cp .env.example .env
fi

# Ensure storage directories exist
mkdir -p storage/logs storage/cache storage/charts storage/reports storage/backups storage/audio
chmod -R 775 storage/

echo "Running migrations..."
php tools/migrate.php
echo -e "${GREEN}✅ Database migrations completed.${NC}"

# 7. Syntax & Setup Verification
echo -e "\n${YELLOW}[7/8] Verifying setup syntax...${NC}"
php test-syntax.php
echo -e "${GREEN}✅ Syntax check passed.${NC}"

# 8. Run Functionality Tests
echo -e "\n${YELLOW}[8/8] Running functionality tests...${NC}"

# Run PHPUnit
if [ -f "vendor/bin/phpunit" ]; then
    echo "Executing PHPUnit suite..."
    vendor/bin/phpunit
else
    echo -e "${RED}❌ PHPUnit not found in vendor/bin. Run composer install.${NC}"
fi

# Run Chart Generation Test
echo -e "\nTesting chart generation engine..."
if [ -f "tools/test-chart-generation.php" ]; then
    php tools/test-chart-generation.php
else
    echo -e "${YELLOW}⚠️  tools/test-chart-generation.php not found.${NC}"
fi

echo -e "\n${YELLOW}================================================${NC}"
echo -e "${GREEN}  SUCCESS: Setup and testing complete!          ${NC}"
echo -e "${YELLOW}================================================${NC}"
echo "You can now start the server with: php -S localhost:8080 index.php"
