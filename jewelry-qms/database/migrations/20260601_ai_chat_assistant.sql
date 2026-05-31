CREATE TABLE IF NOT EXISTS `system_settings` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `value_type` enum('string','json','secret') NOT NULL DEFAULT 'string',
  `description` varchar(255) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_setting_key` (`company_id`,`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='系统级配置';

CREATE TABLE IF NOT EXISTS `ai_chat_sessions` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `user_id` varchar(36) NOT NULL,
  `title` varchar(200) DEFAULT '新对话',
  `context_mode` enum('general','context','expert') NOT NULL DEFAULT 'context',
  `agent_mode` enum('assistant','expert') NOT NULL DEFAULT 'assistant',
  `page_route` varchar(200) DEFAULT NULL,
  `page_record_id` varchar(36) DEFAULT NULL,
  `last_message_at` datetime DEFAULT NULL,
  `message_count` int NOT NULL DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_time` (`user_id`,`last_message_at`),
  KEY `idx_company_created` (`company_id`,`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI 聊天会话';

CREATE TABLE IF NOT EXISTS `ai_chat_messages` (
  `id` varchar(36) NOT NULL,
  `session_id` varchar(36) NOT NULL,
  `role` enum('user','assistant','system') NOT NULL,
  `content` text NOT NULL,
  `context_snapshot` json DEFAULT NULL,
  `draft_json` json DEFAULT NULL,
  `token_usage` json DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_session_created` (`session_id`,`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI 聊天消息';
