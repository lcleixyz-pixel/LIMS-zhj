-- 字段级记录更正：申请时冻结目标路径、原值和拟更正值，批准后只向更正链追加。
-- 不修改 record_form_instances.field_values，也不覆盖原 PDF。

CREATE TABLE IF NOT EXISTS `record_form_correction_requests` (
  `id` varchar(40) NOT NULL,
  `company_id` varchar(40) NOT NULL,
  `record_id` varchar(40) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `correction_type` varchar(30) NOT NULL DEFAULT 'supplement',
  `target_kind` varchar(30) NOT NULL,
  `field_path` varchar(255) NOT NULL,
  `field_key` varchar(100) DEFAULT NULL,
  `field_label` varchar(255) NOT NULL,
  `row_index` int DEFAULT NULL,
  `column_key` varchar(100) DEFAULT NULL,
  `column_label` varchar(255) DEFAULT NULL,
  `original_content` text,
  `corrected_content` text NOT NULL,
  `row_payload_json` longtext,
  `reason` text NOT NULL,
  `requested_by` varchar(40) DEFAULT NULL,
  `requested_at` datetime NOT NULL,
  `decided_by` varchar(40) DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `decision_comment` text,
  `decision_notification_id` varchar(40) DEFAULT NULL,
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(40) DEFAULT NULL,
  `modified_by` varchar(40) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `record_id` (`record_id`),
  KEY `status` (`status`),
  KEY `requested_at` (`requested_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @qms_schema_name = DATABASE();

SET @qms_sql = IF(
  (SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = @qms_schema_name AND table_name = 'record_form_corrections' AND column_name = 'target_kind') = 0,
  'ALTER TABLE `record_form_corrections` ADD COLUMN `target_kind` varchar(30) NOT NULL DEFAULT ''legacy_note'' AFTER `correction_type`',
  'SELECT 1'
);
PREPARE qms_stmt FROM @qms_sql;
EXECUTE qms_stmt;
DEALLOCATE PREPARE qms_stmt;

SET @qms_sql = IF(
  (SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = @qms_schema_name AND table_name = 'record_form_corrections' AND column_name = 'field_path') = 0,
  'ALTER TABLE `record_form_corrections` ADD COLUMN `field_path` varchar(255) DEFAULT NULL AFTER `target_kind`',
  'SELECT 1'
);
PREPARE qms_stmt FROM @qms_sql;
EXECUTE qms_stmt;
DEALLOCATE PREPARE qms_stmt;

SET @qms_sql = IF(
  (SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = @qms_schema_name AND table_name = 'record_form_corrections' AND column_name = 'field_key') = 0,
  'ALTER TABLE `record_form_corrections` ADD COLUMN `field_key` varchar(100) DEFAULT NULL AFTER `field_path`',
  'SELECT 1'
);
PREPARE qms_stmt FROM @qms_sql;
EXECUTE qms_stmt;
DEALLOCATE PREPARE qms_stmt;

SET @qms_sql = IF(
  (SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = @qms_schema_name AND table_name = 'record_form_corrections' AND column_name = 'field_label') = 0,
  'ALTER TABLE `record_form_corrections` ADD COLUMN `field_label` varchar(255) DEFAULT NULL AFTER `field_key`',
  'SELECT 1'
);
PREPARE qms_stmt FROM @qms_sql;
EXECUTE qms_stmt;
DEALLOCATE PREPARE qms_stmt;

SET @qms_sql = IF(
  (SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = @qms_schema_name AND table_name = 'record_form_corrections' AND column_name = 'row_index') = 0,
  'ALTER TABLE `record_form_corrections` ADD COLUMN `row_index` int DEFAULT NULL AFTER `field_label`',
  'SELECT 1'
);
PREPARE qms_stmt FROM @qms_sql;
EXECUTE qms_stmt;
DEALLOCATE PREPARE qms_stmt;

SET @qms_sql = IF(
  (SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = @qms_schema_name AND table_name = 'record_form_corrections' AND column_name = 'column_key') = 0,
  'ALTER TABLE `record_form_corrections` ADD COLUMN `column_key` varchar(100) DEFAULT NULL AFTER `row_index`',
  'SELECT 1'
);
PREPARE qms_stmt FROM @qms_sql;
EXECUTE qms_stmt;
DEALLOCATE PREPARE qms_stmt;

SET @qms_sql = IF(
  (SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = @qms_schema_name AND table_name = 'record_form_corrections' AND column_name = 'column_label') = 0,
  'ALTER TABLE `record_form_corrections` ADD COLUMN `column_label` varchar(255) DEFAULT NULL AFTER `column_key`',
  'SELECT 1'
);
PREPARE qms_stmt FROM @qms_sql;
EXECUTE qms_stmt;
DEALLOCATE PREPARE qms_stmt;

SET @qms_sql = IF(
  (SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = @qms_schema_name AND table_name = 'record_form_corrections' AND column_name = 'row_payload_json') = 0,
  'ALTER TABLE `record_form_corrections` ADD COLUMN `row_payload_json` longtext AFTER `column_label`',
  'SELECT 1'
);
PREPARE qms_stmt FROM @qms_sql;
EXECUTE qms_stmt;
DEALLOCATE PREPARE qms_stmt;

UPDATE `record_form_corrections`
SET
  `target_kind` = 'legacy_note',
  `field_label` = COALESCE(NULLIF(`field_label`, ''), '整表补充说明')
WHERE `target_kind` IS NULL
   OR `target_kind` = ''
   OR (`target_kind` = 'legacy_note' AND (`field_label` IS NULL OR `field_label` = ''));
