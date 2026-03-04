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
