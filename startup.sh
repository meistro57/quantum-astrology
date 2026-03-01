#!/usr/bin/env bash
set -euo pipefail

echo "================================================"
echo "  Quantum Astrology - Production Launcher"
echo "================================================"
echo ""

# Ensure environment file exists
echo "[1/5] Checking environment configuration..."
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

# Load .env so startup behavior matches docker compose values.
if [ -f .env ]; then
    set -a
    # shellcheck disable=SC1091
    source ./.env
    set +a
fi

# Ensure TLS certificate material exists for HTTPS proxy
echo "[2/5] Checking TLS certificate files..."
CERT_DIR="docker/certs"
SERVER_NAME_VALUE="${SERVER_NAME:-localhost}"
TLS_CERT_FILE_VALUE="${TLS_CERT_FILE:-server.crt}"
TLS_KEY_FILE_VALUE="${TLS_KEY_FILE:-server.key}"
TLS_CERT_DAYS_VALUE="${TLS_CERT_DAYS:-365}"
TLS_MODE_VALUE="${TLS_MODE:-self-signed}"
CERT_PATH="${CERT_DIR}/${TLS_CERT_FILE_VALUE}"
KEY_PATH="${CERT_DIR}/${TLS_KEY_FILE_VALUE}"

mkdir -p "$CERT_DIR"

case "$TLS_MODE_VALUE" in
    self-signed)
        if [ ! -f "$CERT_PATH" ] || [ ! -f "$KEY_PATH" ]; then
            if ! command -v openssl >/dev/null 2>&1; then
                echo "Error: openssl is required to generate self-signed certificates."
                echo "Install openssl or set TLS_MODE=real and provide cert files at:"
                echo "  - $CERT_PATH"
                echo "  - $KEY_PATH"
                exit 1
            fi

            echo "      TLS_MODE=self-signed; generating certificate for CN=${SERVER_NAME_VALUE}"
            openssl req -x509 -nodes -newkey rsa:2048 \
                -keyout "$KEY_PATH" \
                -out "$CERT_PATH" \
                -sha256 \
                -days "$TLS_CERT_DAYS_VALUE" \
                -subj "/CN=${SERVER_NAME_VALUE}" >/dev/null 2>&1
            chmod 600 "$KEY_PATH"
            echo "      Generated: $CERT_PATH and $KEY_PATH"
        else
            echo "      TLS_MODE=self-signed; reusing existing cert/key in $CERT_DIR"
        fi
        ;;
    real)
        if [ ! -f "$CERT_PATH" ] || [ ! -f "$KEY_PATH" ]; then
            echo "Error: TLS_MODE=real requires certificate files:"
            echo "  - $CERT_PATH"
            echo "  - $KEY_PATH"
            echo "Either provide those files or set TLS_MODE=self-signed."
            exit 1
        fi
        echo "      TLS_MODE=real; using provided cert/key in $CERT_DIR"
        ;;
    *)
        echo "Error: Unsupported TLS_MODE='${TLS_MODE_VALUE}'."
        echo "Valid values: self-signed, real"
        exit 1
        ;;
esac

if [ -f "$CERT_PATH" ]; then
    echo "      Certificate file: $CERT_PATH"
fi
echo ""

# Resolve compose command
echo "[3/5] Detecting Docker Compose..."
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
echo "[4/5] Locating compose configuration..."
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

# Start production containers
echo "[5/5] Starting production containers..."
echo "      Command: $COMPOSE_CMD -f $COMPOSE_FILE up -d --build"
$COMPOSE_CMD -f "$COMPOSE_FILE" up -d --build

echo ""
echo "================================================"
echo "  Production containers are running"
echo "================================================"
echo ""
echo "HTTPS endpoint:"
echo "  https://${SERVER_NAME_VALUE}:${HTTPS_PORT:-443}"
echo ""
echo "Use this to inspect status:"
echo "  $COMPOSE_CMD -f $COMPOSE_FILE ps"
echo ""
echo "Use this to stop services:"
echo "  $COMPOSE_CMD -f $COMPOSE_FILE down"
