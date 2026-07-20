SET @column_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'record_form_templates'
      AND COLUMN_NAME = 'source_file_sha1'
);
SET @sql := IF(@column_exists = 0,
    'ALTER TABLE `record_form_templates` ADD COLUMN `source_file_sha1` char(40) DEFAULT NULL AFTER `source_file_name`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE `record_form_templates`
SET `source_file_sha1` = NULL
WHERE `source_file_sha1` IS NOT NULL
  AND `source_file_sha1` NOT REGEXP '^[0-9a-f]{40}$';

SET @index_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'record_form_templates'
      AND INDEX_NAME = 'source_file_sha1'
);
SET @sql := IF(@index_exists = 0,
    'ALTER TABLE `record_form_templates` ADD KEY `source_file_sha1` (`source_file_sha1`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
