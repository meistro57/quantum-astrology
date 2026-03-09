# Production Docker Deployment

This guide provides explicit steps for running Quantum Astrology in production with `docker-compose.prod.yml`.

## 1. Prerequisites

- Docker Engine with Docker Compose plugin (`docker compose`)
- Ports `80` and `443` open on the host
- DNS `A`/`AAAA` record pointing your domain to this host (for real TLS mode)

## 2. Prepare Environment

Create your environment file:

```bash
cp .env.example .env
```

Set at least these values:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example
SERVER_NAME=your-domain.example

DB_DRIVER=mysql
DB_HOST=db
DB_PORT=3306
DB_NAME=quantum_astrology
DB_USER=quantum_app
DB_PASS=replace_with_strong_password
MYSQL_ROOT_PASSWORD=replace_with_strong_root_password

HTTP_PORT=80
HTTPS_PORT=443
TLS_MODE=real
TLS_CERT_FILE=server.crt
TLS_KEY_FILE=server.key
```

## 3. Configure TLS Mode

`startup.sh` validates TLS files before launching containers.

### Option A: Self-Signed (test/staging)

```env
TLS_MODE=self-signed
SERVER_NAME=localhost
```

- If cert files are missing, `startup.sh` generates them in `docker/certs/`.

### Option B: Real Certificate (public production)

```env
TLS_MODE=real
SERVER_NAME=your-domain.example
TLS_CERT_FILE=server.crt
TLS_KEY_FILE=server.key
```

Place your CA-issued files at:

- `docker/certs/server.crt`
- `docker/certs/server.key`

## 4. Start Services

```bash
bash startup.sh
```

What this script does:

1. Loads `.env`
2. Checks/generates TLS material based on `TLS_MODE`
3. Uses `docker-compose.prod.yml` when available
4. Runs `docker compose -f docker-compose.prod.yml up -d --build`

## 5. Verify Deployment

Check running services:

```bash
docker compose -f docker-compose.prod.yml ps
```

Check health endpoint:

```bash
curl -k https://your-domain.example/api/health.php
```

Inspect logs if needed:

```bash
docker compose -f docker-compose.prod.yml logs -f
```

## 6. Stop or Restart

Stop:

```bash
bash shutdown.sh
```

Restart after config/image changes:

```bash
bash startup.sh
```

## 7. Security Checklist

- Replace default DB credentials before first public launch.
- Keep `.env` out of version control.
- Use `TLS_MODE=real` for internet-facing production.
- Restrict host firewall to required ports.
- Keep Docker images patched (`docker compose pull` + restart during maintenance windows).
