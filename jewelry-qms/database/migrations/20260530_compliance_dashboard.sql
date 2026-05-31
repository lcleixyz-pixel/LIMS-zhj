CREATE TABLE IF NOT EXISTS `compliance_checks` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `clause_number` varchar(20) DEFAULT NULL,
  `element_key` varchar(50) DEFAULT NULL,
  `dimension` enum('personnel','equipment','material','method','environment','document','record','management') NOT NULL,
  `check_code` varchar(100) NOT NULL,
  `check_name` varchar(200) NOT NULL,
  `check_description` text,
  `severity` enum('critical','major','minor') NOT NULL DEFAULT 'major',
  `weight` decimal(5,2) NOT NULL DEFAULT 1.00,
  `suggestion_template` text,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int NOT NULL DEFAULT 0,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_check_code` (`company_id`,`check_code`),
  KEY `idx_dimension` (`dimension`),
  KEY `idx_element_key` (`element_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `compliance_snapshots` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `snapshot_time` datetime NOT NULL,
  `trigger_type` enum('scheduled','manual') NOT NULL DEFAULT 'manual',
  `total_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `dimension_scores` json NOT NULL,
  `summary` json NOT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_company_time` (`company_id`,`snapshot_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `compliance_check_results` (
  `id` varchar(36) NOT NULL,
  `snapshot_id` varchar(36) NOT NULL,
  `check_id` varchar(36) NOT NULL,
  `check_code` varchar(100) NOT NULL,
  `dimension` varchar(30) NOT NULL,
  `status` enum('pass','fail','warning','insufficient_data','not_applicable') NOT NULL,
  `score` decimal(5,4) DEFAULT NULL,
  `total_checked` int NOT NULL DEFAULT 0,
  `fail_count` int NOT NULL DEFAULT 0,
  `warning_count` int NOT NULL DEFAULT 0,
  `fail_items` json DEFAULT NULL,
  `checked_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_snapshot` (`snapshot_id`),
  KEY `idx_check_code` (`check_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
