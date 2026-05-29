-- Phase 1B: reminder command deduplication.
-- This migration is intentionally idempotent and avoids MySQL 8-only
-- ALTER TABLE ... IF NOT EXISTS syntax for 5.7/MariaDB compatibility.

SET @col_exists = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'notifications'
    AND COLUMN_NAME = 'notification_key'
);
SET @sql = IF(
  @col_exists = 0,
  'ALTER TABLE `notifications` ADD COLUMN `notification_key` varchar(200) DEFAULT NULL AFTER `due_date`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists = (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'notifications'
    AND INDEX_NAME = 'company_notification_key'
);
SET @sql = IF(
  @idx_exists = 0,
  'ALTER TABLE `notifications` ADD UNIQUE KEY `company_notification_key` (`company_id`,`notification_key`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

DELETE nu
FROM `notification_users` nu
JOIN `notification_users` keep_nu
  ON keep_nu.notification_id = nu.notification_id
 AND keep_nu.user_id = nu.user_id
 AND keep_nu.id < nu.id;

SET @idx_exists = (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'notification_users'
    AND INDEX_NAME = 'notification_user'
);
SET @sql = IF(
  @idx_exists = 0,
  'ALTER TABLE `notification_users` ADD UNIQUE KEY `notification_user` (`notification_id`,`user_id`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
