-- ==========================================
-- Sagana (Member 3) - SWAP Part 2
-- Security: A03 Injection (handled in PHP), A09 Logging (audit_logs table)
-- ==========================================

-- 1) Reports table - with ALL required columns for PHP code
CREATE TABLE IF NOT EXISTS reports (
  issue_id INT AUTO_INCREMENT PRIMARY KEY,
  part_number VARCHAR(100) NOT NULL,
  issue TEXT NOT NULL,
  severity TINYINT UNSIGNED NOT NULL,
  urgency TINYINT UNSIGNED NOT NULL,
  status ENUM('out_of_order','under_maintenance','finished') NOT NULL DEFAULT 'out_of_order',
  technician_id INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  accepted_at DATETIME NULL,
  finished_at DATETIME NULL,
  INDEX idx_part_number (part_number),
  INDEX idx_status (status),
  INDEX idx_technician (technician_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ensure missing columns are added if table already existed
ALTER TABLE reports ADD COLUMN IF NOT EXISTS technician_id INT NULL;
ALTER TABLE reports ADD COLUMN IF NOT EXISTS accepted_at DATETIME NULL;
ALTER TABLE reports ADD COLUMN IF NOT EXISTS finished_at DATETIME NULL;

-- 2) Audit logs table (A09)
CREATE TABLE IF NOT EXISTS audit_logs (
  log_id INT AUTO_INCREMENT PRIMARY KEY,
  event_type VARCHAR(50) NOT NULL,
  report_id INT NULL,
  actor_role VARCHAR(30) NOT NULL,
  actor_id INT NULL,
  ip_address VARCHAR(64) NULL,
  user_agent VARCHAR(255) NULL,
  details VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_event_type (event_type),
  INDEX idx_report_id (report_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3) Machines table - CRITICAL: must match PHP code (part_number PK, state column)
CREATE TABLE IF NOT EXISTS machines (
  part_number VARCHAR(100) PRIMARY KEY,
  description VARCHAR(255) NULL,
  state ENUM('out_of_order','under_maintenance','available') NOT NULL DEFAULT 'available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ensure description column exists
ALTER TABLE machines ADD COLUMN IF NOT EXISTS description VARCHAR(255) NULL;

-- Idempotent seed: insert or update if duplicate
INSERT INTO machines (part_number, description, state) VALUES
  ('PN-1001', 'Widget A', 'available'),
  ('PN-1002', 'Widget B', 'available'),
  ('PN-1003', 'Widget C', 'available')
ON DUPLICATE KEY UPDATE
  description = VALUES(description),
  state = VALUES(state);

