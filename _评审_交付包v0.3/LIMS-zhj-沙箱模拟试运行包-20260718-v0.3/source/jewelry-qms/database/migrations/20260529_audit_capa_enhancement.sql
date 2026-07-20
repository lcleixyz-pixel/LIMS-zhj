-- Phase 2.2: audit evidence attachments, management review inputs, and CAPA effectiveness tracking.
DROP PROCEDURE IF EXISTS qms_add_capa_effectiveness_columns;

DELIMITER $$
CREATE PROCEDURE qms_add_capa_effectiveness_columns()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'capas'
      AND COLUMN_NAME = 'effectiveness_review_date'
  ) THEN
    ALTER TABLE `capas`
      ADD COLUMN `effectiveness_review_date` date DEFAULT NULL AFTER `verified_date`;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'capas'
      AND COLUMN_NAME = 'effectiveness_result'
  ) THEN
    ALTER TABLE `capas`
      ADD COLUMN `effectiveness_result` text AFTER `effectiveness_review_date`;
  END IF;
END$$
DELIMITER ;

CALL qms_add_capa_effectiveness_columns();

DROP PROCEDURE IF EXISTS qms_add_capa_effectiveness_columns;
