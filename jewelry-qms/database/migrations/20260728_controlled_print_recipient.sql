SET @qms_recipient_column_exists = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'controlled_print_logs'
    AND COLUMN_NAME = 'recipient'
);
SET @qms_recipient_sql = IF(
  @qms_recipient_column_exists = 0,
  'ALTER TABLE `controlled_print_logs` ADD COLUMN `recipient` varchar(100) DEFAULT NULL AFTER `purpose`',
  'SELECT 1'
);
PREPARE qms_recipient_stmt FROM @qms_recipient_sql;
EXECUTE qms_recipient_stmt;
DEALLOCATE PREPARE qms_recipient_stmt;
