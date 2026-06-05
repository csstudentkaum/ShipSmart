-- ============================================================
-- File: server/schema.sql
-- Purpose: Complete ShipSmart database schema
-- Run: mysql -u root < server/schema.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS shipsmart_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE shipsmart_db;

-- ============================================================
-- 1. Users (roles: admin, user)
-- ============================================================
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

INSERT INTO users (email, password_hash, full_name, role) VALUES
('admin@shipsmart.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ShipSmart Admin', 'admin'),
('user@shipsmart.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Demo User',       'user')
ON DUPLICATE KEY UPDATE email = email;

-- ============================================================
-- 2. User sessions (login persistence)
-- ============================================================
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

-- ============================================================
-- 3. Password reset tokens
-- ============================================================
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

-- ============================================================
-- 4. Login attempts (security / rate limiting)
-- ============================================================
CREATE TABLE IF NOT EXISTS login_attempts (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  email        VARCHAR(255) NOT NULL,
  ip_address   VARCHAR(45)  DEFAULT NULL,
  was_success  TINYINT(1)   NOT NULL DEFAULT 0,
  attempted_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_login_email (email),
  INDEX idx_login_attempted (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. Shipments (search / tracking)
-- ============================================================
CREATE TABLE IF NOT EXISTS shipments (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  tracking_number    VARCHAR(50)   NOT NULL UNIQUE,
  carrier            ENUM('aramex', 'dhl', 'fedex', 'smsa') NOT NULL,
  origin_city        VARCHAR(100)  NOT NULL,
  destination_city   VARCHAR(100)  NOT NULL,
  status             ENUM('created', 'picked_up', 'in_transit', 'out_for_delivery', 'delivered') NOT NULL DEFAULT 'created',
  category           ENUM('standard', 'express', 'freight') NOT NULL DEFAULT 'standard',
  weight_kg          DECIMAL(8,2)  NOT NULL,
  estimated_delivery DATE          NOT NULL,
  last_updated       DATETIME      NOT NULL,
  created_at         DATETIME      DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_shipments_carrier (carrier),
  INDEX idx_shipments_status (status),
  INDEX idx_shipments_eta (estimated_delivery)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO shipments (tracking_number, carrier, origin_city, destination_city, status, category, weight_kg, estimated_delivery, last_updated) VALUES
('ARX1002345678', 'aramex', 'Jeddah', 'Riyadh', 'in_transit', 'express', 2.50, '2026-06-03', '2026-05-31 09:15:00'),
('ARX1009876543', 'aramex', 'Dammam', 'Khobar', 'delivered', 'standard', 1.20, '2026-05-28', '2026-05-28 14:30:00'),
('DHL7700123456', 'dhl', 'Riyadh', 'Dubai', 'out_for_delivery', 'express', 5.75, '2026-06-01', '2026-05-31 07:45:00'),
('DHL7700987654', 'dhl', 'Jeddah', 'Cairo', 'picked_up', 'freight', 48.00, '2026-06-10', '2026-05-30 16:20:00'),
('FDX5522110099', 'fedex', 'Riyadh', 'Jeddah', 'created', 'standard', 3.10, '2026-06-05', '2026-05-31 08:00:00'),
('FDX5522334455', 'fedex', 'Mecca', 'Medina', 'in_transit', 'express', 0.85, '2026-06-02', '2026-05-31 11:10:00'),
('SMSA8800112233', 'smsa', 'Riyadh', 'Abha', 'delivered', 'standard', 4.40, '2026-05-27', '2026-05-27 18:00:00'),
('SMSA8800445566', 'smsa', 'Tabuk', 'Jeddah', 'in_transit', 'freight', 120.50, '2026-06-08', '2026-05-31 06:30:00'),
('ARX1005551212', 'aramex', 'Khobar', 'Riyadh', 'out_for_delivery', 'express', 6.30, '2026-06-01', '2026-05-31 10:05:00'),
('DHL7700332211', 'dhl', 'Dubai', 'Riyadh', 'delivered', 'express', 2.00, '2026-05-29', '2026-05-29 20:15:00'),
('FDX5522667788', 'fedex', 'Jeddah', 'Dammam', 'picked_up', 'standard', 7.25, '2026-06-04', '2026-05-31 05:40:00'),
('SMSA8800778899', 'smsa', 'Riyadh', 'Hail', 'created', 'standard', 1.50, '2026-06-06', '2026-05-31 12:00:00'),
('ARX1003334444', 'aramex', 'Medina', 'Riyadh', 'in_transit', 'freight', 85.00, '2026-06-09', '2026-05-30 22:30:00'),
('DHL7700556677', 'dhl', 'Riyadh', 'Manama', 'out_for_delivery', 'express', 3.80, '2026-06-01', '2026-05-31 08:55:00'),
('FDX5522990011', 'fedex', 'Abha', 'Jeddah', 'delivered', 'standard', 2.90, '2026-05-26', '2026-05-26 15:45:00'),
('SMSA8800123498', 'smsa', 'Jeddah', 'Riyadh', 'picked_up', 'express', 1.10, '2026-06-03', '2026-05-31 13:20:00'),
('ARX1007778888', 'aramex', 'Riyadh', 'Dubai', 'created', 'freight', 200.00, '2026-06-12', '2026-05-31 07:00:00')
ON DUPLICATE KEY UPDATE tracking_number = tracking_number;

-- ============================================================
-- 6. Shipment status history (timeline events)
-- ============================================================
CREATE TABLE IF NOT EXISTS shipment_status_history (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  shipment_id     INT NOT NULL,
  status          ENUM('created', 'picked_up', 'in_transit', 'out_for_delivery', 'delivered') NOT NULL,
  location_note   VARCHAR(255) DEFAULT NULL,
  recorded_by     INT          DEFAULT NULL COMMENT 'Admin user who recorded the update',
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

-- Sample timeline for ARX1002345678
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
-- 7. User ↔ shipments (my shipments / watched)
-- ============================================================
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

INSERT INTO user_shipments (user_id, shipment_id, relationship)
SELECT u.id, s.id, 'owner'
FROM users u
JOIN shipments s ON s.tracking_number IN ('ARX1002345678', 'DHL7700123456', 'SMSA8800112233')
WHERE u.email = 'user@shipsmart.com'
ON DUPLICATE KEY UPDATE relationship = relationship;

-- ============================================================
-- 8. Tracking query log (who searched what)
-- ============================================================
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

-- ============================================================
-- 9. Feedback (guest or logged-in)
-- ============================================================
CREATE TABLE IF NOT EXISTS feedback (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT           DEFAULT NULL,
  full_name   VARCHAR(100)  NOT NULL,
  email       VARCHAR(255)  NOT NULL,
  rating      ENUM('good','average','poor') NOT NULL,
  services    VARCHAR(255)  NOT NULL,
  carrier     VARCHAR(50)   NOT NULL,
  comments    TEXT          DEFAULT NULL,
  created_at  DATETIME      DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_feedback_user (user_id),
  INDEX idx_feedback_email (email),
  INDEX idx_feedback_created (created_at),
  CONSTRAINT fk_feedback_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 10. Shipment documents (user uploads)
-- ============================================================
CREATE TABLE IF NOT EXISTS shipment_documents (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  user_id         INT           DEFAULT NULL,
  shipment_id     INT           DEFAULT NULL COMMENT 'Linked shipment when tracking exists in DB',
  uploader_email  VARCHAR(255)  DEFAULT NULL,
  tracking_number VARCHAR(50)   NOT NULL,
  carrier         ENUM('aramex','dhl','fedex','smsa') NOT NULL,
  doc_type        ENUM('invoice','receipt','proof_of_delivery','other') NOT NULL,
  original_name   VARCHAR(255)  NOT NULL,
  saved_name      VARCHAR(255)  NOT NULL,
  file_size       INT UNSIGNED  NOT NULL,
  mime_type       VARCHAR(100)  NOT NULL,
  uploaded_at     DATETIME      DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_docs_user (user_id),
  INDEX idx_docs_shipment (shipment_id),
  INDEX idx_docs_tracking (tracking_number),
  INDEX idx_docs_uploaded (uploaded_at),
  CONSTRAINT fk_documents_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_documents_shipment
    FOREIGN KEY (shipment_id) REFERENCES shipments(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 11. Email log (confirmation / notification audit)
-- ============================================================
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

-- ============================================================
-- 12. Audit log (admin actions)
-- ============================================================
CREATE TABLE IF NOT EXISTS audit_log (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT          DEFAULT NULL,
  action      VARCHAR(100) NOT NULL COMMENT 'e.g. login, update_shipment, delete_document',
  entity_type VARCHAR(50)  DEFAULT NULL COMMENT 'e.g. shipment, user, document',
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
