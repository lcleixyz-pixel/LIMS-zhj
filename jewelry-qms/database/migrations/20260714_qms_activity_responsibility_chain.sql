-- 活动级责任链：版本、活动、职责、人员草案、签批与岗位别名
-- 幂等：兼容 MySQL 8.4，不依赖 ADD COLUMN IF NOT EXISTS。
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `qms_responsibility_chain_versions` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `chain_code` varchar(100) NOT NULL,
  `version_no` int NOT NULL,
  `status` enum('draft','pending_approval','effective','superseded','revoked') NOT NULL DEFAULT 'draft',
  `content_hash` char(64) DEFAULT NULL,
  `replaces_version_id` varchar(36) DEFAULT NULL,
  `locked_at` datetime DEFAULT NULL,
  `effective_at` datetime DEFAULT NULL,
  `superseded_at` datetime DEFAULT NULL,
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_chain_version` (`company_id`,`chain_code`,`version_no`),
  KEY `chain_status` (`chain_code`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `qms_responsibility_activities` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `chain_version_id` varchar(36) NOT NULL,
  `activity_code` varchar(100) NOT NULL,
  `name` varchar(200) NOT NULL,
  `element_key` varchar(80) DEFAULT NULL,
  `site_scope` enum('all','site') NOT NULL DEFAULT 'all',
  `source_refs` json DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT 0,
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chain_activity` (`chain_version_id`,`activity_code`),
  KEY `chain_version_id` (`chain_version_id`),
  KEY `element_key` (`element_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `qms_activity_responsibilities` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `activity_id` varchar(36) NOT NULL,
  `step_code` varchar(200) NOT NULL,
  `duty_type` enum('organize','review','approve','execute','verify','record_keep','countersign','inform') NOT NULL,
  `duty_text` text NOT NULL,
  `slot_kind` enum('fixed_position','activity_role','dynamic_owner') NOT NULL,
  `assignment_mode` enum('named_person','activity_instance','derived_from_scope') NOT NULL,
  `fixed_position_id` varchar(36) DEFAULT NULL,
  `activity_role_code` varchar(100) DEFAULT NULL,
  `dynamic_owner_code` varchar(100) DEFAULT NULL,
  `required` tinyint(1) NOT NULL DEFAULT 1,
  `eligibility_rule` json DEFAULT NULL,
  `rule_codes` json DEFAULT NULL,
  `source_refs` json DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT 0,
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `activity_step` (`activity_id`,`step_code`),
  KEY `activity_id` (`activity_id`),
  KEY `fixed_position_id` (`fixed_position_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `qms_responsibility_assignments` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `responsibility_id` varchar(36) NOT NULL,
  `employee_id` varchar(36) NOT NULL,
  `site_id` varchar(36) DEFAULT NULL,
  `site_scope_key` varchar(36) NOT NULL DEFAULT '*',
  `proposed_from` date DEFAULT NULL,
  `proposed_until` date DEFAULT NULL,
  `competence_snapshot` json DEFAULT NULL,
  `validation_status` enum('pass','warning','blocker') DEFAULT NULL,
  `validation_details` json DEFAULT NULL,
  `status` enum('draft','pending_approval','active','revoked','expired') NOT NULL DEFAULT 'draft',
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `responsibility_employee_scope` (`responsibility_id`,`employee_id`,`site_scope_key`),
  KEY `responsibility_id` (`responsibility_id`),
  KEY `employee_id` (`employee_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `qms_responsibility_approvals` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `approval_scope` enum('governance_bootstrap','chain_version','assignment') NOT NULL,
  `chain_version_id` varchar(36) DEFAULT NULL,
  `assignment_id` varchar(36) DEFAULT NULL,
  `subject_employee_id` varchar(36) DEFAULT NULL,
  `subject_position_id` varchar(36) DEFAULT NULL,
  `approver_user_id` varchar(36) DEFAULT NULL,
  `approver_employee_id` varchar(36) DEFAULT NULL,
  `approver_position_code` varchar(100) DEFAULT NULL,
  `batch_key` varchar(64) DEFAULT NULL,
  `decision` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `comments` text,
  `version_hash` char(64) DEFAULT NULL,
  `signature_metadata` json DEFAULT NULL,
  `signed_at` datetime DEFAULT NULL,
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `chain_decision` (`chain_version_id`,`decision`),
  KEY `approver_decision` (`approver_employee_id`,`decision`),
  KEY `batch_key` (`batch_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `qms_position_aliases` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `position_id` varchar(36) NOT NULL,
  `alias` varchar(200) NOT NULL,
  `confirmation_status` enum('confirmed','review_required') NOT NULL DEFAULT 'review_required',
  `source_scope` varchar(100) NOT NULL DEFAULT 'position_catalog',
  `site_id` varchar(36) DEFAULT NULL,
  `site_scope_key` varchar(36) NOT NULL DEFAULT '*',
  `confirmation_note` text,
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_alias_scope` (`company_id`,`alias`,`source_scope`,`site_scope_key`),
  KEY `position_id` (`position_id`),
  KEY `confirmation_status` (`confirmation_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @schema_name = DATABASE();

SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @schema_name AND table_name = 'employee_appointments' AND column_name = 'source_kind') = 0,
  'ALTER TABLE `employee_appointments` ADD COLUMN `source_kind` enum(''legacy_document'',''corporate_evidence'',''responsibility_chain'') NOT NULL DEFAULT ''legacy_document'' AFTER `source_excerpt`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @schema_name AND table_name = 'employee_appointments' AND column_name = 'source_chain_version_id') = 0,
  'ALTER TABLE `employee_appointments` ADD COLUMN `source_chain_version_id` varchar(36) DEFAULT NULL AFTER `source_kind`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @schema_name AND table_name = 'employee_appointments' AND column_name = 'source_responsibility_id') = 0,
  'ALTER TABLE `employee_appointments` ADD COLUMN `source_responsibility_id` varchar(36) DEFAULT NULL AFTER `source_chain_version_id`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @schema_name AND table_name = 'employee_appointments' AND column_name = 'source_approval_id') = 0,
  'ALTER TABLE `employee_appointments` ADD COLUMN `source_approval_id` varchar(36) DEFAULT NULL AFTER `source_responsibility_id`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = @schema_name AND table_name = 'employee_appointments' AND index_name = 'source_chain_version_id') = 0,
  'ALTER TABLE `employee_appointments` ADD KEY `source_chain_version_id` (`source_chain_version_id`)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = @schema_name AND table_name = 'employee_appointments' AND index_name = 'source_responsibility_id') = 0,
  'ALTER TABLE `employee_appointments` ADD KEY `source_responsibility_id` (`source_responsibility_id`)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = @schema_name AND table_name = 'employee_appointments' AND index_name = 'source_approval_id') = 0,
  'ALTER TABLE `employee_appointments` ADD KEY `source_approval_id` (`source_approval_id`)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @schema_name AND table_name = 'employee_appointments' AND column_name = 'status' AND column_type LIKE '%revoked%') = 0,
  'ALTER TABLE `employee_appointments` MODIFY COLUMN `status` enum(''active'',''inactive'',''expired'',''revoked'') DEFAULT ''active''',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
