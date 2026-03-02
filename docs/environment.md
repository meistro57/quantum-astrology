<!-- docs/environment.md -->
# Environment Configuration Guide

This guide documents the environment variables used by the Quantum Astrology suite. All variables can be provided via a `.env` file (loaded through `vlucas/phpdotenv`) or through standard server environment configuration. Values shown below represent production-ready defaults—adjust them thoughtfully for local development.

> **Security note:** Never commit `.env` files or plaintext credentials to version control. Prefer per-host configuration management (e.g., Ansible, Docker secrets, or platform-specific secret stores).

## Core Application Settings

| Variable | Required | Default | Description |
| --- | --- | --- | --- |
| `APP_ENV` | No | `production` | Sets the runtime environment. Use `local` during development to enable verbose logging. |
| `APP_DEBUG` | No | `false` | Enables debugging output when `true`. Ensure this is `false` in production to avoid leaking sensitive information. |
| `APP_NAME` | No | `Quantum Astrology` | Application display name. Used in provider headers such as OpenRouter `X-Title`. |
| `APP_URL` | No | `http://localhost` | Base URL used for URL generation in CLI tasks and emails. |
| `APP_TIMEZONE` | No | `UTC` | Default timezone applied across PHP date handling. |
| `GITHUB_ISSUES_URL` | No | `https://github.com/meistro57/quantum-astrology/issues` | URL opened by the `/admin` "Open GitHub Issues" button. |

## Database Configuration

| Variable | Required | Default | Description |
| --- | --- | --- | --- |
| `DB_DRIVER` | No | `sqlite` | Database driver. Use `mysql` to connect to an existing MySQL/MariaDB server or leave as `sqlite` for the bundled file database. |
| `DB_HOST` | Yes (mysql) | `localhost` | Hostname or IP address of the MySQL/MariaDB server. |
| `DB_PORT` | No (mysql) | `3306` | Database server port. |
| `DB_NAME` | Yes (mysql) | `quantum_astrology` | Schema name that stores application data. |
| `DB_USER` | Yes (mysql) | `root` | Database user with permissions to read/write the schema. |
| `DB_PASS` | Yes (mysql) | *(empty)* | Password for the database user. Leave blank only when using socket-based auth. |
| `DB_CHARSET` | No (mysql) | `utf8mb4` | Character set applied to PDO connections. |
| `DB_COLLATION` | No (mysql) | `utf8mb4_unicode_ci` | Collation applied to string columns when using MySQL/MariaDB. |
| `DB_SQLITE_PATH` | No (sqlite) | `storage/database.sqlite` | Path to a SQLite database file used in development or testing. Leave unset when using MySQL. |

### Suggested MySQL Permissions

The database user should have the following privileges on the target schema: `SELECT`, `INSERT`, `UPDATE`, `DELETE`, `CREATE`, `ALTER`, and `INDEX`. Grant `LOCK TABLES` only when running maintenance routines.

## Cache Controls

| Variable | Required | Default | Description |
| --- | --- | --- | --- |
| `CACHE_ENABLED` | No | `true` | Toggles the caching layer for computed charts and Swiss Ephemeris lookups. Disable during debugging to observe uncached behaviour. |
| `CACHE_TTL` | No | `3600` | Cache lifetime in seconds. Choose lower values if dealing with frequently changing chart data. |

## Swiss Ephemeris Integration

| Variable | Required | Default | Description |
| --- | --- | --- | --- |
| `SWEPH_PATH` | Yes | `/usr/local/bin/swetest` | Absolute path to the `swetest` executable. Ensure the binary is executable by the web server user. |
| `SWEPH_DATA_PATH` | Yes | `data/ephemeris` | Directory containing the Swiss Ephemeris data files. Provide an absolute path in production deployments. |

## AI Configuration

| Variable | Required | Default | Description |
| --- | --- | --- | --- |
| `AI_PROVIDER` | No | `ollama` | AI provider used for natural-language interpretations and AI summary reports (`ollama`, `openrouter`, `openai`, `anthropic`, `deepseek`, `gemini`). |
| `AI_MODEL` | No | provider-specific default | Model override. Set to a provider-supported model string; leave unset to use built-in defaults. |
| `AI_API_KEY` | Conditional | *(empty)* | API key for cloud providers. Not required for local `ollama`. |
| `AI_API_ENDPOINT` | No | provider default endpoint | Optional custom endpoint override (useful for self-hosted proxies/gateways). |
| `AI_CACHE_TTL` | No | `21600` | AI interpretation cache TTL in seconds for chart-view AI reads. Set to `0` to disable cache writes/reads. |

> In current builds, AI provider/model are expected to be managed from `/admin` system settings for runtime usage in chart/report AI flows.

## Session & Security Hardening

| Variable | Required | Default | Description |
| --- | --- | --- | --- |
| `SESSION_LIFETIME` | No | `2592000` | Inactivity timeout in seconds (current default: 30 days for beta workflows). |
| `SESSION_COOKIE_LIFETIME` | No | `2592000` | Session cookie expiration in seconds (persists login across browser restarts). |
| `ADMIN_EMAIL` | No | *(empty)* | Optional single email to treat as admin in addition to DB flags. |
| `ADMIN_EMAILS` | No | *(empty)* | Optional comma-separated admin email allowlist (takes precedence over `ADMIN_EMAIL`). |

In addition to environment variables, PHP session cookies inherit secure defaults (`HttpOnly`, optional `Secure`, `SameSite=Lax`) and are refreshed on activity. Ensure the application is served via HTTPS to take full advantage of these protections.

## Example `.env`

```dotenv
# Application
APP_ENV=local
APP_DEBUG=true
APP_NAME="Quantum Astrology"
APP_URL="http://quantum-astrology.test"
APP_TIMEZONE="Europe/London"
GITHUB_ISSUES_URL="https://github.com/meistro57/quantum-astrology/issues"

# Database
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=quantum_astrology
DB_USER=quantum_app
DB_PASS="replace-with-strong-password"
DB_CHARSET=utf8mb4

# Cache
CACHE_ENABLED=true
CACHE_TTL=900

# Swiss Ephemeris
SWEPH_PATH="/usr/local/bin/swetest"
SWEPH_DATA_PATH="/opt/sweph/ephemeris"

# AI
AI_PROVIDER="ollama"
AI_MODEL="llama3.1"
AI_API_KEY=""
AI_API_ENDPOINT=""
AI_CACHE_TTL=21600

# Sessions
SESSION_LIFETIME=2592000
SESSION_COOKIE_LIFETIME=2592000

# Optional admin allowlist
ADMIN_EMAILS="admin@example.com,ops@example.com"
```

Store this file alongside `config.php` when developing locally. Production deployments should inject the values using the hosting platform's secret management features.
