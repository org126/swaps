-- Full-database seed data for development and testing.
-- Adjust IDs or remove explicit IDs if you want auto-increment behavior.
-- Run: mysql -u user -p your_db < seed_all.sql

-- Insert users (explicit IDs to reference from reports/logs)
INSERT INTO `users` (`user_id`,`username`,`password_hash`,`role`,`created_at`) VALUES
  (1,'alice','$2y$10$examplehash1','technician','2024-11-01 08:00:00'),
  (2,'bob','$2y$10$examplehash2','technician','2024-11-02 09:00:00'),
  (3,'carol','$2y$10$examplehash3','admin','2024-11-03 10:00:00'),
  (4,'dave','$2y$10$examplehash4','equipment_user','2024-11-04 11:00:00'),
  (5,'eve','$2y$10$examplehash5','technician','2024-11-05 12:00:00');

-- Insert machines
INSERT INTO `machines` (`id`,`part_number`,`machine_number`,`next_maintenance_date`,`notes`,`created_at`) VALUES
  (1,'PN-1001','M-001','2025-01-15','Checked belts and bearings','2024-10-01 08:00:00'),
  (2,'PN-1002','M-002','2025-02-20','Replaced filter','2024-10-05 09:30:00'),
  (3,'PN-1003','M-003','2025-03-10','Calibration pending','2024-10-10 10:00:00'),
  (4,'PN-1004','M-004','2025-04-01','General inspection done','2024-10-15 14:00:00'),
  (5,'PN-1005','M-005','2025-05-12','No issues','2024-10-20 16:30:00');

-- Insert reports (performed_by references `users.user_id`, part_number must exist in `machines`)
INSERT INTO `reports` (`issue_id`,`part_number`,`issue`,`severity`,`urgency`,`performed_by`,`availability`,`created_at`) VALUES
  (1,'PN-1001','Strange noise from motor',5,4,1,'pending','2024-12-01 09:00:00'),
  (2,'PN-1002','Leak observed near valve',6,7,2,'pending','2024-12-02 10:15:00'),
  (3,'PN-1003','Sensor miscalibrated',4,3,5,'in maintenance','2024-12-03 11:30:00'),
  (4,'PN-1004','Loose mounting bracket',3,2,1,'available','2024-12-04 12:45:00'),
  (5,'PN-1005','Overheating intermittently',8,9,2,'pending','2024-12-05 13:50:00');

-- Insert pictures for reports
INSERT INTO `pictures` (`picture_id`,`report_id`,`path`,`created_at`) VALUES
  (1,1,'uploads/reports/1_front.jpg','2024-12-01 09:05:00'),
  (2,2,'uploads/reports/2_leak.jpg','2024-12-02 10:20:00'),
  (3,3,'uploads/reports/3_sensor.png','2024-12-03 11:35:00'),
  (4,4,'uploads/reports/4_bracket.jpg','2024-12-04 12:50:00'),
  (5,5,'uploads/reports/5_overheat.jpg','2024-12-05 13:55:00');

-- Insert some audit logs
INSERT INTO `logs` (`log_id`,`table_changed`,`column_changed`,`old_info`,`new_info`,`user_id`,`changed_at`) VALUES
  (1,'reports','issue',NULL,'Created initial report #1',3,'2024-12-01 09:01:00'),
  (2,'reports','availability',NULL,'Set to in maintenance',5,'2024-12-03 11:40:00'),
  (3,'machines','notes',NULL,'Replaced filter on PN-1002',2,'2024-10-05 09:35:00'),
  (4,'users','role',NULL,'Promoted to admin',3,'2024-11-03 12:00:00'),
  (5,'reports','severity',NULL,'Updated severity to 8',2,'2024-12-05 14:00:00');

-- End of seed
