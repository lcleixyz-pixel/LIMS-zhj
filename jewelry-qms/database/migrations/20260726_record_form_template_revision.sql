-- 记录表格模板换版链：旧版保留，新版从修订草稿开始。
-- 可重复执行；不修改任何现有模板状态或记录实例。

DROP PROCEDURE IF EXISTS qms_record_form_template_revision;

DELIMITER //

CREATE PROCEDURE qms_record_form_template_revision()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'record_form_templates'
      AND COLUMN_NAME = 'supersedes_template_id'
  ) THEN
    ALTER TABLE `record_form_templates`
      ADD COLUMN `supersedes_template_id` varchar(36) DEFAULT NULL
      COMMENT '本修订版直接替代的上一模板版本'
      AFTER `trial_of_template_id`;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'record_form_templates'
      AND COLUMN_NAME = 'revision_root_id'
  ) THEN
    ALTER TABLE `record_form_templates`
      ADD COLUMN `revision_root_id` varchar(36) DEFAULT NULL
      COMMENT '模板版本链首版 ID'
      AFTER `supersedes_template_id`;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'record_form_templates'
      AND COLUMN_NAME = 'revision_note'
  ) THEN
    ALTER TABLE `record_form_templates`
      ADD COLUMN `revision_note` text
      COMMENT '建立本修订草稿时填写的修订说明'
      AFTER `revision_root_id`;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'record_form_templates'
      AND INDEX_NAME = 'supersedes_template_id'
  ) THEN
    ALTER TABLE `record_form_templates`
      ADD KEY `supersedes_template_id` (`supersedes_template_id`);
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'record_form_templates'
      AND INDEX_NAME = 'revision_root_id'
  ) THEN
    ALTER TABLE `record_form_templates`
      ADD KEY `revision_root_id` (`revision_root_id`);
  END IF;
END//

DELIMITER ;

CALL qms_record_form_template_revision();
DROP PROCEDURE IF EXISTS qms_record_form_template_revision;
