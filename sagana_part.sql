-- ==========================================
-- Sagana (Member 3) - SWAP Part 2
-- Security: A03 Injection (handled in PHP), A09 Logging (audit_logs table)
-- IMPORTANT: This SQL assumes you already have a machine parts table.
-- ==========================================

-- 1) Reports table (your feature)
CREATE TABLE IF NOT EXISTS reports (
  issue_id INT AUTO_INCREMENT PRIMARY KEY,
  part_number VARCHAR(100) NOT NULL,
  issue TEXT NOT NULL,
  severity TINYINT UNSIGNED NOT NULL,
  urgency TINYINT UNSIGNED NOT NULL,
  status ENUM('out_of_order','under_maintenance','finished') NOT NULL DEFAULT 'out_of_order',
  technician.id INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  accepted_at DATETIME NULL,
  finished_at DATETIME NULL,
  INDEX idx_part_number (part_number),
  INDEX idx_status (status),
  INDEX idx_technician (technician.id)
);

-- 2) Audit logs table (A09)
CREATE TABLE IF NOT EXISTS audit_logs (
  log_id INT AUTO_INCREMENT PRIMARY KEY,
  event_type VARCHAR(50) NOT NULL,          -- REPORT_CREATED / REPORT_ACCEPTED / REPORT_FINISHED / REPORT_CREATE_FAILED / etc.
  report_id INT NULL,
  actor_role VARCHAR(30) NOT NULL,          -- reporter / technician
  actor_id INT NULL,                        -- tech_id from URL; reporter is NULL
  ip_address VARCHAR(64) NULL,
  user_agent VARCHAR(255) NULL,
  details VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_event_type (event_type),
  INDEX idx_report_id (report_id)
);

-- OPTIONAL: If you want, you can also add a foreign key to reports.issue_id, but not required.

-- 3) Machines table (create if missing) + safe seed
-- This creates a minimal `machines` table that matches the PHP code (part_number PK, state column)
CREATE TABLE IF NOT EXISTS machines (
  part_number VARCHAR(100) PRIMARY KEY,
  description VARCHAR(255) NULL,
  state ENUM('out_of_order','under_maintenance','available') NOT NULL DEFAULT 'available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Safe idempotent seed: won't error on duplicates and will keep values in sync
-- If the `machines` table existed without a `description` column, add it (MySQL 8+ supports IF NOT EXISTS)
ALTER TABLE machines ADD COLUMN IF NOT EXISTS description VARCHAR(255) NULL;

-- Ensure `reports` table has a `status` column expected by the PHP code.
ALTER TABLE reports ADD COLUMN IF NOT EXISTS status ENUM('out_of_order','under_maintenance','finished') NOT NULL DEFAULT 'out_of_order';

-- Idempotent seed. Use only columns that exist; keep description and state in sync when possible.
INSERT INTO machines (part_number, description, state) VALUES
  ('PN-1001', 'Widget A', 'available'),
  ('PN-1002', 'Widget B', 'available'),
  ('PN-1003', 'Widget C', 'available')
ON DUPLICATE KEY UPDATE
  description = VALUES(description),
  state = VALUES(state);
