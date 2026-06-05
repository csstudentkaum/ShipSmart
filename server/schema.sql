-- ============================================================
-- File: server/schema.sql
-- Purpose: Create the ShipSmart database and feedback table
-- ============================================================

-- Create the database 
CREATE DATABASE IF NOT EXISTS shipsmart_db 
  CHARACTER SET utf8mb4 
  COLLATE utf8mb4_unicode_ci; 

-- use the database
USE shipsmart_db;

-- ============================================================
-- Users table (registration, login, RBAC)
-- Run once: mysql < server/schema.sql
-- Match credentials in server/db_config.php
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  full_name     VARCHAR(100)  NOT NULL,
  email         VARCHAR(255)  NOT NULL UNIQUE,
  password_hash VARCHAR(255)  NOT NULL,
  role          ENUM('admin', 'user') NOT NULL DEFAULT 'user',
  created_at    DATETIME      DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Demo accounts (Admin@1234 / User@1234) — bcrypt hashes only
INSERT IGNORE INTO users (full_name, email, password_hash, role) VALUES
('Admin', 'admin@shipsmart.com',
 '$2y$10$ys.DLS9GjBV/SJrrHgVKruLfy27oCrFJlcAJd2u.aHA6wBYf8qE5G', 'admin'),
('Sara Ahmed', 'user@shipsmart.com',
 '$2y$10$VM0l3pZAG34tIcX55T71Vu4qLq6uQ2GHf/rjwHyAi3PJ4.2tRDGlu', 'user');

-- Create the feedback table
CREATE TABLE IF NOT EXISTS feedback (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  full_name   VARCHAR(100)  NOT NULL,
  email       VARCHAR(255)  NOT NULL,
  rating      ENUM('good','average','poor') NOT NULL,
  services    VARCHAR(255)  NOT NULL  COMMENT 'Comma-separated list of selected services',
  carrier     VARCHAR(50)   NOT NULL,
  comments    TEXT          DEFAULT NULL,
  created_at  DATETIME      DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create the shipments table
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
  created_at         DATETIME      DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed shipments (varied carriers, statuses, and categories)
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
('ARX1007778888', 'aramex', 'Riyadh', 'Dubai', 'created', 'freight', 200.00, '2026-06-12', '2026-05-31 07:00:00');

-- ============================================================
-- Shipment documents table (File Upload System)
-- ============================================================
CREATE TABLE IF NOT EXISTS shipment_documents (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  tracking_number VARCHAR(50)   NOT NULL,
  carrier         ENUM('aramex','dhl','fedex','smsa') NOT NULL,
  doc_type        ENUM('invoice','receipt','proof_of_delivery','other') NOT NULL,
  original_name   VARCHAR(255)  NOT NULL  COMMENT 'Original filename from the user',
  saved_name      VARCHAR(255)  NOT NULL  COMMENT 'Renamed file stored on disk',
  file_size       INT UNSIGNED  NOT NULL  COMMENT 'Size in bytes',
  mime_type       VARCHAR(100)  NOT NULL,
  uploaded_at     DATETIME      DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
