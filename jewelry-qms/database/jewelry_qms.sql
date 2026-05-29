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
  `element_id` varchar(36) DEFAULT NULL COMMENT '关联体系要素',
  `procedure_doc_id` varchar(36) DEFAULT NULL COMMENT '关联程序文件 documents.id',
  `doc_number` varchar(50) NOT NULL COMMENT '记录表格编号',
  `name` varchar(300) NOT NULL,
  `module` varchar(200) DEFAULT NULL,
  `source_file_path` varchar(500) DEFAULT NULL,
  `source_file_name` varchar(255) DEFAULT NULL,
  `source_file_sha1` char(40) DEFAULT NULL,
  `print_template_key` varchar(100) NOT NULL,
  `field_schema` text NOT NULL,
  `version` varchar(20) DEFAULT 'A/0',
  `status` enum('draft','published','obsolete') DEFAULT 'draft',
  `review_status` enum('pending','field_confirmed','needs_fidelity','deferred','completed') DEFAULT 'pending',
  `review_note` text,
  `reviewed_at` datetime DEFAULT NULL,
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `element_id` (`element_id`),
  KEY `procedure_doc_id` (`procedure_doc_id`),
  KEY `doc_number` (`doc_number`),
  KEY `status` (`status`),
  KEY `review_status` (`review_status`),
  KEY `source_file_sha1` (`source_file_sha1`)
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

-- ========== 体系策划中心 ==========
CREATE TABLE `qms_sources` (
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

CREATE TABLE `qms_clauses` (
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

CREATE TABLE `qms_clause_texts` (
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

CREATE TABLE `qms_elements` (
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

CREATE TABLE `qms_element_clause_links` (
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

CREATE TABLE `qms_positions` (
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

CREATE TABLE `qms_element_documents` (
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

CREATE TABLE `qms_element_responsibilities` (
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

CREATE TABLE `qms_manual_sections` (
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

CREATE TABLE `qms_business_modules` (
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

CREATE TABLE `qms_business_module_elements` (
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

CREATE TABLE `qms_agent_suggestions` (
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

CREATE TABLE `qms_reference_procedure_matches` (
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

CREATE TABLE `qms_document_assets` (
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

CREATE TABLE `qms_structured_documents` (
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

CREATE TABLE `qms_document_blocks` (
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

CREATE TABLE `qms_document_block_links` (
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

CREATE TABLE `qms_document_change_logs` (
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

CREATE TABLE `qms_quality_policies` (
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

CREATE TABLE `qms_quality_objectives` (
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
  `notification_key` varchar(200) DEFAULT NULL,
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_notification_key` (`company_id`,`notification_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `notification_users` (
  `id` varchar(36) NOT NULL,
  `notification_id` varchar(36) NOT NULL,
  `user_id` varchar(36) NOT NULL,
  `status` tinyint(1) DEFAULT 0 COMMENT '0未读1已读',
  `read_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `notification_user` (`notification_id`,`user_id`)
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
