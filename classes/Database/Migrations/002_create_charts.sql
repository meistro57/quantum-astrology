CREATE TABLE birth_profiles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    name VARCHAR(100) NOT NULL,
    birth_date DATE NOT NULL,
    birth_time TIME,
    birth_timezone VARCHAR(50),
    latitude DECIMAL(10,8),
    longitude DECIMAL(11,8),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES astro_users(id)
);

CREATE TABLE calculated_charts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    profile_id INT,
    chart_type ENUM('natal', 'transit', 'progressed', 'solar_return'),
    planets_json JSON,
    aspects_json JSON,
    svg_chart_path VARCHAR(255),
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (profile_id) REFERENCES birth_profiles(id)
);
