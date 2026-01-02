-- database/sql/sqlite-schema.sql
-- Quantum Astrology schema for SQLite deployments.

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    first_name TEXT NULL,
    last_name TEXT NULL,
    timezone TEXT DEFAULT 'UTC',
    email_verified_at TEXT NULL,
    last_login_at TEXT NULL,
    birth_date TEXT NULL,
    birth_time TEXT NULL,
    birth_timezone TEXT NULL,
    birth_city TEXT NULL,
    birth_country TEXT NULL,
    birth_latitude REAL NULL,
    birth_longitude REAL NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS charts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    chart_type TEXT DEFAULT 'natal',
    birth_datetime TEXT NOT NULL,
    birth_timezone TEXT NOT NULL DEFAULT 'UTC',
    birth_latitude REAL NOT NULL,
    birth_longitude REAL NOT NULL,
    birth_location_name TEXT,
    house_system TEXT DEFAULT 'P',
    chart_data TEXT,
    planetary_positions TEXT,
    house_positions TEXT,
    aspects TEXT,
    calculation_metadata TEXT,
    is_public INTEGER DEFAULT 0,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS birth_profiles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    birth_datetime TEXT NOT NULL,
    birth_timezone TEXT NOT NULL DEFAULT 'UTC',
    birth_latitude REAL NOT NULL,
    birth_longitude REAL NOT NULL,
    birth_location_name TEXT,
    notes TEXT,
    is_private INTEGER DEFAULT 1,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS chart_sessions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    chart_id INTEGER NOT NULL,
    session_data TEXT,
    view_settings TEXT,
    aspect_settings TEXT,
    display_preferences TEXT,
    last_accessed TEXT DEFAULT CURRENT_TIMESTAMP,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (chart_id) REFERENCES charts(id) ON DELETE CASCADE,
    UNIQUE(user_id, chart_id)
);

CREATE TABLE IF NOT EXISTS migrations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    migration TEXT NOT NULL UNIQUE,
    executed_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_created_at ON users(created_at);
CREATE INDEX IF NOT EXISTS idx_charts_user_id ON charts(user_id);
CREATE INDEX IF NOT EXISTS idx_charts_chart_type ON charts(chart_type);
CREATE INDEX IF NOT EXISTS idx_charts_birth_datetime ON charts(birth_datetime);
CREATE INDEX IF NOT EXISTS idx_charts_created_at ON charts(created_at);
CREATE INDEX IF NOT EXISTS idx_charts_public ON charts(is_public);
CREATE INDEX IF NOT EXISTS idx_birth_profiles_user_id ON birth_profiles(user_id);
CREATE INDEX IF NOT EXISTS idx_birth_profiles_name ON birth_profiles(name);
CREATE INDEX IF NOT EXISTS idx_birth_profiles_birth_datetime ON birth_profiles(birth_datetime);
CREATE INDEX IF NOT EXISTS idx_birth_profiles_location ON birth_profiles(birth_latitude, birth_longitude);
CREATE INDEX IF NOT EXISTS idx_chart_sessions_user_id ON chart_sessions(user_id);
CREATE INDEX IF NOT EXISTS idx_chart_sessions_chart_id ON chart_sessions(chart_id);
CREATE INDEX IF NOT EXISTS idx_chart_sessions_last_accessed ON chart_sessions(last_accessed);
