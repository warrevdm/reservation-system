PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'admin',
    is_active INTEGER NOT NULL DEFAULT 1,
    last_login_at TEXT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS rooms (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    slug TEXT NOT NULL UNIQUE,
    sort_order INTEGER NOT NULL DEFAULT 0,
    is_active INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS dining_tables (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    room_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    seats INTEGER NOT NULL DEFAULT 2,
    shape TEXT NOT NULL DEFAULT 'round' CHECK (shape IN ('round','square','rectangle')),
    pos_x REAL NOT NULL DEFAULT 10,
    pos_y REAL NOT NULL DEFAULT 10,
    width_pct REAL NOT NULL DEFAULT 14,
    height_pct REAL NOT NULL DEFAULT 14,
    is_active INTEGER NOT NULL DEFAULT 1,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS reservations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    public_code TEXT NOT NULL UNIQUE,
    reservation_date TEXT NOT NULL,
    start_time TEXT NOT NULL,
    duration_minutes INTEGER NOT NULL DEFAULT 120,
    party_size INTEGER NOT NULL,
    guest_name TEXT NOT NULL,
    guest_email TEXT NOT NULL DEFAULT '',
    guest_phone TEXT NOT NULL DEFAULT '',
    notes TEXT NULL,
    status TEXT NOT NULL DEFAULT 'new' CHECK (status IN ('new','confirmed','seated','completed','cancelled','no_show')),
    source TEXT NOT NULL DEFAULT 'website' CHECK (source IN ('website','manual','phone','walk_in')),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_reservation_day ON reservations(reservation_date, start_time);
CREATE INDEX IF NOT EXISTS idx_reservation_status ON reservations(status);

CREATE TABLE IF NOT EXISTS reservation_tables (
    reservation_id INTEGER NOT NULL,
    table_id INTEGER NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (reservation_id, table_id),
    FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
    FOREIGN KEY (table_id) REFERENCES dining_tables(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_rt_table ON reservation_tables(table_id);

CREATE TABLE IF NOT EXISTS settings (
    setting_key TEXT PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS audit_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NULL,
    action TEXT NOT NULL,
    entity_type TEXT NOT NULL,
    entity_id INTEGER NOT NULL,
    payload TEXT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS request_throttle (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    fingerprint TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_throttle_fingerprint ON request_throttle(fingerprint);
CREATE INDEX IF NOT EXISTS idx_throttle_created ON request_throttle(created_at);

INSERT OR IGNORE INTO rooms (id, name, slug, sort_order) VALUES
(1, 'Binnen', 'binnen', 10), (2, 'Terras', 'terras', 20), (3, 'Tuin', 'tuin', 30);

INSERT OR IGNORE INTO dining_tables (id, room_id, name, seats, shape, pos_x, pos_y, width_pct, height_pct) VALUES
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

INSERT OR IGNORE INTO settings (setting_key, setting_value) VALUES
('booking_interval_minutes', '30'),
('default_duration_minutes', '120'),
('max_online_party_size', '12'),
('bookable_days_ahead', '90'),
('min_lead_minutes', '60'),
('max_covers_per_slot', '80'),
('opening_hours', '{"1":{"open":"16:00","close":"00:00"},"2":{"open":"16:00","close":"00:00"},"3":{"open":"16:00","close":"00:00"},"4":{"open":"16:00","close":"00:00"},"5":{"open":"16:00","close":"01:00"},"6":{"open":"10:00","close":"02:00"},"7":{"open":"10:00","close":"22:00"}}');
