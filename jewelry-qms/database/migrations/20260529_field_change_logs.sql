-- Phase 1E: field-level audit logs for key QMS records.
CREATE TABLE IF NOT EXISTS `field_change_logs` (
  `id` varchar(36) NOT NULL,
  `model_name` varchar(100) NOT NULL,
  `record_id` varchar(36) NOT NULL,
  `field_name` varchar(100) NOT NULL,
  `old_value` text,
  `new_value` text,
  `changed_by` varchar(36) DEFAULT NULL,
  `changed_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `record_lookup` (`model_name`,`record_id`),
  KEY `changed_at` (`changed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
