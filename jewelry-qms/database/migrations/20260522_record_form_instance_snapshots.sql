DROP PROCEDURE IF EXISTS qms_add_record_form_snapshot_columns;

DELIMITER //

CREATE PROCEDURE qms_add_record_form_snapshot_columns()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'record_form_instances'
      AND COLUMN_NAME = 'template_name'
  ) THEN
    ALTER TABLE `record_form_instances`
      ADD COLUMN `template_name` varchar(300) DEFAULT NULL AFTER `template_id`;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'record_form_instances'
      AND COLUMN_NAME = 'template_module'
  ) THEN
    ALTER TABLE `record_form_instances`
      ADD COLUMN `template_module` varchar(200) DEFAULT NULL AFTER `template_name`;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'record_form_instances'
      AND COLUMN_NAME = 'template_version'
  ) THEN
    ALTER TABLE `record_form_instances`
      ADD COLUMN `template_version` varchar(20) DEFAULT NULL AFTER `template_module`;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'record_form_instances'
      AND COLUMN_NAME = 'template_print_template_key'
  ) THEN
    ALTER TABLE `record_form_instances`
      ADD COLUMN `template_print_template_key` varchar(100) DEFAULT NULL AFTER `template_version`;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'record_form_instances'
      AND COLUMN_NAME = 'template_field_schema'
  ) THEN
    ALTER TABLE `record_form_instances`
      ADD COLUMN `template_field_schema` text AFTER `template_print_template_key`;
  END IF;
END//

DELIMITER ;

CALL qms_add_record_form_snapshot_columns();

DROP PROCEDURE IF EXISTS qms_add_record_form_snapshot_columns;

UPDATE `record_form_instances` AS r
INNER JOIN `record_form_templates` AS t ON t.`id` = r.`template_id`
SET
  r.`template_name` = COALESCE(NULLIF(r.`template_name`, ''), t.`name`),
  r.`template_module` = COALESCE(NULLIF(r.`template_module`, ''), t.`module`),
  r.`template_version` = COALESCE(NULLIF(r.`template_version`, ''), t.`version`),
  r.`template_print_template_key` = COALESCE(NULLIF(r.`template_print_template_key`, ''), t.`print_template_key`),
  r.`template_field_schema` = COALESCE(NULLIF(r.`template_field_schema`, ''), t.`field_schema`)
WHERE r.`template_field_schema` IS NULL
   OR TRIM(r.`template_field_schema`) = ''
   OR r.`template_print_template_key` IS NULL
   OR TRIM(r.`template_print_template_key`) = '';
