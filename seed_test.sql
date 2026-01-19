-- Seed: admin user (idempotent)
INSERT INTO `users` (`username`, `password_hash`, `role`, `created_at`)
VALUES ('admin', '8c6976e5b5410415bde908bd4dee15dfb167a9c873fc4bb8a81f6f2ab448a918', 'admin', '2002-09-03 00:00:00')
ON DUPLICATE KEY UPDATE
	`password_hash` = VALUES(`password_hash`),
	`role` = VALUES(`role`),
	`created_at` = VALUES(`created_at`);
