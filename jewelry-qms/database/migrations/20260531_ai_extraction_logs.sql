CREATE TABLE IF NOT EXISTS `ai_extraction_logs` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `source_file` varchar(500) NOT NULL,
  `target_type` varchar(50) NOT NULL,
  `extracted_json` json DEFAULT NULL,
  `status` enum('pending','confirmed','rejected') DEFAULT 'pending',
  `records_created` int DEFAULT 0,
  `created_by` varchar(36) DEFAULT NULL,
  `confirmed_by` varchar(36) DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `target_type` (`target_type`),
  KEY `status` (`status`),
  KEY `created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
