-- D：签批资产 source_kind + 签批轮次表 + 可选 metadata
SET @schema_name = DATABASE();

-- 扩展 qms_document_assets.source_kind 增加 signed_document
SET @col_type = (
  SELECT COLUMN_TYPE FROM information_schema.columns
  WHERE table_schema = @schema_name AND table_name = 'qms_document_assets' AND column_name = 'source_kind'
  LIMIT 1
);
SET @sql = IF(
  @col_type IS NOT NULL AND @col_type NOT LIKE '%signed_document%',
  'ALTER TABLE `qms_document_assets` MODIFY COLUMN `source_kind` enum(''external_basis'',''quality_manual'',''procedure'',''work_instruction'',''record_form'',''reference_file'',''signed_document'') NOT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 可选 metadata JSON（若不存在则加）
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = @schema_name AND table_name = 'qms_document_assets' AND column_name = 'metadata_json') = 0,
  'ALTER TABLE `qms_document_assets` ADD COLUMN `metadata_json` json DEFAULT NULL COMMENT ''签批/来源扩展元数据'' AFTER `source_note`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `document_signing_rounds` (
  `id` varchar(36) NOT NULL,
  `document_id` varchar(36) NOT NULL,
  `round_no` int NOT NULL DEFAULT 1,
  `decision` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `submission_id` varchar(100) DEFAULT NULL,
  `note` text,
  `created` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `document_id` (`document_id`),
  KEY `document_decision` (`document_id`,`decision`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
