-- ==========================================================================
-- Migration 001 — run this ONLY on a database created from an older db.sql
-- (before user deactivation + login throttling were added). It upgrades the
-- tables in place and keeps all existing bookings, users and payments.
--
-- Fresh installs don't need this: db.sql already includes these changes.
--   phpMyAdmin: select the database > Import > choose this file.
-- If you get "Duplicate column name 'is_active'", it was already applied.
-- ==========================================================================

ALTER TABLE users
  ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER role,
  ADD COLUMN last_login_at DATETIME NULL AFTER is_active;

CREATE TABLE IF NOT EXISTS login_attempts (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email        VARCHAR(150) NOT NULL,
  ip_address   VARCHAR(45)  NOT NULL,
  attempted_at DATETIME NOT NULL,
  INDEX idx_attempt_email (email, attempted_at),
  INDEX idx_attempt_ip (ip_address, attempted_at)
) ENGINE=InnoDB;
