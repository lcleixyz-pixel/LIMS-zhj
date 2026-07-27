-- 8021 UX 整改：期间核查计划基础表。
-- 幂等，可用于既有治理试运行数据卷和新建环境。
CREATE TABLE IF NOT EXISTS `equipment_period_checks` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `equipment_id` varchar(36) NOT NULL,
  `plan_date` date NOT NULL,
  `status` enum('planned','completed','cancelled') NOT NULL DEFAULT 'planned',
  `note` text,
  `publish` tinyint(1) NOT NULL DEFAULT 1,
  `soft_delete` tinyint(1) NOT NULL DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `company_plan_date` (`company_id`,`plan_date`),
  KEY `equipment_id` (`equipment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
