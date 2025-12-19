#!/bin/bash
# start_server.sh - Lightweight development server launcher

# Default configuration
HOST="0.0.0.0"
PORT=8080

# Load from .env if available
if [ -f .env ]; then
    ENV_PORT=$(grep "^APP_URL=" .env | cut -d ':' -f3 | cut -d '/' -f1)
    if [ ! -z "$ENV_PORT" ]; then
        PORT=$ENV_PORT
    fi
fi

echo "================================================"
echo "  Quantum Astrology - Development Server"
echo "================================================"
echo "  Starting at: http://$HOST:$PORT"
echo "  Press Ctrl+C to stop the server"
echo "================================================"
echo ""

# Check for PHP
if ! command -v php &> /dev/null; then
    echo "Error: PHP is not installed or not in your PATH."
    exit 1
fi

# Check for index.php
if [ ! -f index.php ]; then
    echo "Error: index.php not found. Are you in the project root?"
    exit 1
fi

php -S $HOST:$PORT index.php
