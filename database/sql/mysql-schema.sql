-- database/sql/mysql-schema.sql
-- Quantum Astrology schema for MySQL/MariaDB deployments.

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(191) NOT NULL UNIQUE,
    email VARCHAR(191) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NULL,
    last_name VARCHAR(100) NULL,
    timezone VARCHAR(64) DEFAULT 'UTC',
    email_verified_at DATETIME NULL,
    last_login_at DATETIME NULL,
    birth_date DATE NULL,
    birth_time TIME NULL,
    birth_timezone VARCHAR(64) NULL,
    birth_city VARCHAR(191) NULL,
    birth_country VARCHAR(191) NULL,
    birth_latitude DECIMAL(10,6) NULL,
    birth_longitude DECIMAL(10,6) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS charts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    name VARCHAR(191) NOT NULL,
    chart_type VARCHAR(50) DEFAULT 'natal',
    birth_datetime DATETIME NOT NULL,
    birth_timezone VARCHAR(64) NOT NULL DEFAULT 'UTC',
    birth_latitude DECIMAL(10,6) NOT NULL,
    birth_longitude DECIMAL(10,6) NOT NULL,
    birth_location_name VARCHAR(255) NULL,
    house_system VARCHAR(10) DEFAULT 'P',
    chart_data LONGTEXT,
    planetary_positions LONGTEXT,
    house_positions LONGTEXT,
    aspects LONGTEXT,
    calculation_metadata LONGTEXT,
    is_public TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_charts_user_id (user_id),
    INDEX idx_charts_chart_type (chart_type),
    INDEX idx_charts_birth_datetime (birth_datetime),
    INDEX idx_charts_created_at (created_at),
    INDEX idx_charts_public (is_public),
    CONSTRAINT fk_charts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS birth_profiles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    name VARCHAR(191) NOT NULL,
    birth_datetime DATETIME NOT NULL,
    birth_timezone VARCHAR(64) NOT NULL DEFAULT 'UTC',
    birth_latitude DECIMAL(10,6) NOT NULL,
    birth_longitude DECIMAL(10,6) NOT NULL,
    birth_location_name VARCHAR(255) NULL,
    notes LONGTEXT,
    is_private TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_birth_profiles_user_id (user_id),
    INDEX idx_birth_profiles_name (name),
    INDEX idx_birth_profiles_birth_datetime (birth_datetime),
    INDEX idx_birth_profiles_location (birth_latitude, birth_longitude),
    CONSTRAINT fk_birth_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chart_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    chart_id INT UNSIGNED NOT NULL,
    session_data LONGTEXT,
    view_settings LONGTEXT,
    aspect_settings LONGTEXT,
    display_preferences LONGTEXT,
    last_accessed TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_chart_sessions_user_chart (user_id, chart_id),
    INDEX idx_chart_sessions_user_id (user_id),
    INDEX idx_chart_sessions_chart_id (chart_id),
    INDEX idx_chart_sessions_last_accessed (last_accessed),
    CONSTRAINT fk_chart_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_chart_sessions_chart FOREIGN KEY (chart_id) REFERENCES charts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS migrations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    migration VARCHAR(255) NOT NULL UNIQUE,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_created_at ON users(created_at);
