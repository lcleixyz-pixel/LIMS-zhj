-- Wave1 R-5: employees active-only uniqueness (A2)
-- Soft-deleted rows do not occupy the unique key (generated column → NULL).
-- Blank strings among active rows still collide; normalize '' → NULL before apply.

-- Rollback legacy full-column unique indexes if present.
SET @schema_name = DATABASE();

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.statistics
   WHERE table_schema = @schema_name AND table_name = 'employees' AND index_name = 'uq_employees_employee_number') > 0,
  'ALTER TABLE `employees` DROP INDEX `uq_employees_employee_number`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.statistics
   WHERE table_schema = @schema_name AND table_name = 'employees' AND index_name = 'uq_employees_email') > 0,
  'ALTER TABLE `employees` DROP INDEX `uq_employees_email`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Generated active-only keys (MySQL 8 STORED).
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = @schema_name AND table_name = 'employees' AND column_name = 'employee_number_active') = 0,
  'ALTER TABLE `employees` ADD COLUMN `employee_number_active` varchar(64) GENERATED ALWAYS AS (IF(`soft_delete` = 0, `employee_number`, NULL)) STORED',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = @schema_name AND table_name = 'employees' AND column_name = 'email_active') = 0,
  'ALTER TABLE `employees` ADD COLUMN `email_active` varchar(255) GENERATED ALWAYS AS (IF(`soft_delete` = 0, `email`, NULL)) STORED',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.statistics
   WHERE table_schema = @schema_name AND table_name = 'employees' AND index_name = 'uq_employees_employee_number_active') = 0,
  'ALTER TABLE `employees` ADD UNIQUE KEY `uq_employees_employee_number_active` (`employee_number_active`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.statistics
   WHERE table_schema = @schema_name AND table_name = 'employees' AND index_name = 'uq_employees_email_active') = 0,
  'ALTER TABLE `employees` ADD UNIQUE KEY `uq_employees_email_active` (`email_active`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Optional stub table for EquipmentPeriodCheck mechanism entry (F-3a-02).
CREATE TABLE IF NOT EXISTS `equipment_period_checks` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `equipment_id` varchar(36) NOT NULL,
  `plan_date` date NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'planned',
  `note` text,
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `equipment_id` (`equipment_id`),
  KEY `plan_date` (`plan_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
