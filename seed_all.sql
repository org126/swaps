-- Full-database seed data for development and testing.
-- Adjust IDs or remove explicit IDs if you want auto-increment behavior.
-- Run: mysql -u user -p your_db < seed_all.sql

-- Insert users with real bcrypt hashes (password: "password123" for all test users)
-- Admin: carol / password123
-- Technicians: alice, bob, eve / password123
-- Equipment User: dave / password123
INSERT INTO `users` (`user_id`,`username`,`password_hash`,`role`,`created_at`) VALUES
  (1,'alice','$2y$10$SaltSaltSaltSaltSaltSu8KkCkQYOZ.4rRFrC3bJx9nGKq6qjVpm','technician','2025-01-01 08:00:00'),
  (2,'bob','$2y$10$SaltSaltSaltSaltSaltSu8KkCkQYOZ.4rRFrC3bJx9nGKq6qjVpm','technician','2025-01-02 09:00:00'),
  (3,'carol','$2y$10$SaltSaltSaltSaltSaltSu8KkCkQYOZ.4rRFrC3bJx9nGKq6qjVpm','admin','2025-01-03 10:00:00'),
  (4,'dave','$2y$10$SaltSaltSaltSaltSaltSu8KkCkQYOZ.4rRFrC3bJx9nGKq6qjVpm','equipment_user','2025-01-04 11:00:00'),
  (5,'eve','$2y$10$SaltSaltSaltSaltSaltSu8KkCkQYOZ.4rRFrC3bJx9nGKq6qjVpm','technician','2025-01-05 12:00:00')
ON DUPLICATE KEY UPDATE
  password_hash=VALUES(password_hash),
  role=VALUES(role);

-- Insert machines (state column is required: 'ready', 'in_use', 'in_maintenance', 'out_of_order')
INSERT INTO `machines` (`id`,`part_number`,`machine_number`,`next_maintenance_date`,`notes`,`state`,`created_at`) VALUES
  (1,'PN-1001','M-001','2025-06-15','Checked belts and bearings','ready','2025-01-10 08:00:00'),
  (2,'PN-1002','M-002','2025-07-20','Replaced filter','ready','2025-01-11 09:30:00'),
  (3,'PN-1003','M-003','2025-08-10','Calibration pending','in_maintenance','2025-01-12 10:00:00'),
  (4,'PN-1004','M-004','2025-09-01','General inspection done','ready','2025-01-13 14:00:00'),
  (5,'PN-1005','M-005','2025-10-12','No issues','out_of_order','2025-01-14 16:30:00')
ON DUPLICATE KEY UPDATE
  next_maintenance_date=VALUES(next_maintenance_date),
  notes=VALUES(notes),
  state=VALUES(state);

-- Insert reports (performed_by references `users.user_id`, part_number must exist in `machines`)
-- Note: reports table has no 'availability' column - that's tracked in machines.state
INSERT INTO `reports` (`issue_id`,`part_number`,`issue`,`severity`,`urgency`,`performed_by`,`created_at`) VALUES
  (1,'PN-1001','Strange noise from motor',5,4,1,'2025-01-20 09:00:00'),
  (2,'PN-1002','Leak observed near valve',6,7,2,'2025-01-21 10:15:00'),
  (3,'PN-1003','Sensor miscalibrated',4,3,5,'2025-01-22 11:30:00'),
  (4,'PN-1004','Loose mounting bracket',3,2,1,'2025-01-23 12:45:00'),
  (5,'PN-1005','Overheating intermittently',8,9,2,'2025-01-24 13:50:00')
ON DUPLICATE KEY UPDATE
  issue=VALUES(issue),
  severity=VALUES(severity),
  urgency=VALUES(urgency);

-- Pictures table removed from database.sql schema

-- Insert some audit logs
INSERT INTO `logs` (`log_id`,`table_changed`,`column_changed`,`old_info`,`new_info`,`user_id`,`changed_at`) VALUES
  (1,'reports','issue',NULL,'Created initial report #1',3,'2025-01-20 09:01:00'),
  (2,'machines','state','ready','Set to in_maintenance',5,'2025-01-22 11:40:00'),
  (3,'machines','notes',NULL,'Replaced filter on PN-1002',2,'2025-01-11 09:35:00'),
  (4,'users','role','equipment_user','Promoted to admin',3,'2025-01-03 12:00:00'),
  (5,'reports','severity','5','Updated severity to 8',2,'2025-01-24 14:00:00')
ON DUPLICATE KEY UPDATE
  new_info=VALUES(new_info);

-- End of seed
