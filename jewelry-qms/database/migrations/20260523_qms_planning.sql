-- 体系策划中心重构：无编号要素、独立条款层、独立手册章节层
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `qms_requirement_elements`;
DROP TABLE IF EXISTS `qms_element_clause_mappings`;
DROP TABLE IF EXISTS `qms_responsibility_matrix`;
DROP TABLE IF EXISTS `qms_document_sections`;
DROP TABLE IF EXISTS `qms_trace_links`;
DROP TABLE IF EXISTS `qms_import_candidates`;
DROP TABLE IF EXISTS `qms_import_batches`;

CREATE TABLE IF NOT EXISTS `qms_sources` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `source_code` varchar(80) NOT NULL COMMENT '依据编号',
  `name` varchar(300) NOT NULL,
  `source_type` varchar(60) DEFAULT 'external_standard',
  `version` varchar(80) DEFAULT NULL,
  `effective_date` date DEFAULT NULL,
  `attachment_file_path` varchar(500) DEFAULT NULL,
  `attachment_file_name` varchar(255) DEFAULT NULL,
  `freshness_checked_at` date DEFAULT NULL COMMENT '最近查新日期',
  `freshness_result` varchar(300) DEFAULT NULL COMMENT '查新结论',
  `freshness_evidence` varchar(500) DEFAULT NULL COMMENT '查新证据来源',
  `next_freshness_due` date DEFAULT NULL COMMENT '下次查新日期',
  `freshness_status` enum('unknown','current','due','obsolete') DEFAULT 'unknown',
  `status` enum('draft','published','obsolete') DEFAULT 'draft',
  `review_note` text,
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `source_code` (`source_code`),
  KEY `freshness_status` (`freshness_status`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @schema_name = DATABASE();
SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @schema_name AND table_name = 'record_form_templates' AND column_name = 'element_id') = 0,
  'ALTER TABLE `record_form_templates` ADD COLUMN `element_id` varchar(36) DEFAULT NULL COMMENT ''关联体系要素''',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @schema_name AND table_name = 'record_form_templates' AND column_name = 'procedure_doc_id') = 0,
  'ALTER TABLE `record_form_templates` ADD COLUMN `procedure_doc_id` varchar(36) DEFAULT NULL COMMENT ''关联程序文件 documents.id''',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = @schema_name AND table_name = 'record_form_templates' AND index_name = 'element_id') = 0,
  'ALTER TABLE `record_form_templates` ADD INDEX `element_id` (`element_id`)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = @schema_name AND table_name = 'record_form_templates' AND index_name = 'procedure_doc_id') = 0,
  'ALTER TABLE `record_form_templates` ADD INDEX `procedure_doc_id` (`procedure_doc_id`)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @schema_name AND table_name = 'qms_sources' AND column_name = 'freshness_checked_at') = 0,
  'ALTER TABLE `qms_sources` ADD COLUMN `freshness_checked_at` date DEFAULT NULL COMMENT ''最近查新日期''',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @schema_name AND table_name = 'qms_sources' AND column_name = 'freshness_result') = 0,
  'ALTER TABLE `qms_sources` ADD COLUMN `freshness_result` varchar(300) DEFAULT NULL COMMENT ''查新结论''',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @schema_name AND table_name = 'qms_sources' AND column_name = 'freshness_evidence') = 0,
  'ALTER TABLE `qms_sources` ADD COLUMN `freshness_evidence` varchar(500) DEFAULT NULL COMMENT ''查新证据来源''',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @schema_name AND table_name = 'qms_sources' AND column_name = 'next_freshness_due') = 0,
  'ALTER TABLE `qms_sources` ADD COLUMN `next_freshness_due` date DEFAULT NULL COMMENT ''下次查新日期''',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @schema_name AND table_name = 'qms_sources' AND column_name = 'freshness_status') = 0,
  'ALTER TABLE `qms_sources` ADD COLUMN `freshness_status` enum(''unknown'',''current'',''due'',''obsolete'') DEFAULT ''unknown''',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = @schema_name AND table_name = 'qms_sources' AND index_name = 'freshness_status') = 0,
  'ALTER TABLE `qms_sources` ADD INDEX `freshness_status` (`freshness_status`)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `qms_clauses` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `source_id` varchar(36) NOT NULL,
  `parent_id` varchar(36) DEFAULT NULL,
  `clause_number` varchar(80) NOT NULL,
  `title` varchar(500) NOT NULL,
  `level` tinyint(2) DEFAULT 1,
  `page_number` int DEFAULT NULL,
  `locator` varchar(255) DEFAULT NULL,
  `applicability` enum('applicable','not_applicable','conditional') DEFAULT 'applicable',
  `review_status` enum('draft','published','obsolete') DEFAULT 'published',
  `summary` text,
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `source_clause` (`source_id`,`clause_number`),
  KEY `parent_id` (`parent_id`),
  KEY `review_status` (`review_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `qms_clause_texts` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `clause_id` varchar(36) NOT NULL,
  `source_id` varchar(36) NOT NULL,
  `clause_number` varchar(80) NOT NULL,
  `original_text` mediumtext NOT NULL,
  `locator` varchar(255) DEFAULT NULL,
  `page_number` int DEFAULT NULL,
  `text_hash` varchar(64) DEFAULT NULL,
  `extraction_method` varchar(80) DEFAULT 'manual',
  `review_status` enum('draft','published','obsolete') DEFAULT 'published',
  `review_note` text,
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `clause_text` (`clause_id`),
  KEY `source_clause` (`source_id`,`clause_number`),
  KEY `review_status` (`review_status`),
  KEY `text_hash` (`text_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `qms_elements` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `key` varchar(80) NOT NULL COMMENT '不可见稳定键',
  `name` varchar(200) NOT NULL COMMENT '无编号体系要素名称',
  `parent_id` varchar(36) DEFAULT NULL,
  `element_type` enum('management','technical') DEFAULT 'management',
  `applicability` enum('applicable','not_applicable','conditional') DEFAULT 'applicable',
  `applicability_note` text,
  `owner_position_id` varchar(36) DEFAULT NULL,
  `source_basis` varchar(200) DEFAULT NULL,
  `summary` text,
  `status` enum('draft','effective','under_review') DEFAULT 'draft',
  `sort_order` int DEFAULT 0,
  `last_reviewed_at` datetime DEFAULT NULL,
  `next_review_due` date DEFAULT NULL,
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `element_key` (`key`),
  KEY `parent_id` (`parent_id`),
  KEY `element_type` (`element_type`),
  KEY `owner_position_id` (`owner_position_id`),
  KEY `status` (`status`),
  KEY `sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `qms_element_clause_links` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `element_id` varchar(36) NOT NULL,
  `clause_id` varchar(36) NOT NULL,
  `mapping_type` enum('equivalent','partial','supplement','reference') DEFAULT 'equivalent',
  `is_primary` tinyint(1) DEFAULT 0 COMMENT '主27025条款，用于默认排序',
  `note` text,
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `element_clause` (`element_id`,`clause_id`),
  KEY `element_id` (`element_id`),
  KEY `clause_id` (`clause_id`),
  KEY `is_primary` (`is_primary`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `qms_positions` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `code` varchar(100) NOT NULL,
  `name` varchar(200) NOT NULL,
  `department_hint` varchar(200) DEFAULT NULL,
  `source` varchar(120) DEFAULT NULL,
  `description` text,
  `review_status` enum('draft','published','obsolete') DEFAULT 'published',
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `review_status` (`review_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `qms_element_documents` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `element_id` varchar(36) NOT NULL,
  `document_id` varchar(36) NOT NULL,
  `relation_type` enum('primary','reference') DEFAULT 'primary',
  `note` text,
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `element_document` (`element_id`,`document_id`),
  KEY `document_id` (`document_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `qms_element_responsibilities` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `element_id` varchar(36) NOT NULL,
  `position_id` varchar(36) NOT NULL,
  `responsibility_type` enum('decision_owner','organizer','participant') NOT NULL,
  `note` text,
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `element_position_type` (`element_id`,`position_id`,`responsibility_type`),
  KEY `position_id` (`position_id`),
  KEY `responsibility_type` (`responsibility_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `qms_manual_sections` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `document_id` varchar(36) DEFAULT NULL,
  `element_id` varchar(36) DEFAULT NULL,
  `parent_id` varchar(36) DEFAULT NULL,
  `section_number` varchar(80) NOT NULL,
  `title` varchar(500) NOT NULL,
  `level` tinyint(2) DEFAULT 1,
  `summary` text,
  `status` enum('draft','effective','obsolete') DEFAULT 'effective',
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `manual_section` (`document_id`,`section_number`),
  KEY `element_id` (`element_id`),
  KEY `section_number` (`section_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `qms_business_modules` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `code` varchar(80) NOT NULL,
  `name` varchar(200) NOT NULL,
  `controller_name` varchar(100) DEFAULT NULL,
  `primary_element_id` varchar(36) DEFAULT NULL,
  `url` varchar(200) DEFAULT NULL,
  `description` text,
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `module_code` (`code`),
  KEY `primary_element_id` (`primary_element_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `qms_business_module_elements` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `module_id` varchar(36) NOT NULL,
  `element_id` varchar(36) NOT NULL,
  `relation_type` enum('primary','supporting') DEFAULT 'supporting',
  `note` text,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `module_element` (`module_id`,`element_id`),
  KEY `element_id` (`element_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `qms_agent_suggestions` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `element_id` varchar(36) DEFAULT NULL,
  `suggestion_type` enum('gap','mapping','document','record','module','responsibility') DEFAULT 'gap',
  `title` varchar(300) NOT NULL,
  `content` text,
  `evidence` text,
  `status` enum('open','accepted','rejected') DEFAULT 'open',
  `review_note` text,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `element_id` (`element_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `qms_reference_procedure_matches` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `reference_doc_number` varchar(80) NOT NULL,
  `reference_section_number` varchar(80) NOT NULL,
  `reference_title` varchar(300) NOT NULL,
  `reference_block_id` varchar(36) DEFAULT NULL,
  `procedure_document_id` varchar(36) NOT NULL,
  `match_source` enum('manual') DEFAULT 'manual',
  `status` enum('active','retired') DEFAULT 'active',
  `review_note` text NOT NULL,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  `soft_delete` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference_manual_match` (`reference_doc_number`,`reference_section_number`,`status`,`soft_delete`),
  KEY `procedure_document_id` (`procedure_document_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `qms_document_assets` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `source_kind` enum('external_basis','quality_manual','procedure','work_instruction','record_form','reference_file') NOT NULL,
  `document_id` varchar(36) DEFAULT NULL,
  `source_id` varchar(36) DEFAULT NULL,
  `record_form_template_id` varchar(36) DEFAULT NULL,
  `original_name` varchar(255) NOT NULL,
  `original_path` varchar(500) NOT NULL,
  `normalized_name` varchar(255) NOT NULL,
  `archived_path` varchar(500) DEFAULT NULL,
  `file_type` varchar(20) DEFAULT NULL,
  `file_sha256` char(64) DEFAULT NULL,
  `archive_status` enum('pending','archived','missing') DEFAULT 'pending',
  `extracted_at` datetime DEFAULT NULL,
  `extracted_text_hash` char(64) DEFAULT NULL,
  `markdown_path` varchar(500) DEFAULT NULL,
  `review_status` enum('draft','structured','published','obsolete') DEFAULT 'draft',
  `source_note` text,
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `asset_record_form_template` (`source_kind`,`record_form_template_id`),
  KEY `asset_original_path_lookup` (`source_kind`,`original_path`),
  KEY `document_id` (`document_id`),
  KEY `source_id` (`source_id`),
  KEY `record_form_template_id` (`record_form_template_id`),
  KEY `archive_status` (`archive_status`),
  KEY `review_status` (`review_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = @schema_name AND table_name = 'qms_document_assets' AND index_name = 'asset_original_path' AND non_unique = 0) > 0,
  'ALTER TABLE `qms_document_assets` DROP INDEX `asset_original_path`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = @schema_name AND table_name = 'qms_document_assets' AND index_name = 'asset_original_path_lookup') = 0,
  'ALTER TABLE `qms_document_assets` ADD KEY `asset_original_path_lookup` (`source_kind`,`original_path`)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = @schema_name AND table_name = 'qms_document_assets' AND index_name = 'asset_record_form_template') = 0,
  'ALTER TABLE `qms_document_assets` ADD UNIQUE KEY `asset_record_form_template` (`source_kind`,`record_form_template_id`)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `qms_structured_documents` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `source_asset_id` varchar(36) DEFAULT NULL,
  `document_id` varchar(36) DEFAULT NULL,
  `document_role` enum('external_basis','quality_manual','procedure','work_instruction','record_form') NOT NULL,
  `doc_number` varchar(80) NOT NULL,
  `title` varchar(300) NOT NULL,
  `version` varchar(80) DEFAULT NULL,
  `source_status` enum('current','reference','draft') DEFAULT 'current',
  `markdown_path` varchar(500) DEFAULT NULL,
  `rendered_file_path` varchar(500) DEFAULT NULL,
  `render_status` enum('not_rendered','rendered','archived') DEFAULT 'not_rendered',
  `status` enum('draft','structured','published','obsolete') DEFAULT 'structured',
  `review_note` text,
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `structured_document_source_asset` (`document_role`,`source_asset_id`),
  KEY `source_asset_id` (`source_asset_id`),
  KEY `document_id` (`document_id`),
  KEY `document_role` (`document_role`),
  KEY `structured_document_lookup` (`document_role`,`doc_number`,`version`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = @schema_name AND table_name = 'qms_structured_documents' AND index_name = 'structured_document') > 0,
  'ALTER TABLE `qms_structured_documents` DROP INDEX `structured_document`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = @schema_name AND table_name = 'qms_structured_documents' AND index_name = 'structured_document_source_asset') = 0,
  'ALTER TABLE `qms_structured_documents` ADD UNIQUE KEY `structured_document_source_asset` (`document_role`,`source_asset_id`)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = @schema_name AND table_name = 'qms_structured_documents' AND index_name = 'structured_document_lookup') = 0,
  'ALTER TABLE `qms_structured_documents` ADD KEY `structured_document_lookup` (`document_role`,`doc_number`,`version`)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `qms_document_blocks` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `structured_document_id` varchar(36) NOT NULL,
  `document_id` varchar(36) DEFAULT NULL,
  `parent_id` varchar(36) DEFAULT NULL,
  `stable_key` varchar(160) NOT NULL,
  `section_number` varchar(80) DEFAULT NULL,
  `title` varchar(300) NOT NULL,
  `block_type` enum('section','purpose','scope','responsibility','process_step','control_requirement','record_requirement','form_schema','clause_trace','text') DEFAULT 'text',
  `markdown` mediumtext NOT NULL,
  `sort_order` int DEFAULT 0,
  `source_locator` varchar(255) DEFAULT NULL,
  `status` enum('draft','effective','obsolete') DEFAULT 'effective',
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `structured_block` (`structured_document_id`,`stable_key`),
  KEY `document_id` (`document_id`),
  KEY `parent_id` (`parent_id`),
  KEY `block_type` (`block_type`),
  KEY `sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `qms_document_block_links` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `block_id` varchar(36) NOT NULL,
  `element_id` varchar(36) DEFAULT NULL,
  `clause_id` varchar(36) DEFAULT NULL,
  `manual_section_id` varchar(36) DEFAULT NULL,
  `procedure_document_id` varchar(36) DEFAULT NULL,
  `record_form_template_id` varchar(36) DEFAULT NULL,
  `position_id` varchar(36) DEFAULT NULL,
  `business_module_id` varchar(36) DEFAULT NULL,
  `relation_type` enum('basis','implements','mentions','responsible','requires_record','renders_to','supporting') DEFAULT 'implements',
  `confidence` enum('high','medium','low','review_required') DEFAULT 'medium',
  `note` text,
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `block_id` (`block_id`),
  KEY `element_id` (`element_id`),
  KEY `clause_id` (`clause_id`),
  KEY `manual_section_id` (`manual_section_id`),
  KEY `procedure_document_id` (`procedure_document_id`),
  KEY `record_form_template_id` (`record_form_template_id`),
  KEY `position_id` (`position_id`),
  KEY `business_module_id` (`business_module_id`),
  KEY `relation_type` (`relation_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `qms_document_change_logs` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `structured_document_id` varchar(36) NOT NULL,
  `block_id` varchar(36) DEFAULT NULL,
  `document_id` varchar(36) DEFAULT NULL,
  `change_type` enum('block_update','render','status_change','version_update') DEFAULT 'block_update',
  `revision_note` text NOT NULL,
  `old_markdown_sha256` char(64) DEFAULT NULL,
  `new_markdown_sha256` char(64) DEFAULT NULL,
  `old_excerpt` text,
  `new_excerpt` text,
  `rendered_file_path` varchar(500) DEFAULT NULL,
  `archive_path` varchar(500) DEFAULT NULL,
  `trace_snapshot_json` mediumtext,
  `status_from` varchar(80) DEFAULT NULL,
  `status_to` varchar(80) DEFAULT NULL,
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `structured_document_id` (`structured_document_id`),
  KEY `block_id` (`block_id`),
  KEY `document_id` (`document_id`),
  KEY `change_type` (`change_type`),
  KEY `created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @schema_name AND table_name = 'qms_document_change_logs' AND column_name = 'trace_snapshot_json') = 0,
  'ALTER TABLE `qms_document_change_logs` ADD COLUMN `trace_snapshot_json` mediumtext DEFAULT NULL AFTER `archive_path`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `qms_quality_policies` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `title` varchar(300) NOT NULL,
  `policy_text` text NOT NULL,
  `version` varchar(80) DEFAULT NULL,
  `effective_date` date DEFAULT NULL,
  `source_document_id` varchar(36) DEFAULT NULL,
  `is_current` tinyint(1) DEFAULT 0,
  `management_review_input` tinyint(1) DEFAULT 1,
  `review_status` enum('candidate','draft','pending_review','published','rejected','obsolete') DEFAULT 'draft',
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `is_current` (`is_current`),
  KEY `review_status` (`review_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `qms_quality_objectives` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `policy_id` varchar(36) DEFAULT NULL,
  `year` smallint DEFAULT NULL,
  `department_id` varchar(36) DEFAULT NULL,
  `position_id` varchar(36) DEFAULT NULL,
  `title` varchar(300) NOT NULL,
  `metric_name` varchar(200) DEFAULT NULL,
  `target_value` varchar(120) DEFAULT NULL,
  `unit` varchar(40) DEFAULT NULL,
  `statistic_cycle` enum('monthly','quarterly','semiannual','annual','event') DEFAULT 'annual',
  `responsible_department` varchar(200) DEFAULT NULL,
  `responsible_position` varchar(200) DEFAULT NULL,
  `management_review_input` tinyint(1) DEFAULT 1,
  `review_status` enum('candidate','draft','pending_review','published','rejected','obsolete') DEFAULT 'draft',
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `year` (`year`),
  KEY `department_id` (`department_id`),
  KEY `review_status` (`review_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
