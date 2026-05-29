-- Phase 2.4: training and competency deepening

CREATE TABLE IF NOT EXISTS `employee_certificates` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `employee_id` varchar(36) NOT NULL,
  `certificate_type` varchar(100) NOT NULL,
  `certificate_number` varchar(100) DEFAULT NULL,
  `issuing_authority` varchar(200) DEFAULT NULL,
  `issue_date` date DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `review_date` date DEFAULT NULL,
  `status` enum('active','expired','revoked','archived') DEFAULT 'active',
  `remarks` text,
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  KEY `valid_until` (`valid_until`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_plans' AND COLUMN_NAME = 'description');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `training_plans` ADD COLUMN `description` text DEFAULT NULL AFTER `title`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_plans' AND COLUMN_NAME = 'approved_by');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `training_plans` ADD COLUMN `approved_by` varchar(36) DEFAULT NULL AFTER `status`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_plans' AND COLUMN_NAME = 'approved_at');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `training_plans` ADD COLUMN `approved_at` datetime DEFAULT NULL AFTER `approved_by`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_plans' AND COLUMN_NAME = 'completed_at');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `training_plans` ADD COLUMN `completed_at` datetime DEFAULT NULL AFTER `approved_at`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_plans' AND COLUMN_NAME = 'modified');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `training_plans` ADD COLUMN `modified` datetime DEFAULT NULL AFTER `created`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
