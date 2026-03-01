#!/usr/bin/env bash
set -euo pipefail

echo "================================================"
echo "  Quantum Astrology - Production Shutdown"
echo "================================================"
echo ""

# Resolve compose command
echo "[1/3] Detecting Docker Compose..."
if docker compose version >/dev/null 2>&1; then
    COMPOSE_CMD="docker compose"
elif command -v docker-compose >/dev/null 2>&1; then
    COMPOSE_CMD="docker-compose"
else
    echo "Error: Docker Compose not found. Install Docker with Compose support."
    exit 1
fi
echo "      Using: $COMPOSE_CMD"
echo ""

# Pick compose file (production first)
echo "[2/3] Locating compose configuration..."
if [ -f docker-compose.prod.yml ]; then
    COMPOSE_FILE="docker-compose.prod.yml"
elif [ -f docker-compose.prod.yaml ]; then
    COMPOSE_FILE="docker-compose.prod.yaml"
elif [ -f docker-compose.yml ]; then
    COMPOSE_FILE="docker-compose.yml"
elif [ -f docker-compose.yaml ]; then
    COMPOSE_FILE="docker-compose.yaml"
else
    echo "Error: No compose file found."
    echo "Expected one of:"
    echo "  - docker-compose.prod.yml"
    echo "  - docker-compose.prod.yaml"
    echo "  - docker-compose.yml"
    echo "  - docker-compose.yaml"
    exit 1
fi
echo "      Found: $COMPOSE_FILE"
echo ""

# Stop production containers
echo "[3/3] Stopping production containers..."
echo "      Command: $COMPOSE_CMD -f $COMPOSE_FILE down"
$COMPOSE_CMD -f "$COMPOSE_FILE" down

echo ""
echo "================================================"
echo "  Production containers stopped"
echo "================================================"
