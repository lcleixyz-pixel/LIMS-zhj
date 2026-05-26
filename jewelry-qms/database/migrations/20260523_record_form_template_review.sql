DROP PROCEDURE IF EXISTS qms_add_record_form_template_review_columns;

DELIMITER //

CREATE PROCEDURE qms_add_record_form_template_review_columns()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'record_form_templates'
      AND COLUMN_NAME = 'review_status'
  ) THEN
    ALTER TABLE `record_form_templates`
      ADD COLUMN `review_status` enum('pending','field_confirmed','needs_fidelity','deferred','completed') DEFAULT 'pending' AFTER `status`;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'record_form_templates'
      AND COLUMN_NAME = 'review_note'
  ) THEN
    ALTER TABLE `record_form_templates`
      ADD COLUMN `review_note` text AFTER `review_status`;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'record_form_templates'
      AND COLUMN_NAME = 'reviewed_at'
  ) THEN
    ALTER TABLE `record_form_templates`
      ADD COLUMN `reviewed_at` datetime DEFAULT NULL AFTER `review_note`;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'record_form_templates'
      AND INDEX_NAME = 'review_status'
  ) THEN
    ALTER TABLE `record_form_templates`
      ADD KEY `review_status` (`review_status`);
  END IF;
END//

DELIMITER ;

CALL qms_add_record_form_template_review_columns();

DROP PROCEDURE IF EXISTS qms_add_record_form_template_review_columns;

UPDATE `record_form_templates`
SET `review_status` = 'pending'
WHERE `review_status` IS NULL
   OR TRIM(`review_status`) = '';
