-- G-R14 本机受控试运行：模板、记录、文件换版和外部证据引用契约。
-- record_form_instances.template_version 及其他模板快照字段沿用既有迁移，不覆盖历史快照。
-- 本迁移可重复执行；所有新增列和索引均先检查 information_schema。

DROP PROCEDURE IF EXISTS qms_gr14_controlled_trial;

DELIMITER //

CREATE PROCEDURE qms_gr14_controlled_trial()
BEGIN
  IF EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'record_form_templates'
      AND COLUMN_NAME = 'status'
      AND COLUMN_TYPE NOT LIKE '%trial_ready%'
  ) THEN
    ALTER TABLE `record_form_templates`
      MODIFY COLUMN `status` enum('draft','trial_ready','published','obsolete') DEFAULT 'draft';
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'record_form_templates' AND COLUMN_NAME = 'trial_batch'
  ) THEN
    ALTER TABLE `record_form_templates`
      ADD COLUMN `trial_batch` varchar(80) DEFAULT NULL AFTER `status`;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'management_reviews' AND COLUMN_NAME = 'input_snapshot'
  ) THEN
    ALTER TABLE `management_reviews`
      ADD COLUMN `input_snapshot` longtext DEFAULT NULL AFTER `inputs`;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'record_form_templates' AND COLUMN_NAME = 'trial_approved_by'
  ) THEN
    ALTER TABLE `record_form_templates`
      ADD COLUMN `trial_approved_by` varchar(36) DEFAULT NULL AFTER `trial_batch`;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'record_form_templates' AND COLUMN_NAME = 'trial_approved_at'
  ) THEN
    ALTER TABLE `record_form_templates`
      ADD COLUMN `trial_approved_at` datetime DEFAULT NULL AFTER `trial_approved_by`;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'record_form_templates' AND COLUMN_NAME = 'trial_note'
  ) THEN
    ALTER TABLE `record_form_templates`
      ADD COLUMN `trial_note` text AFTER `trial_approved_at`;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'record_form_templates' AND COLUMN_NAME = 'canonical_doc_number'
  ) THEN
    ALTER TABLE `record_form_templates`
      ADD COLUMN `canonical_doc_number` varchar(80) DEFAULT NULL AFTER `doc_number`;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'record_form_templates' AND COLUMN_NAME = 'trial_of_template_id'
  ) THEN
    ALTER TABLE `record_form_templates`
      ADD COLUMN `trial_of_template_id` varchar(36) DEFAULT NULL AFTER `canonical_doc_number`;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'record_form_templates' AND COLUMN_NAME = 'applicable_sites'
  ) THEN
    ALTER TABLE `record_form_templates`
      ADD COLUMN `applicable_sites` varchar(500) DEFAULT NULL AFTER `module`;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'record_form_templates' AND COLUMN_NAME = 'responsible_position_code'
  ) THEN
    ALTER TABLE `record_form_templates`
      ADD COLUMN `responsible_position_code` varchar(80) DEFAULT NULL AFTER `applicable_sites`;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'record_form_templates' AND COLUMN_NAME = 'retention_period'
  ) THEN
    ALTER TABLE `record_form_templates`
      ADD COLUMN `retention_period` varchar(200) DEFAULT NULL AFTER `responsible_position_code`;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'record_form_instances' AND COLUMN_NAME = 'is_simulation'
  ) THEN
    ALTER TABLE `record_form_instances`
      ADD COLUMN `is_simulation` tinyint(1) NOT NULL DEFAULT 0 AFTER `status`;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'record_form_instances' AND COLUMN_NAME = 'trial_batch'
  ) THEN
    ALTER TABLE `record_form_instances`
      ADD COLUMN `trial_batch` varchar(80) DEFAULT NULL AFTER `is_simulation`;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'supersedes_document_id'
  ) THEN
    ALTER TABLE `documents`
      ADD COLUMN `supersedes_document_id` varchar(36) DEFAULT NULL AFTER `id`;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'revision_root_id'
  ) THEN
    ALTER TABLE `documents`
      ADD COLUMN `revision_root_id` varchar(36) DEFAULT NULL AFTER `supersedes_document_id`;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'site_id'
  ) THEN
    ALTER TABLE `documents`
      ADD COLUMN `site_id` varchar(36) DEFAULT NULL AFTER `department_id`;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'record_form_instances' AND INDEX_NAME = 'trial_batch'
  ) THEN
    ALTER TABLE `record_form_instances` ADD KEY `trial_batch` (`trial_batch`);
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND INDEX_NAME = 'supersedes_document_id'
  ) THEN
    ALTER TABLE `documents` ADD KEY `supersedes_document_id` (`supersedes_document_id`);
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND INDEX_NAME = 'site_id'
  ) THEN
    ALTER TABLE `documents` ADD KEY `site_id` (`site_id`);
  END IF;
END//

DELIMITER ;

CALL qms_gr14_controlled_trial();
DROP PROCEDURE IF EXISTS qms_gr14_controlled_trial;

CREATE TABLE IF NOT EXISTS `external_evidence_references` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `subject_type` varchar(50) NOT NULL COMMENT 'quality_event/audit/complaint/capa/management_review',
  `subject_id` varchar(36) NOT NULL,
  `source_system` varchar(120) NOT NULL,
  `object_type` varchar(120) NOT NULL,
  `external_number` varchar(160) NOT NULL,
  `display_name` varchar(300) NOT NULL,
  `readonly_url` varchar(1000) NOT NULL,
  `cited_at` datetime NOT NULL,
  `checksum_summary` varchar(255) DEFAULT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `subject_lookup` (`subject_type`,`subject_id`),
  KEY `external_lookup` (`source_system`,`object_type`,`external_number`),
  KEY `cited_at` (`cited_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
