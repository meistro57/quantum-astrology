#!/usr/bin/env bash
set -euo pipefail

# Usage:
#   bash tools/go-public.sh steelflowmrp.com admin@steelflowmrp.com
#
# This script:
# 1) verifies domain DNS resolves to this host public IP
# 2) requests a Let's Encrypt cert via certbot (standalone HTTP challenge)
# 3) installs cert/key into docker/certs/
# 4) sets .env for production domain + TLS_MODE=real
# 5) starts production stack

DOMAIN="${1:-}"
EMAIL="${2:-}"

if [[ -z "$DOMAIN" || -z "$EMAIL" ]]; then
  echo "Usage: bash tools/go-public.sh <domain> <email>"
  exit 1
fi

if [[ ! -f .env ]]; then
  echo "Error: .env not found. Create it first."
  exit 1
fi

if ! command -v curl >/dev/null 2>&1; then
  echo "Error: curl is required."
  exit 1
fi

if ! command -v getent >/dev/null 2>&1; then
  echo "Error: getent is required."
  exit 1
fi

if ! command -v docker >/dev/null 2>&1; then
  echo "Error: docker is required."
  exit 1
fi

HOST_IP="$(curl -sS https://api.ipify.org)"
DOMAIN_IPS="$(getent ahostsv4 "$DOMAIN" | awk '{print $1}' | sort -u || true)"

if [[ -z "$DOMAIN_IPS" ]]; then
  echo "Error: domain does not resolve yet: $DOMAIN"
  echo "Point DNS A record to this host IP: $HOST_IP"
  exit 1
fi

if ! grep -qx "$HOST_IP" <<<"$DOMAIN_IPS"; then
  echo "Error: domain resolves, but not to this host."
  echo "Host public IP: $HOST_IP"
  echo "Domain IPs:"
  echo "$DOMAIN_IPS"
  echo "Update DNS A record and retry."
  exit 1
fi

CERT_DIR="docker/certs"
mkdir -p "$CERT_DIR"

echo "Stopping current stack to free port 80 for cert challenge..."
bash shutdown.sh >/dev/null 2>&1 || true

echo "Requesting Let's Encrypt certificate for $DOMAIN ..."
docker run --rm \
  -p 80:80 \
  -v "$PWD/docker/letsencrypt:/etc/letsencrypt" \
  certbot/certbot:latest certonly \
  --non-interactive \
  --agree-tos \
  --email "$EMAIL" \
  --standalone \
  --preferred-challenges http \
  -d "$DOMAIN"

FULLCHAIN="$PWD/docker/letsencrypt/live/$DOMAIN/fullchain.pem"
PRIVKEY="$PWD/docker/letsencrypt/live/$DOMAIN/privkey.pem"

if [[ ! -f "$FULLCHAIN" || ! -f "$PRIVKEY" ]]; then
  echo "Error: certbot finished but cert files were not found."
  exit 1
fi

cp "$FULLCHAIN" "$CERT_DIR/server.crt"
cp "$PRIVKEY" "$CERT_DIR/server.key"
chmod 600 "$CERT_DIR/server.key"

set_env() {
  local key="$1"
  local value="$2"
  if rg -q "^${key}=" .env; then
    sed -i "s#^${key}=.*#${key}=${value}#" .env
  else
    printf '%s=%s\n' "$key" "$value" >> .env
  fi
}

set_env APP_ENV production
set_env APP_DEBUG false
set_env APP_URL "https://${DOMAIN}"
set_env SERVER_NAME "$DOMAIN"
set_env TLS_MODE real
set_env TLS_CERT_FILE server.crt
set_env TLS_KEY_FILE server.key
set_env HTTP_PORT 80
set_env HTTPS_PORT 443

echo "Starting production stack with real TLS..."
bash startup.sh

echo "Done. Public URL: https://${DOMAIN}"
