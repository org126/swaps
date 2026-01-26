-- Schema for secure_web
-- Tables: machines, users, reports, logs

DROP TABLE IF EXISTS `logs`;
DROP TABLE IF EXISTS `reports`;
DROP TABLE IF EXISTS `machines`;
DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
	`user_id` INT AUTO_INCREMENT PRIMARY KEY,
	`username` VARCHAR(100) NOT NULL UNIQUE COMMENT 'admin only',
	`password_hash` VARCHAR(255) NOT NULL COMMENT 'admin only',
	`role` ENUM('admin','technician','equipment_user') NOT NULL DEFAULT 'equipment_user' COMMENT 'admin only',
	`created_at` DATETIME DEFAULT CURRENT_TIMESTAMP  COMMENT 'admin only'
) ENGINE=InnoDB COMMENT='User accounts (admin managed)';

CREATE TABLE `machines` (
	`id` INT AUTO_INCREMENT PRIMARY KEY COMMENT 'admin only',
	`part_number` VARCHAR(255) NOT NULL UNIQUE COMMENT 'all',
	`machine_number` VARCHAR(255) NOT NULL COMMENT 'all',
	`next_maintenance_date` DATE COMMENT 'all',
	`notes` TEXT COMMENT 'tech only',
	`state` ENUM('ready','in_use','in_maintenance','out_of_order') NOT NULL DEFAULT 'ready' COMMENT 'all',
	`created_at` DATETIME DEFAULT CURRENT_TIMESTAMP  COMMENT 'admin only'
) ENGINE=InnoDB COMMENT='Machine inventory and maintenance info';

CREATE TABLE `reports` (
	`issue_id` INT AUTO_INCREMENT PRIMARY KEY COMMENT 'admin only',
	`part_number` VARCHAR(255) NOT NULL COMMENT 'all',
	`issue` TEXT NOT NULL COMMENT 'all',
	`severity` TINYINT UNSIGNED COMMENT 'tech only',
	`urgency` TINYINT UNSIGNED COMMENT 'tech only',
	`picture` VARCHAR(1024) COMMENT 'all',
	`performed_by` INT COMMENT 'tech user_id',
	`availability` ENUM('pending','in maintenance','available') NOT NULL DEFAULT 'pending' COMMENT 'all',
	`created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
	FOREIGN KEY (`performed_by`) REFERENCES `users`(`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB COMMENT='Reports and issues logged by techs';

CREATE TABLE `logs` (
	`log_id` INT AUTO_INCREMENT PRIMARY KEY,
	`table_changed` VARCHAR(255) NOT NULL COMMENT 'admin only',
	`column_changed` VARCHAR(255) COMMENT 'admin only',
	`old_info` TEXT COMMENT 'admin only',
	`new_info` TEXT COMMENT 'admin only',
	`user_id` INT COMMENT 'admin only',
	`changed_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
	FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB COMMENT='Audit logs (admin access only)';

-- Constraints: severity and urgency should be 1-10. MySQL will enforce via CHECK where supported.
ALTER TABLE `reports`
	ADD CONSTRAINT `chk_severity` CHECK (`severity` BETWEEN 1 AND 10),
	ADD CONSTRAINT `chk_urgency` CHECK (`urgency` BETWEEN 1 AND 10);

