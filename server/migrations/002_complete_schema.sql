-- ============================================================
-- Migration: add ALL missing tables/columns (existing DB)
-- Run: mysql -u root shipsmart_db < server/migrations/002_complete_schema.sql
-- Safe to re-run: uses CREATE IF NOT EXISTS; skip ALTER lines that error if already applied.
-- ============================================================

USE shipsmart_db;

-- Users (if missing)
CREATE TABLE IF NOT EXISTS users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  email         VARCHAR(255)  NOT NULL UNIQUE,
  password_hash VARCHAR(255)  NOT NULL,
  full_name     VARCHAR(100)  NOT NULL,
  role          ENUM('admin', 'user') NOT NULL DEFAULT 'user',
  is_active     TINYINT(1)    NOT NULL DEFAULT 1,
  created_at    DATETIME      DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_users_role (role),
  INDEX idx_users_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO users (email, password_hash, full_name, role) VALUES
('admin@shipsmart.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ShipSmart Admin', 'admin'),
('user@shipsmart.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Demo User',       'user');

CREATE TABLE IF NOT EXISTS user_sessions (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  user_id       INT           NOT NULL,
  session_token VARCHAR(128)  NOT NULL UNIQUE,
  ip_address    VARCHAR(45)   DEFAULT NULL,
  user_agent    VARCHAR(512)  DEFAULT NULL,
  expires_at    DATETIME      NOT NULL,
  created_at    DATETIME      DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sessions_user (user_id),
  INDEX idx_sessions_expires (expires_at),
  CONSTRAINT fk_sessions_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT           NOT NULL,
  token_hash VARCHAR(255)  NOT NULL,
  expires_at DATETIME      NOT NULL,
  used_at    DATETIME      DEFAULT NULL,
  created_at DATETIME      DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_reset_user (user_id),
  INDEX idx_reset_expires (expires_at),
  CONSTRAINT fk_reset_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  email        VARCHAR(255) NOT NULL,
  ip_address   VARCHAR(45)  DEFAULT NULL,
  was_success  TINYINT(1)   NOT NULL DEFAULT 0,
  attempted_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_login_email (email),
  INDEX idx_login_attempted (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shipment_status_history (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  shipment_id     INT NOT NULL,
  status          ENUM('created', 'picked_up', 'in_transit', 'out_for_delivery', 'delivered') NOT NULL,
  location_note   VARCHAR(255) DEFAULT NULL,
  recorded_by     INT          DEFAULT NULL,
  recorded_at     DATETIME     DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_history_shipment (shipment_id),
  INDEX idx_history_recorded (recorded_at),
  CONSTRAINT fk_history_shipment
    FOREIGN KEY (shipment_id) REFERENCES shipments(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_history_recorded_by
    FOREIGN KEY (recorded_by) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_shipments (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  user_id       INT NOT NULL,
  shipment_id   INT NOT NULL,
  relationship  ENUM('owner', 'watcher') NOT NULL DEFAULT 'watcher',
  added_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_user_shipment (user_id, shipment_id),
  INDEX idx_user_shipments_user (user_id),
  INDEX idx_user_shipments_shipment (shipment_id),
  CONSTRAINT fk_user_shipments_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_user_shipments_shipment
    FOREIGN KEY (shipment_id) REFERENCES shipments(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tracking_queries (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  user_id         INT          DEFAULT NULL,
  tracking_number VARCHAR(50)  NOT NULL,
  carrier         ENUM('aramex', 'dhl', 'fedex', 'smsa') NOT NULL,
  found_in_db     TINYINT(1)   NOT NULL DEFAULT 0,
  ip_address      VARCHAR(45)  DEFAULT NULL,
  queried_at      DATETIME     DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_queries_user (user_id),
  INDEX idx_queries_tracking (tracking_number),
  INDEX idx_queries_date (queried_at),
  CONSTRAINT fk_queries_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_log (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  user_id       INT           DEFAULT NULL,
  recipient     VARCHAR(255)  NOT NULL,
  subject       VARCHAR(255)  NOT NULL,
  email_type    ENUM('feedback_confirmation','upload_confirmation','password_reset','other') NOT NULL DEFAULT 'other',
  status        ENUM('sent','failed') NOT NULL DEFAULT 'sent',
  related_table VARCHAR(50)   DEFAULT NULL,
  related_id    INT           DEFAULT NULL,
  sent_at       DATETIME      DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_email_recipient (recipient),
  INDEX idx_email_type (email_type),
  INDEX idx_email_sent (sent_at),
  CONSTRAINT fk_email_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_log (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT          DEFAULT NULL,
  action      VARCHAR(100) NOT NULL,
  entity_type VARCHAR(50)  DEFAULT NULL,
  entity_id   INT          DEFAULT NULL,
  details     TEXT         DEFAULT NULL,
  ip_address  VARCHAR(45)  DEFAULT NULL,
  created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_user (user_id),
  INDEX idx_audit_action (action),
  INDEX idx_audit_created (created_at),
  CONSTRAINT fk_audit_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Link demo user to sample shipments
INSERT IGNORE INTO user_shipments (user_id, shipment_id, relationship)
SELECT u.id, s.id, 'owner'
FROM users u
JOIN shipments s ON s.tracking_number IN ('ARX1002345678', 'DHL7700123456', 'SMSA8800112233')
WHERE u.email = 'user@shipsmart.com';

-- Sample status history for ARX1002345678 (skip if already present)
INSERT INTO shipment_status_history (shipment_id, status, location_note, recorded_at)
SELECT s.id, 'created', 'Jeddah warehouse', '2026-05-28 08:00:00'
FROM shipments s WHERE s.tracking_number = 'ARX1002345678'
  AND NOT EXISTS (SELECT 1 FROM shipment_status_history h WHERE h.shipment_id = s.id AND h.status = 'created');

INSERT INTO shipment_status_history (shipment_id, status, location_note, recorded_at)
SELECT s.id, 'picked_up', 'Jeddah pickup hub', '2026-05-29 10:30:00'
FROM shipments s WHERE s.tracking_number = 'ARX1002345678'
  AND NOT EXISTS (SELECT 1 FROM shipment_status_history h WHERE h.shipment_id = s.id AND h.status = 'picked_up');

INSERT INTO shipment_status_history (shipment_id, status, location_note, recorded_at)
SELECT s.id, 'in_transit', 'En route to Riyadh', '2026-05-31 09:15:00'
FROM shipments s WHERE s.tracking_number = 'ARX1002345678'
  AND NOT EXISTS (SELECT 1 FROM shipment_status_history h WHERE h.shipment_id = s.id AND h.status = 'in_transit');

-- ============================================================
-- ALTER existing tables (ignore errors if column/FK already exists)
-- Run these one at a time in phpMyAdmin if the batch fails.
-- ============================================================
-- ALTER TABLE feedback ADD COLUMN user_id INT DEFAULT NULL AFTER id;
-- ALTER TABLE shipment_documents ADD COLUMN user_id INT DEFAULT NULL AFTER id;
-- ALTER TABLE shipment_documents ADD COLUMN shipment_id INT DEFAULT NULL AFTER user_id;
-- ALTER TABLE shipment_documents ADD COLUMN uploader_email VARCHAR(255) DEFAULT NULL AFTER shipment_id;
