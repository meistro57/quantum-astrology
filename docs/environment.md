<!-- docs/environment.md -->
# Environment Configuration Guide

This guide documents the environment variables used by the Quantum Astrology suite. All variables can be provided via a `.env` file (loaded through `vlucas/phpdotenv`) or through standard server environment configuration. Values shown below represent production-ready defaults—adjust them thoughtfully for local development.

> **Security note:** Never commit `.env` files or plaintext credentials to version control. Prefer per-host configuration management (e.g., Ansible, Docker secrets, or platform-specific secret stores).

## Core Application Settings

| Variable | Required | Default | Description |
| --- | --- | --- | --- |
| `APP_ENV` | No | `production` | Sets the runtime environment. Use `local` during development to enable verbose logging. |
| `APP_DEBUG` | No | `false` | Enables debugging output when `true`. Ensure this is `false` in production to avoid leaking sensitive information. |
| `APP_URL` | No | `http://localhost` | Base URL used for URL generation in CLI tasks and emails. |
| `APP_TIMEZONE` | No | `UTC` | Default timezone applied across PHP date handling. |

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

## Session & Security Hardening

| Variable | Required | Default | Description |
| --- | --- | --- | --- |
| `SESSION_LIFETIME` | No | `3600` | Session lifetime in seconds. Increase for long-running admin workflows; decrease for heightened security. |

In addition to environment variables, PHP session cookies inherit secure defaults (`HttpOnly`, optional `Secure`, `SameSite=Strict`). Ensure the application is served via HTTPS to take full advantage of these protections.

## Example `.env`

```dotenv
# Application
APP_ENV=local
APP_DEBUG=true
APP_URL="http://quantum-astrology.test"
APP_TIMEZONE="Europe/London"

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

# Sessions
SESSION_LIFETIME=7200
```

Store this file alongside `config.php` when developing locally. Production deployments should inject the values using the hosting platform's secret management features.
