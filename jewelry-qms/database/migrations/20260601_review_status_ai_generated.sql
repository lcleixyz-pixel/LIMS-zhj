DROP PROCEDURE IF EXISTS qms_add_ai_generated_review_status;

DELIMITER //

CREATE PROCEDURE qms_add_ai_generated_review_status()
BEGIN
  DECLARE current_type VARCHAR(1024);

  SELECT COLUMN_TYPE INTO current_type
    FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'record_form_templates'
     AND COLUMN_NAME = 'review_status';

  IF current_type IS NOT NULL AND LOCATE('ai_generated', current_type) = 0 THEN
    ALTER TABLE `record_form_templates`
      MODIFY COLUMN `review_status`
        enum('pending','ai_generated','field_confirmed','needs_fidelity','deferred','completed')
        DEFAULT 'pending';
  END IF;
END//

DELIMITER ;

CALL qms_add_ai_generated_review_status();

DROP PROCEDURE IF EXISTS qms_add_ai_generated_review_status;
