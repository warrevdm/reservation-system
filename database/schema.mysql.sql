SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(30) NOT NULL DEFAULT 'admin',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rooms (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    slug VARCHAR(80) NOT NULL UNIQUE,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dining_tables (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_id INT UNSIGNED NOT NULL,
    name VARCHAR(40) NOT NULL,
    seats INT NOT NULL DEFAULT 2,
    shape ENUM('round','square','rectangle') NOT NULL DEFAULT 'round',
    pos_x DECIMAL(6,2) NOT NULL DEFAULT 10.00,
    pos_y DECIMAL(6,2) NOT NULL DEFAULT 10.00,
    width_pct DECIMAL(6,2) NOT NULL DEFAULT 14.00,
    height_pct DECIMAL(6,2) NOT NULL DEFAULT 14.00,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT fk_table_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
    INDEX idx_tables_room (room_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reservations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_code VARCHAR(16) NOT NULL UNIQUE,
    reservation_date DATE NOT NULL,
    start_time TIME NOT NULL,
    duration_minutes INT NOT NULL DEFAULT 120,
    party_size INT NOT NULL,
    guest_name VARCHAR(100) NOT NULL,
    guest_email VARCHAR(190) NOT NULL DEFAULT '',
    guest_phone VARCHAR(40) NOT NULL DEFAULT '',
    notes TEXT NULL,
    status ENUM('new','confirmed','seated','completed','cancelled','no_show') NOT NULL DEFAULT 'new',
    source ENUM('website','manual','phone','walk_in') NOT NULL DEFAULT 'website',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_reservation_day (reservation_date, start_time),
    INDEX idx_reservation_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reservation_tables (
    reservation_id BIGINT UNSIGNED NOT NULL,
    table_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (reservation_id, table_id),
    CONSTRAINT fk_rt_reservation FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
    CONSTRAINT fk_rt_table FOREIGN KEY (table_id) REFERENCES dining_tables(id) ON DELETE CASCADE,
    INDEX idx_rt_table (table_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    action VARCHAR(80) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    payload TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_audit_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS request_throttle (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fingerprint VARCHAR(100) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_throttle_fingerprint (fingerprint),
    INDEX idx_throttle_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO rooms (id, name, slug, sort_order) VALUES
(1, 'Binnen', 'binnen', 10),
(2, 'Terras', 'terras', 20),
(3, 'Tuin', 'tuin', 30);

INSERT IGNORE INTO dining_tables (id, room_id, name, seats, shape, pos_x, pos_y, width_pct, height_pct) VALUES
(1, 1, 'B1', 2, 'round', 9, 12, 13, 17),
(2, 1, 'B2', 4, 'rectangle', 29, 11, 18, 15),
(3, 1, 'B3', 4, 'rectangle', 55, 11, 18, 15),
(4, 1, 'B4', 2, 'round', 81, 12, 13, 17),
(5, 1, 'B5', 4, 'square', 14, 52, 16, 20),
(6, 1, 'B6', 4, 'square', 42, 50, 16, 20),
(7, 1, 'B7', 4, 'square', 70, 52, 16, 20),
(8, 1, 'B8', 6, 'rectangle', 38, 78, 24, 14),
(9, 2, 'T1', 4, 'round', 8, 12, 15, 19),
(10, 2, 'T2', 4, 'round', 31, 12, 15, 19),
(11, 2, 'T3', 4, 'round', 54, 12, 15, 19),
(12, 2, 'T4', 4, 'round', 77, 12, 15, 19),
(13, 2, 'T5', 4, 'round', 8, 58, 15, 19),
(14, 2, 'T6', 4, 'round', 31, 58, 15, 19),
(15, 2, 'T7', 4, 'round', 54, 58, 15, 19),
(16, 2, 'T8', 4, 'round', 77, 58, 15, 19),
(17, 3, 'G1', 6, 'rectangle', 8, 17, 22, 16),
(18, 3, 'G2', 6, 'rectangle', 39, 17, 22, 16),
(19, 3, 'G3', 6, 'rectangle', 70, 17, 22, 16),
(20, 3, 'G4', 6, 'rectangle', 8, 62, 22, 16),
(21, 3, 'G5', 6, 'rectangle', 39, 62, 22, 16),
(22, 3, 'G6', 6, 'rectangle', 70, 62, 22, 16);

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('booking_interval_minutes', '30'),
('default_duration_minutes', '120'),
('max_online_party_size', '12'),
('bookable_days_ahead', '90'),
('min_lead_minutes', '60'),
('max_covers_per_slot', '80'),
('opening_hours', '{"1":{"open":"16:00","close":"00:00"},"2":{"open":"16:00","close":"00:00"},"3":{"open":"16:00","close":"00:00"},"4":{"open":"16:00","close":"00:00"},"5":{"open":"16:00","close":"01:00"},"6":{"open":"10:00","close":"02:00"},"7":{"open":"10:00","close":"22:00"}}');
