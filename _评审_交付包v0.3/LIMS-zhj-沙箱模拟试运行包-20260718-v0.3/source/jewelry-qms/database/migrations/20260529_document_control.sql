-- Phase 2.1: document distribution, recall, and periodic review records.
CREATE TABLE IF NOT EXISTS `document_distributions` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `document_id` varchar(36) NOT NULL,
  `user_id` varchar(36) NOT NULL,
  `site_id` varchar(36) DEFAULT NULL,
  `distributed_at` datetime NOT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `recalled_at` datetime DEFAULT NULL,
  `remarks` text,
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `document_id` (`document_id`),
  KEY `user_id` (`user_id`),
  KEY `site_id` (`site_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `document_reviews` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `document_id` varchar(36) NOT NULL,
  `review_date` date NOT NULL,
  `result` enum('continue','revise','obsolete') NOT NULL,
  `review_note` text NOT NULL,
  `next_review_date` date DEFAULT NULL,
  `reviewed_by` varchar(36) DEFAULT NULL,
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `document_id` (`document_id`),
  KEY `review_date` (`review_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
