-- 珠宝检测实验室质量管理系统 (jewelry_qms)
-- ISO/IEC 17025 / CMA / CNAS
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `jewelry_qms` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `jewelry_qms`;

-- ========== 基础组织 ==========
CREATE TABLE `companies` (
  `id` varchar(36) NOT NULL,
  `name` varchar(200) NOT NULL COMMENT '实验室名称',
  `address` text,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `cma_number` varchar(100) DEFAULT NULL COMMENT 'CMA证书号',
  `cnas_number` varchar(100) DEFAULT NULL COMMENT 'CNAS证书号',
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `departments` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `name` varchar(100) NOT NULL COMMENT '部门名称',
  `code` varchar(20) DEFAULT NULL,
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `designations` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `name` varchar(100) NOT NULL COMMENT '职务',
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `employees` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `department_id` varchar(36) DEFAULT NULL,
  `designation_id` varchar(36) DEFAULT NULL,
  `employee_number` varchar(50) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `education` varchar(200) DEFAULT NULL,
  `entry_date` date DEFAULT NULL,
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `users` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `employee_id` varchar(36) DEFAULT NULL,
  `department_id` varchar(36) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role` enum('admin','quality_manager','auditor','department_head','staff') DEFAULT 'staff',
  `is_mr` tinyint(1) DEFAULT 0 COMMENT '质量负责人',
  `is_approver` tinyint(1) DEFAULT 0,
  `user_access` text COMMENT 'JSON权限',
  `last_login` datetime DEFAULT NULL,
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `user_sessions` (
  `id` varchar(36) NOT NULL,
  `user_id` varchar(36) NOT NULL,
  `start_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========== 文件控制 ==========
CREATE TABLE `doc_categories` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `parent_id` varchar(36) DEFAULT NULL,
  `level` tinyint(1) NOT NULL COMMENT '1手册2程序3SOP4记录',
  `code` varchar(50) DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `sort_order` int DEFAULT 0,
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `doc_templates` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `level` tinyint(1) NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text,
  `file_path` varchar(500) DEFAULT NULL COMMENT 'Word模板路径',
  `file_name` varchar(255) DEFAULT NULL,
  `header_fields` text COMMENT 'JSON页眉字段配置',
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `documents` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `category_id` varchar(36) DEFAULT NULL,
  `template_id` varchar(36) DEFAULT NULL,
  `level` tinyint(1) NOT NULL,
  `doc_number` varchar(50) NOT NULL COMMENT '文件编号',
  `title` varchar(300) NOT NULL,
  `version` varchar(20) DEFAULT 'A/0',
  `revision` int DEFAULT 0,
  `department_id` varchar(36) DEFAULT NULL COMMENT '归口部门',
  `effective_date` date DEFAULT NULL,
  `review_date` date DEFAULT NULL,
  `status` enum('draft','reviewing','approved','published','obsolete') DEFAULT 'draft',
  `file_path` varchar(500) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_type` varchar(20) DEFAULT NULL,
  `prepared_by` varchar(36) DEFAULT NULL,
  `reviewed_by` varchar(36) DEFAULT NULL,
  `approved_by` varchar(36) DEFAULT NULL,
  `change_reason` text,
  `publish` tinyint(1) DEFAULT 0,
  `soft_delete` tinyint(1) DEFAULT 0,
  `record_status` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `doc_number` (`doc_number`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `document_revisions` (
  `id` varchar(36) NOT NULL,
  `document_id` varchar(36) NOT NULL,
  `version` varchar(20) NOT NULL,
  `revision` int NOT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `change_reason` text,
  `created_by` varchar(36) DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `document_id` (`document_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `approvals` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `model_name` varchar(100) NOT NULL,
  `controller_name` varchar(100) DEFAULT NULL,
  `record` varchar(36) NOT NULL,
  `user_id` varchar(36) NOT NULL,
  `approval_level` tinyint(1) DEFAULT 1 COMMENT '1编制2审核3批准',
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `comments` text,
  `approved_on` datetime DEFAULT NULL,
  `record_status` tinyint(1) DEFAULT 1,
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `record` (`record`,`model_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `file_uploads` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `record` varchar(36) DEFAULT NULL,
  `model_name` varchar(100) DEFAULT NULL,
  `file_details` varchar(500) DEFAULT NULL,
  `file_dir` varchar(500) DEFAULT NULL,
  `file_type` varchar(20) DEFAULT NULL,
  `version` int DEFAULT 1,
  `archived` tinyint(1) DEFAULT 0,
  `comment` varchar(500) DEFAULT NULL,
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `record_form_templates` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `document_id` varchar(36) DEFAULT NULL COMMENT '受控原始附件对应documents.id',
  `doc_number` varchar(50) NOT NULL COMMENT '记录表格编号',
  `name` varchar(300) NOT NULL,
  `module` varchar(200) DEFAULT NULL,
  `source_file_path` varchar(500) DEFAULT NULL,
  `source_file_name` varchar(255) DEFAULT NULL,
  `print_template_key` varchar(100) NOT NULL,
  `field_schema` text NOT NULL,
  `version` varchar(20) DEFAULT 'A/0',
  `status` enum('draft','published','obsolete') DEFAULT 'draft',
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `doc_number` (`doc_number`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `record_form_instances` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `template_id` varchar(36) NOT NULL,
  `template_name` varchar(300) DEFAULT NULL,
  `template_module` varchar(200) DEFAULT NULL,
  `template_version` varchar(20) DEFAULT NULL,
  `template_print_template_key` varchar(100) DEFAULT NULL,
  `template_field_schema` text,
  `doc_number` varchar(50) NOT NULL,
  `record_title` varchar(300) NOT NULL,
  `field_values` text NOT NULL,
  `status` enum('draft','generated','locked','voided') DEFAULT 'draft',
  `generated_html_path` varchar(500) DEFAULT NULL,
  `generated_pdf_path` varchar(500) DEFAULT NULL,
  `generated_pdf_name` varchar(255) DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `template_id` (`template_id`),
  KEY `doc_number` (`doc_number`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========== 内部审核 ==========
CREATE TABLE `audit_plans` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `plan_year` year NOT NULL,
  `title` varchar(200) NOT NULL,
  `scope` text,
  `criteria` text COMMENT '审核依据',
  `status` enum('draft','approved','in_progress','completed') DEFAULT 'draft',
  `approved_by` varchar(36) DEFAULT NULL,
  `approved_date` date DEFAULT NULL,
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `audit_schedules` (
  `id` varchar(36) NOT NULL,
  `audit_plan_id` varchar(36) NOT NULL,
  `audit_date` date NOT NULL,
  `department_id` varchar(36) DEFAULT NULL,
  `clause` varchar(100) DEFAULT NULL COMMENT '17025条款',
  `auditor_id` varchar(36) DEFAULT NULL,
  `auditee_id` varchar(36) DEFAULT NULL,
  `status` enum('planned','in_progress','completed') DEFAULT 'planned',
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `audit_checklists` (
  `id` varchar(36) NOT NULL,
  `audit_schedule_id` varchar(36) NOT NULL,
  `clause` varchar(50) DEFAULT NULL,
  `check_item` text NOT NULL,
  `result` enum('conform','nonconform','observation','na') DEFAULT NULL,
  `evidence` text,
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `audit_findings` (
  `id` varchar(36) NOT NULL,
  `audit_schedule_id` varchar(36) NOT NULL,
  `finding_number` varchar(50) DEFAULT NULL,
  `finding_type` enum('major','minor','observation') DEFAULT 'minor',
  `clause` varchar(50) DEFAULT NULL,
  `description` text NOT NULL,
  `department_id` varchar(36) DEFAULT NULL,
  `responsible_id` varchar(36) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `status` enum('open','correcting','verified','closed') DEFAULT 'open',
  `capa_id` varchar(36) DEFAULT NULL,
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========== 管理评审 ==========
CREATE TABLE `management_reviews` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `review_number` varchar(50) DEFAULT NULL,
  `review_date` date NOT NULL,
  `title` varchar(200) NOT NULL,
  `participants` text COMMENT '参会人员JSON',
  `inputs` text COMMENT '评审输入',
  `outputs` text COMMENT '评审输出',
  `resolutions` text COMMENT '决议事项',
  `status` enum('planned','completed','follow_up') DEFAULT 'planned',
  `chairperson_id` varchar(36) DEFAULT NULL,
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `review_actions` (
  `id` varchar(36) NOT NULL,
  `management_review_id` varchar(36) NOT NULL,
  `action_item` text NOT NULL,
  `responsible_id` varchar(36) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `status` enum('open','in_progress','completed','overdue') DEFAULT 'open',
  `completion_date` date DEFAULT NULL,
  `verification` text,
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========== CAPA ==========
CREATE TABLE `capa_sources` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `name` varchar(100) NOT NULL,
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `capas` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `capa_number` varchar(50) NOT NULL,
  `source_id` varchar(36) DEFAULT NULL,
  `source_type` varchar(50) DEFAULT NULL COMMENT 'audit/complaint/nc/internal',
  `source_record_id` varchar(36) DEFAULT NULL,
  `description` text NOT NULL,
  `root_cause` text,
  `corrective_action` text,
  `preventive_action` text,
  `assigned_to` varchar(36) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `verification` text,
  `verified_by` varchar(36) DEFAULT NULL,
  `verified_date` date DEFAULT NULL,
  `status` enum('open','analyzing','implementing','verifying','closed') DEFAULT 'open',
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `record_status` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========== 设备与校准 ==========
CREATE TABLE `equipments` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `equipment_number` varchar(50) NOT NULL,
  `name` varchar(200) NOT NULL,
  `model` varchar(100) DEFAULT NULL,
  `manufacturer` varchar(200) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `department_id` varchar(36) DEFAULT NULL,
  `location` varchar(200) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `calibration_required` tinyint(1) DEFAULT 1,
  `calibration_cycle_months` int DEFAULT 12,
  `last_calibration_date` date DEFAULT NULL,
  `next_calibration_date` date DEFAULT NULL,
  `status` enum('active','calibrating','maintenance','decommissioned') DEFAULT 'active',
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `calibrations` (
  `id` varchar(36) NOT NULL,
  `equipment_id` varchar(36) NOT NULL,
  `calibration_date` date NOT NULL,
  `next_due_date` date DEFAULT NULL,
  `calibration_org` varchar(200) DEFAULT NULL,
  `certificate_number` varchar(100) DEFAULT NULL,
  `result` enum('pass','fail','limited') DEFAULT 'pass',
  `uncertainty` varchar(200) DEFAULT NULL COMMENT '测量不确定度',
  `remarks` text,
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `equipment_maintenances` (
  `id` varchar(36) NOT NULL,
  `equipment_id` varchar(36) NOT NULL,
  `maintenance_date` date NOT NULL,
  `maintenance_type` enum('routine','repair','verification') DEFAULT 'routine',
  `description` text,
  `performed_by` varchar(100) DEFAULT NULL,
  `next_due_date` date DEFAULT NULL,
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========== 培训与能力 ==========
CREATE TABLE `training_plans` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `plan_year` year NOT NULL,
  `title` varchar(200) NOT NULL,
  `status` enum('draft','approved','completed') DEFAULT 'draft',
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `trainings` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `training_plan_id` varchar(36) DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `training_type` enum('internal','external','on_job') DEFAULT 'internal',
  `trainer` varchar(200) DEFAULT NULL,
  `training_date` date DEFAULT NULL,
  `duration_hours` decimal(5,1) DEFAULT NULL,
  `content` text,
  `department_id` varchar(36) DEFAULT NULL,
  `status` enum('planned','completed','cancelled') DEFAULT 'planned',
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `training_records` (
  `id` varchar(36) NOT NULL,
  `training_id` varchar(36) NOT NULL,
  `employee_id` varchar(36) NOT NULL,
  `attendance` enum('present','absent','excused') DEFAULT 'present',
  `evaluation_score` decimal(5,2) DEFAULT NULL,
  `evaluation_result` enum('pass','fail','pending') DEFAULT 'pending',
  `remarks` text,
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `competency_records` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `employee_id` varchar(36) NOT NULL,
  `test_item` varchar(200) NOT NULL COMMENT '检测项目/方法',
  `method_standard` varchar(200) DEFAULT NULL COMMENT '标准方法',
  `assessment_date` date DEFAULT NULL,
  `assessor_id` varchar(36) DEFAULT NULL,
  `result` enum('pending','qualified','unqualified','supervised') DEFAULT 'pending',
  `authorization_scope` text COMMENT '授权范围',
  `valid_until` date DEFAULT NULL,
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========== 供应商 ==========
CREATE TABLE `suppliers` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `supplier_number` varchar(50) DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text,
  `service_type` varchar(200) DEFAULT NULL COMMENT '供应/服务类型',
  `qualification` text,
  `status` enum('pending','qualified','suspended','removed') DEFAULT 'pending',
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `supplier_evaluations` (
  `id` varchar(36) NOT NULL,
  `supplier_id` varchar(36) NOT NULL,
  `evaluation_date` date NOT NULL,
  `evaluation_type` enum('initial','periodic','reevaluation') DEFAULT 'periodic',
  `criteria_scores` text COMMENT 'JSON评分项',
  `total_score` decimal(5,2) DEFAULT NULL,
  `conclusion` enum('acceptable','conditional','unacceptable') DEFAULT NULL,
  `evaluator_id` varchar(36) DEFAULT NULL,
  `next_evaluation_date` date DEFAULT NULL,
  `remarks` text,
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========== 客户投诉 ==========
CREATE TABLE `customer_complaints` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `complaint_number` varchar(50) NOT NULL,
  `customer_name` varchar(200) NOT NULL,
  `contact` varchar(100) DEFAULT NULL,
  `received_date` date NOT NULL,
  `report_number` varchar(100) DEFAULT NULL COMMENT '关联检测报告',
  `description` text NOT NULL,
  `investigation` text,
  `handling` text,
  `response` text,
  `assigned_to` varchar(36) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `closed_date` date DEFAULT NULL,
  `status` enum('received','investigating','handling','responded','closed') DEFAULT 'received',
  `capa_id` varchar(36) DEFAULT NULL,
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `record_status` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========== 不符合工作 ==========
CREATE TABLE `nonconformities` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `nc_number` varchar(50) NOT NULL,
  `source` enum('test','sample','equipment','document','other') DEFAULT 'test',
  `description` text NOT NULL,
  `identified_by` varchar(36) DEFAULT NULL,
  `identified_date` date NOT NULL,
  `severity` enum('minor','major','critical') DEFAULT 'minor',
  `impact_assessment` text,
  `immediate_action` text,
  `disposition` enum('continue','suspend','recall','other') DEFAULT NULL,
  `assigned_to` varchar(36) DEFAULT NULL,
  `status` enum('open','evaluating','correcting','verified','closed') DEFAULT 'open',
  `capa_id` varchar(36) DEFAULT NULL,
  `report_number` varchar(100) DEFAULT NULL,
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `record_status` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========== 通知与历史 ==========
CREATE TABLE `notifications` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text,
  `type` enum('calibration','training','document','audit','general') DEFAULT 'general',
  `link_controller` varchar(100) DEFAULT NULL,
  `link_action` varchar(50) DEFAULT NULL,
  `link_id` varchar(36) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `notification_users` (
  `id` varchar(36) NOT NULL,
  `notification_id` varchar(36) NOT NULL,
  `user_id` varchar(36) NOT NULL,
  `status` tinyint(1) DEFAULT 0 COMMENT '0未读1已读',
  `read_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `histories` (
  `id` varchar(36) NOT NULL,
  `model_name` varchar(100) DEFAULT NULL,
  `controller_name` varchar(100) DEFAULT NULL,
  `action` varchar(50) DEFAULT NULL,
  `record_id` varchar(36) DEFAULT NULL,
  `user_id` varchar(36) DEFAULT NULL,
  `details` text,
  `created` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- 初始数据
INSERT INTO `companies` (`id`,`name`,`address`,`phone`,`email`,`publish`,`soft_delete`,`created`,`modified`) VALUES
('00000000-0000-0000-0000-000000000001','珠宝检测实验室','','','','1','0',NOW(),NOW());

INSERT INTO `departments` (`id`,`company_id`,`name`,`code`,`publish`,`soft_delete`,`created`,`modified`) VALUES
('00000000-0000-0000-0000-000000000010','00000000-0000-0000-0000-000000000001','质量管理部','QM','1','0',NOW(),NOW()),
('00000000-0000-0000-0000-000000000011','00000000-0000-0000-0000-000000000001','检测部','TEST','1','0',NOW(),NOW()),
('00000000-0000-0000-0000-000000000012','00000000-0000-0000-0000-000000000001','综合管理部','ADMIN','1','0',NOW(),NOW());

INSERT INTO `designations` (`id`,`company_id`,`name`,`publish`,`soft_delete`,`created`) VALUES
('00000000-0000-0000-0000-000000000020','00000000-0000-0000-0000-000000000001','质量负责人','1','0',NOW()),
('00000000-0000-0000-0000-000000000021','00000000-0000-0000-0000-000000000001','技术负责人','1','0',NOW()),
('00000000-0000-0000-0000-000000000022','00000000-0000-0000-0000-000000000001','检测员','1','0',NOW());

INSERT INTO `employees` (`id`,`company_id`,`department_id`,`designation_id`,`employee_number`,`name`,`email`,`publish`,`soft_delete`,`created`,`modified`) VALUES
('00000000-0000-0000-0000-000000000030','00000000-0000-0000-0000-000000000001','00000000-0000-0000-0000-000000000010','00000000-0000-0000-0000-000000000020','E001','系统管理员','admin@lab.com','1','0',NOW(),NOW());

INSERT INTO `users` (`id`,`company_id`,`employee_id`,`department_id`,`username`,`password`,`name`,`email`,`role`,`is_mr`,`is_approver`,`publish`,`soft_delete`,`created`,`modified`) VALUES
('00000000-0000-0000-0000-000000000040','00000000-0000-0000-0000-000000000001','00000000-0000-0000-0000-000000000030','00000000-0000-0000-0000-000000000010','admin','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','系统管理员','admin@lab.com','admin','1','1','1','0',NOW(),NOW());

INSERT INTO `doc_categories` (`id`,`company_id`,`parent_id`,`level`,`code`,`name`,`sort_order`,`publish`,`soft_delete`,`created`) VALUES
('00000000-0000-0000-0000-000000000050','00000000-0000-0000-0000-000000000001',NULL,1,'QM','质量手册',1,1,0,NOW()),
('00000000-0000-0000-0000-000000000051','00000000-0000-0000-0000-000000000001',NULL,2,'QP','程序文件',2,1,0,NOW()),
('00000000-0000-0000-0000-000000000052','00000000-0000-0000-0000-000000000001',NULL,3,'SOP','作业指导书',3,1,0,NOW()),
('00000000-0000-0000-0000-000000000053','00000000-0000-0000-0000-000000000001',NULL,4,'REC','记录表格',4,1,0,NOW());

INSERT INTO `capa_sources` (`id`,`company_id`,`name`,`publish`,`soft_delete`) VALUES
('00000000-0000-0000-0000-000000000060','00000000-0000-0000-0000-000000000001','内部审核','1','0'),
('00000000-0000-0000-0000-000000000061','00000000-0000-0000-0000-000000000001','管理评审','1','0'),
('00000000-0000-0000-0000-000000000062','00000000-0000-0000-0000-000000000001','客户投诉','1','0'),
('00000000-0000-0000-0000-000000000063','00000000-0000-0000-0000-000000000001','不符合工作','1','0'),
('00000000-0000-0000-0000-000000000064','00000000-0000-0000-0000-000000000001','日常监督','1','0');

-- 默认密码: password (bcrypt)
