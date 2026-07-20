-- Phase 1C: multi-site minimal loop.
-- Idempotent migration for MySQL 5.7/MariaDB-compatible DDL.

CREATE TABLE IF NOT EXISTS `sites` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(200) NOT NULL,
  `address` text,
  `site_type` enum('main','branch') DEFAULT 'branch',
  `contact_person` varchar(100) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `sort_order` int DEFAULT 0,
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `site_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @col_exists = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'equipments'
    AND COLUMN_NAME = 'site_id'
);
SET @sql = IF(
  @col_exists = 0,
  'ALTER TABLE `equipments` ADD COLUMN `site_id` varchar(36) DEFAULT NULL AFTER `department_id`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'employees'
    AND COLUMN_NAME = 'primary_site_id'
);
SET @sql = IF(
  @col_exists = 0,
  'ALTER TABLE `employees` ADD COLUMN `primary_site_id` varchar(36) DEFAULT NULL AFTER `department_id`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'audit_schedules'
    AND COLUMN_NAME = 'site_id'
);
SET @sql = IF(
  @col_exists = 0,
  'ALTER TABLE `audit_schedules` ADD COLUMN `site_id` varchar(36) DEFAULT NULL AFTER `department_id`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `equipment_transfers` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `equipment_id` varchar(36) NOT NULL,
  `from_site_id` varchar(36) DEFAULT NULL,
  `to_site_id` varchar(36) NOT NULL,
  `transfer_date` date NOT NULL,
  `reason` text,
  `transferred_by` varchar(36) DEFAULT NULL,
  `remarks` text,
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `equipment_id` (`equipment_id`),
  KEY `to_site_id` (`to_site_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `sites` (`id`, `company_id`, `code`, `name`, `site_type`, `status`, `sort_order`, `publish`, `soft_delete`, `created`)
VALUES ('00000000-0000-0000-0000-000000000070', '00000000-0000-0000-0000-000000000001', 'MAIN', '主场所', 'main', 'active', 0, 1, 0, NOW());
