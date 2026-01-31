-- Schema for secure_web
-- Tables: machines, users, reports, logs

-- NOTE: DROP order avoids FK failures (drop children before parents).
-- Drops are idempotent so this file can be re-run during development.
DROP TABLE IF EXISTS `logs`;
DROP TABLE IF EXISTS `reports`;
DROP TABLE IF EXISTS `pictures`;
DROP TABLE IF EXISTS `machines`;
DROP TABLE IF EXISTS `users`;

-- Users table: admin-managed user accounts. Passwords must be stored hashed.
CREATE TABLE `users` (
	`user_id` INT AUTO_INCREMENT PRIMARY KEY,
	`username` VARCHAR(100) NOT NULL UNIQUE COMMENT 'admin only',
	`password_hash` VARCHAR(255) NOT NULL COMMENT 'admin only',
	-- `role` controls permissions: keep enum values small and explicit.
	`role` ENUM('admin','technician','equipment_user') NOT NULL DEFAULT 'equipment_user' COMMENT 'admin only',
	`created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'record created timestamp'
) ENGINE=InnoDB COMMENT='User accounts (admin managed)';

-- Machines table: inventory and maintenance scheduling.
CREATE TABLE `machines` (
	`id` INT AUTO_INCREMENT PRIMARY KEY COMMENT 'admin only',
	`part_number` VARCHAR(255) NOT NULL UNIQUE COMMENT 'all',
	`machine_number` VARCHAR(255) NOT NULL COMMENT 'all',
	`next_maintenance_date` DATE COMMENT 'all',
	`notes` TEXT COMMENT 'tech only',
	`state` ENUM('ready','in_use','in_maintenance','out_of_order') NOT NULL DEFAULT 'ready' COMMENT 'all',
	`created_at` DATETIME DEFAULT CURRENT_TIMESTAMP  COMMENT 'admin only'
) ENGINE=InnoDB COMMENT='Machine inventory and maintenance info';

-- Reports table: technical issues logged by technicians.
CREATE TABLE `reports` (
	`issue_id` INT AUTO_INCREMENT PRIMARY KEY COMMENT 'admin only',
	`part_number` VARCHAR(255) NOT NULL COMMENT 'all',
	`issue` TEXT NOT NULL COMMENT 'all',
	-- severity/urgency are constrained to 1..10 below; storing as TINYINT is sufficient.
	`severity` TINYINT UNSIGNED COMMENT 'tech only',
	`urgency` TINYINT UNSIGNED COMMENT 'tech only',
	-- `performed_by` stores the user who performed the work; FK below.
	`performed_by` INT COMMENT 'tech user_id',
	`created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'record created timestamp',
	FOREIGN KEY (`performed_by`) REFERENCES `users`(`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB COMMENT='Reports and issues logged by techs';

-- Audit logs: record admin actions and changes. Sensitive fields should be redacted when stored.
CREATE TABLE `logs` (
	`log_id` INT AUTO_INCREMENT PRIMARY KEY,
	`table_changed` VARCHAR(255) NOT NULL COMMENT 'admin only',
	`column_changed` VARCHAR(255) COMMENT 'admin only',
	`old_info` TEXT COMMENT 'admin only',
	`new_info` TEXT COMMENT 'admin only',
	`user_id` INT COMMENT 'admin only',
	-- Fixed typo: DATETIMzE -> DATETIME
	`changed_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
	FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB COMMENT='Audit logs (admin access only)';

-- Constraints: severity and urgency should be 1-10. MySQL will enforce via CHECK where supported.
ALTER TABLE `reports`
	ADD CONSTRAINT `chk_severity` CHECK (`severity` BETWEEN 1 AND 10),
	ADD CONSTRAINT `chk_urgency` CHECK (`urgency` BETWEEN 1 AND 10);

-- ======================================================
-- AUDIT TRIGGERS: log inserts/updates/deletes to `logs`
-- ======================================================
DROP TRIGGER IF EXISTS `trg_users_ai`;
DROP TRIGGER IF EXISTS `trg_users_au`;
DROP TRIGGER IF EXISTS `trg_users_ad`;
DROP TRIGGER IF EXISTS `trg_machines_ai`;
DROP TRIGGER IF EXISTS `trg_machines_au`;
DROP TRIGGER IF EXISTS `trg_machines_ad`;
DROP TRIGGER IF EXISTS `trg_reports_ai`;
DROP TRIGGER IF EXISTS `trg_reports_au`;
DROP TRIGGER IF EXISTS `trg_reports_ad`;

DELIMITER $$

-- USERS
CREATE TRIGGER `trg_users_ai` AFTER INSERT ON `users`
FOR EACH ROW
BEGIN
	INSERT INTO `logs` (`table_changed`,`column_changed`,`old_info`,`new_info`,`user_id`,`changed_at`)
	VALUES ('users','row',NULL,CONCAT('Created user_id=', NEW.user_id, ', username=', NEW.username, ', role=', NEW.role), NULL, NOW());
END$$

CREATE TRIGGER `trg_users_au` AFTER UPDATE ON `users`
FOR EACH ROW
BEGIN
	INSERT INTO `logs` (`table_changed`,`column_changed`,`old_info`,`new_info`,`user_id`,`changed_at`)
	VALUES (
		'users','row',
		CONCAT('user_id=', OLD.user_id, ', username=', OLD.username, ', role=', OLD.role),
		CONCAT('user_id=', NEW.user_id, ', username=', NEW.username, ', role=', NEW.role),
		NULL, NOW()
	);
END$$

CREATE TRIGGER `trg_users_ad` AFTER DELETE ON `users`
FOR EACH ROW
BEGIN
	INSERT INTO `logs` (`table_changed`,`column_changed`,`old_info`,`new_info`,`user_id`,`changed_at`)
	VALUES ('users','row',CONCAT('Deleted user_id=', OLD.user_id, ', username=', OLD.username, ', role=', OLD.role),NULL,NULL,NOW());
END$$

-- MACHINES
CREATE TRIGGER `trg_machines_ai` AFTER INSERT ON `machines`
FOR EACH ROW
BEGIN
	INSERT INTO `logs` (`table_changed`,`column_changed`,`old_info`,`new_info`,`user_id`,`changed_at`)
	VALUES ('machines','row',NULL,CONCAT('Created id=', NEW.id, ', part=', NEW.part_number, ', machine=', NEW.machine_number, ', state=', NEW.state),NULL,NOW());
END$$

CREATE TRIGGER `trg_machines_au` AFTER UPDATE ON `machines`
FOR EACH ROW
BEGIN
	INSERT INTO `logs` (`table_changed`,`column_changed`,`old_info`,`new_info`,`user_id`,`changed_at`)
	VALUES (
		'machines','row',
		CONCAT('id=', OLD.id, ', part=', OLD.part_number, ', machine=', OLD.machine_number, ', state=', OLD.state),
		CONCAT('id=', NEW.id, ', part=', NEW.part_number, ', machine=', NEW.machine_number, ', state=', NEW.state),
		NULL, NOW()
	);
END$$

CREATE TRIGGER `trg_machines_ad` AFTER DELETE ON `machines`
FOR EACH ROW
BEGIN
	INSERT INTO `logs` (`table_changed`,`column_changed`,`old_info`,`new_info`,`user_id`,`changed_at`)
	VALUES ('machines','row',CONCAT('Deleted id=', OLD.id, ', part=', OLD.part_number, ', machine=', OLD.machine_number, ', state=', OLD.state),NULL,NULL,NOW());
END$$

-- REPORTS
CREATE TRIGGER `trg_reports_ai` AFTER INSERT ON `reports`
FOR EACH ROW
BEGIN
	INSERT INTO `logs` (`table_changed`,`column_changed`,`old_info`,`new_info`,`user_id`,`changed_at`)
	VALUES ('reports','row',NULL,CONCAT('Created issue_id=', NEW.issue_id, ', part=', NEW.part_number, ', severity=', NEW.severity, ', urgency=', NEW.urgency),NEW.performed_by,NOW());
END$$

CREATE TRIGGER `trg_reports_au` AFTER UPDATE ON `reports`
FOR EACH ROW
BEGIN
	INSERT INTO `logs` (`table_changed`,`column_changed`,`old_info`,`new_info`,`user_id`,`changed_at`)
	VALUES (
		'reports','row',
		CONCAT('issue_id=', OLD.issue_id, ', part=', OLD.part_number, ', severity=', OLD.severity, ', urgency=', OLD.urgency),
		CONCAT('issue_id=', NEW.issue_id, ', part=', NEW.part_number, ', severity=', NEW.severity, ', urgency=', NEW.urgency),
		NEW.performed_by, NOW()
	);
END$$

CREATE TRIGGER `trg_reports_ad` AFTER DELETE ON `reports`
FOR EACH ROW
BEGIN
	INSERT INTO `logs` (`table_changed`,`column_changed`,`old_info`,`new_info`,`user_id`,`changed_at`)
	VALUES ('reports','row',CONCAT('Deleted issue_id=', OLD.issue_id, ', part=', OLD.part_number, ', severity=', OLD.severity, ', urgency=', OLD.urgency),NULL,OLD.performed_by,NOW());
END$$

DELIMITER ;

