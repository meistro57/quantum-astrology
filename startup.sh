#!/usr/bin/env bash
set -euo pipefail

echo "================================================"
echo "  Quantum Astrology - Application Launcher"
echo "================================================"
echo ""

# Update git repository
echo "[1/6] Updating git repository..."
if git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    if git remote get-url origin >/dev/null 2>&1; then
        git pull --ff-only
    else
        echo "      No git remote configured; skipping git pull."
    fi
else
    echo "      Not a git repository; skipping git pull."
fi
echo ""

# Install PHP dependencies
echo "[2/6] Installing PHP dependencies..."
if command -v composer >/dev/null 2>&1; then
    composer install --no-interaction --prefer-dist --no-progress
else
    echo "      Composer not found; skipping PHP dependencies installation."
fi
echo ""

# Install Node dependencies
echo "[3/6] Installing Node dependencies..."
if [ -f package.json ]; then
    if command -v npm >/dev/null 2>&1; then
        npm install --no-progress
    else
        echo "      npm not found; skipping Node dependencies installation."
    fi
fi
echo ""

# Setup environment file
echo "[4/6] Checking environment configuration..."
if [ ! -f .env ]; then
    if [ -f .env.example ]; then
        echo "      Creating .env file from .env.example..."
        cp .env.example .env
        echo "      NOTE: Please review .env and update database credentials if needed"
    else
        echo "      WARNING: No .env or .env.example found"
    fi
else
    echo "      .env file exists"
fi
echo ""

# Run database migrations
echo "[5/6] Running database migrations..."
if [ -f tools/migrate.php ]; then
    php tools/migrate.php
    echo "      Database migrations completed"
else
    echo "      WARNING: tools/migrate.php not found; skipping migrations"
fi
echo ""

# Start development server
echo "[6/6] Starting development server..."
echo ""
echo "================================================"
echo "  Server running at: http://localhost:8080"
echo "================================================"
echo ""
echo "  First time setup:"
echo "  - Register your account: http://localhost:8080/register"
echo "  - Login: http://localhost:8080/login"
echo "  - Dashboard: http://localhost:8080/"
echo ""
echo "  Press Ctrl+C to stop the server"
echo ""
echo "================================================"
echo ""

php -S localhost:8080 index.php
