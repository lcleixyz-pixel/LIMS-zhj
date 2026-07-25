-- 记录更正追加层：纸质划改思想的电子化附页。
-- 原记录和原 PDF 不解冻、不覆盖；更正内容只追加，保留原内容、新内容、人员、时间和批准来源。
CREATE TABLE IF NOT EXISTS `record_form_corrections` (
  `id` varchar(40) NOT NULL,
  `company_id` varchar(40) NOT NULL,
  `record_id` varchar(40) NOT NULL,
  `correction_request_id` varchar(40) NOT NULL,
  `decision_notification_id` varchar(40) DEFAULT NULL,
  `correction_type` varchar(30) NOT NULL DEFAULT 'supplement',
  `original_content` text,
  `corrected_content` text NOT NULL,
  `correction_reason` text,
  `registered_by` varchar(40) DEFAULT NULL,
  `registered_at` datetime NOT NULL,
  `approved_by` varchar(40) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(40) DEFAULT NULL,
  `modified_by` varchar(40) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `record_id` (`record_id`),
  KEY `correction_request_id` (`correction_request_id`),
  KEY `decision_notification_id` (`decision_notification_id`),
  KEY `registered_at` (`registered_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
