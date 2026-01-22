-- Seed data for machines (idempotent)
-- Demonstrates: One machine can have multiple parts, but each part belongs to only one machine.

INSERT INTO `machines` (`part_number`, `machine_number`, `next_maintenance_date`, `notes`)
VALUES
  -- Machine MX-01 has 3 different parts
  ('PN-1000', 'MX-01', '2026-02-15', 'Motor assembly for MX-01'),
  ('PN-1001', 'MX-01', '2026-02-18', 'Control panel for MX-01'),
  ('PN-1002', 'MX-01', '2026-03-01', 'Hydraulic pump for MX-01'),
  
  -- Machine MX-02 has 2 different parts
  ('PN-2000', 'MX-02', '2026-02-20', 'Main drive for MX-02'),
  ('PN-2001', 'MX-02', '2026-02-25', 'Cooling system for MX-02'),
  
  -- Machine MX-03 has 4 different parts
  ('PN-3000', 'MX-03', '2026-02-22', 'Electrical unit for MX-03'),
  ('PN-3001', 'MX-03', '2026-02-23', 'Sensor array for MX-03'),
  ('PN-3002', 'MX-03', '2026-02-28', 'Safety system for MX-03'),
  ('PN-3003', 'MX-03', '2026-03-05', 'Transmission for MX-03'),
  
  -- Machine MX-04 has 2 different parts
  ('PN-4000', 'MX-04', '2026-03-01', 'Power supply for MX-04'),
  ('PN-4001', 'MX-04', '2026-03-10', 'Display panel for MX-04'),
  
  -- Machine MX-05 has 1 part
  ('PN-5000', 'MX-05', '2026-03-15', 'Standalone unit for MX-05')
ON DUPLICATE KEY UPDATE
  `next_maintenance_date` = VALUES(`next_maintenance_date`),
  `notes` = VALUES(`notes`);
 