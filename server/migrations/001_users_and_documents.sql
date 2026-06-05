-- ============================================================
-- Migration for EXISTING shipsmart_db (already has old tables)
-- Run: mysql -u root shipsmart_db < server/migrations/001_users_and_documents.sql
--
-- FIX: All bare ALTER TABLE statements replaced with safe
--      IF NOT EXISTS column-existence checks via stored procedure.
--      Safe to re-run on any database state.
-- ============================================================

USE shipsmart_db;

-- Users (safe regardless of prior state)
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

-- ── shipment_documents: add columns only if they don't already exist ──────────

DROP PROCEDURE IF EXISTS _sp_add_col;
DELIMITER $$
CREATE PROCEDURE _sp_add_col(
    IN tbl  VARCHAR(64),
    IN col  VARCHAR(64),
    IN defn TEXT,
    IN pos  TEXT          -- e.g. 'AFTER `id`' or ''
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name   = tbl
          AND column_name  = col
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN `', col, '` ', defn, ' ', pos);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

CALL _sp_add_col('shipment_documents', 'user_id',        'INT DEFAULT NULL COMMENT ''Logged-in uploader''', 'AFTER `id`');
CALL _sp_add_col('shipment_documents', 'uploader_email', 'VARCHAR(255) DEFAULT NULL',                        'AFTER `user_id`');

-- Add indexes only if missing
DROP PROCEDURE IF EXISTS _sp_add_idx;
DELIMITER $$
CREATE PROCEDURE _sp_add_idx(
    IN tbl  VARCHAR(64),
    IN idx  VARCHAR(64),
    IN defn TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name   = tbl
          AND index_name   = idx
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', tbl, '` ADD ', defn);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

CALL _sp_add_idx('shipment_documents', 'idx_docs_user',     'INDEX idx_docs_user (user_id)');
CALL _sp_add_idx('shipment_documents', 'idx_docs_tracking', 'INDEX idx_docs_tracking (tracking_number)');

-- Add FK only if missing
DROP PROCEDURE IF EXISTS _sp_add_fk;
DELIMITER $$
CREATE PROCEDURE _sp_add_fk(
    IN tbl  VARCHAR(64),
    IN fk   VARCHAR(64),
    IN defn TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.table_constraints
        WHERE table_schema     = DATABASE()
          AND table_name       = tbl
          AND constraint_name  = fk
          AND constraint_type  = 'FOREIGN KEY'
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', tbl, '` ADD CONSTRAINT `', fk, '` ', defn);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

CALL _sp_add_fk('shipment_documents', 'fk_documents_user',
    'FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE');

-- ── feedback: add user_id only if it doesn't already exist ───────────────────

CALL _sp_add_col('feedback', 'user_id', 'INT DEFAULT NULL', 'AFTER `id`');
CALL _sp_add_idx('feedback', 'idx_feedback_user', 'INDEX idx_feedback_user (user_id)');
CALL _sp_add_fk ('feedback', 'fk_feedback_user',
    'FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE');

-- ── Cleanup helper procedures ─────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS _sp_add_col;
DROP PROCEDURE IF EXISTS _sp_add_idx;
DROP PROCEDURE IF EXISTS _sp_add_fk;
