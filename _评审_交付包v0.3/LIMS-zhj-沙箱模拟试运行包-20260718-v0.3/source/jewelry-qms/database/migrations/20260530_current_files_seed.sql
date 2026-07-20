-- 现用体系文件初始信息录入支撑结构
-- 幂等：兼容 MySQL 5.7 / MariaDB 10.3，不依赖 ALTER IF NOT EXISTS。

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'equipments' AND COLUMN_NAME = 'measurement_range');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `equipments` ADD COLUMN `measurement_range` varchar(200) DEFAULT NULL AFTER `serial_number`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'equipments' AND COLUMN_NAME = 'traceability_method');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `equipments` ADD COLUMN `traceability_method` varchar(50) DEFAULT NULL AFTER `measurement_range`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'equipments' AND COLUMN_NAME = 'traceability_due_date');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `equipments` ADD COLUMN `traceability_due_date` date DEFAULT NULL AFTER `traceability_method`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'equipments' AND COLUMN_NAME = 'traceability_confirm_result');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `equipments` ADD COLUMN `traceability_confirm_result` varchar(50) DEFAULT NULL AFTER `traceability_due_date`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `employee_appointments` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `employee_id` varchar(36) NOT NULL,
  `position_id` varchar(36) DEFAULT NULL,
  `site_id` varchar(36) DEFAULT NULL,
  `appointment_key` varchar(200) NOT NULL,
  `appointment_type` enum('role','authorization','responsibility') DEFAULT 'role',
  `position_name` varchar(200) NOT NULL,
  `appointment_scope` text,
  `appointed_at` date DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `source_document_id` varchar(36) DEFAULT NULL,
  `source_document_number` varchar(80) DEFAULT NULL,
  `source_excerpt` text,
  `status` enum('active','inactive','expired') DEFAULT 'active',
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_appointment_key` (`company_id`,`appointment_key`),
  KEY `employee_id` (`employee_id`),
  KEY `position_id` (`position_id`),
  KEY `site_id` (`site_id`),
  KEY `appointment_type` (`appointment_type`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
