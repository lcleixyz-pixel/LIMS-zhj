-- MySQL dump 10.13  Distrib 8.4.10, for Linux (aarch64)
--
-- Host: localhost    Database: jewelry_qms
-- ------------------------------------------------------
-- Server version	8.4.10

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `approvals`
--

DROP TABLE IF EXISTS `approvals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `approvals` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `model_name` varchar(100) NOT NULL,
  `controller_name` varchar(100) DEFAULT NULL,
  `record` varchar(36) NOT NULL,
  `user_id` varchar(36) NOT NULL,
  `approval_level` tinyint(1) DEFAULT '1' COMMENT '1编制2审核3批准',
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `comments` text,
  `approved_on` datetime DEFAULT NULL,
  `record_status` tinyint(1) DEFAULT '1',
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `record` (`record`,`model_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `approvals`
--

LOCK TABLES `approvals` WRITE;
/*!40000 ALTER TABLE `approvals` DISABLE KEYS */;
/*!40000 ALTER TABLE `approvals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_checklists`
--

DROP TABLE IF EXISTS `audit_checklists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_checklists` (
  `id` varchar(36) NOT NULL,
  `audit_schedule_id` varchar(36) NOT NULL,
  `clause` varchar(50) DEFAULT NULL,
  `check_item` text NOT NULL,
  `result` enum('conform','nonconform','observation','na') DEFAULT NULL,
  `evidence` text,
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_checklists`
--

LOCK TABLES `audit_checklists` WRITE;
/*!40000 ALTER TABLE `audit_checklists` DISABLE KEYS */;
/*!40000 ALTER TABLE `audit_checklists` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_findings`
--

DROP TABLE IF EXISTS `audit_findings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_findings`
--

LOCK TABLES `audit_findings` WRITE;
/*!40000 ALTER TABLE `audit_findings` DISABLE KEYS */;
/*!40000 ALTER TABLE `audit_findings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_plans`
--

DROP TABLE IF EXISTS `audit_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_plans`
--

LOCK TABLES `audit_plans` WRITE;
/*!40000 ALTER TABLE `audit_plans` DISABLE KEYS */;
/*!40000 ALTER TABLE `audit_plans` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_schedules`
--

DROP TABLE IF EXISTS `audit_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_schedules` (
  `id` varchar(36) NOT NULL,
  `audit_plan_id` varchar(36) NOT NULL,
  `audit_date` date NOT NULL,
  `department_id` varchar(36) DEFAULT NULL,
  `site_id` varchar(36) DEFAULT NULL,
  `clause` varchar(100) DEFAULT NULL COMMENT '17025条款',
  `auditor_id` varchar(36) DEFAULT NULL,
  `auditee_id` varchar(36) DEFAULT NULL,
  `status` enum('planned','in_progress','completed') DEFAULT 'planned',
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_schedules`
--

LOCK TABLES `audit_schedules` WRITE;
/*!40000 ALTER TABLE `audit_schedules` DISABLE KEYS */;
/*!40000 ALTER TABLE `audit_schedules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `calibrations`
--

DROP TABLE IF EXISTS `calibrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `calibrations`
--

LOCK TABLES `calibrations` WRITE;
/*!40000 ALTER TABLE `calibrations` DISABLE KEYS */;
/*!40000 ALTER TABLE `calibrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `capa_sources`
--

DROP TABLE IF EXISTS `capa_sources`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `capa_sources` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `name` varchar(100) NOT NULL,
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `capa_sources`
--

LOCK TABLES `capa_sources` WRITE;
/*!40000 ALTER TABLE `capa_sources` DISABLE KEYS */;
INSERT INTO `capa_sources` VALUES ('00000000-0000-0000-0000-000000000060','00000000-0000-0000-0000-000000000001','内部审核',1,0),('00000000-0000-0000-0000-000000000061','00000000-0000-0000-0000-000000000001','管理评审',1,0),('00000000-0000-0000-0000-000000000062','00000000-0000-0000-0000-000000000001','客户投诉',1,0),('00000000-0000-0000-0000-000000000063','00000000-0000-0000-0000-000000000001','不符合工作',1,0),('00000000-0000-0000-0000-000000000064','00000000-0000-0000-0000-000000000001','日常监督',1,0);
/*!40000 ALTER TABLE `capa_sources` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `capas`
--

DROP TABLE IF EXISTS `capas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
  `effectiveness_review_date` date DEFAULT NULL,
  `effectiveness_result` text,
  `status` enum('open','analyzing','implementing','verifying','closed') DEFAULT 'open',
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `record_status` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_capa_company_number` (`company_id`,`capa_number`),
  UNIQUE KEY `uq_capa_company_source_record` (`company_id`,`source_type`,`source_record_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `capas`
--

LOCK TABLES `capas` WRITE;
/*!40000 ALTER TABLE `capas` DISABLE KEYS */;
/*!40000 ALTER TABLE `capas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `companies`
--

DROP TABLE IF EXISTS `companies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `companies` (
  `id` varchar(36) NOT NULL,
  `name` varchar(200) NOT NULL COMMENT '实验室名称',
  `address` text,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `cma_number` varchar(100) DEFAULT NULL COMMENT 'CMA证书号',
  `cnas_number` varchar(100) DEFAULT NULL COMMENT 'CNAS证书号',
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `companies`
--

LOCK TABLES `companies` WRITE;
/*!40000 ALTER TABLE `companies` DISABLE KEYS */;
INSERT INTO `companies` VALUES ('00000000-0000-0000-0000-000000000001','珠宝检测实验室','','','',NULL,NULL,1,0,'2026-07-19 15:43:58','2026-07-19 15:43:58',NULL,NULL);
/*!40000 ALTER TABLE `companies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `competency_records`
--

DROP TABLE IF EXISTS `competency_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `competency_records`
--

LOCK TABLES `competency_records` WRITE;
/*!40000 ALTER TABLE `competency_records` DISABLE KEYS */;
/*!40000 ALTER TABLE `competency_records` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `compliance_check_results`
--

DROP TABLE IF EXISTS `compliance_check_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `compliance_check_results` (
  `id` varchar(36) NOT NULL,
  `snapshot_id` varchar(36) NOT NULL,
  `check_id` varchar(36) NOT NULL,
  `check_code` varchar(100) NOT NULL,
  `dimension` varchar(30) NOT NULL,
  `status` enum('pass','fail','warning','insufficient_data','not_applicable') NOT NULL,
  `score` decimal(5,4) DEFAULT NULL,
  `total_checked` int NOT NULL DEFAULT '0',
  `fail_count` int NOT NULL DEFAULT '0',
  `warning_count` int NOT NULL DEFAULT '0',
  `fail_items` json DEFAULT NULL,
  `checked_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_snapshot` (`snapshot_id`),
  KEY `idx_check_code` (`check_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `compliance_check_results`
--

LOCK TABLES `compliance_check_results` WRITE;
/*!40000 ALTER TABLE `compliance_check_results` DISABLE KEYS */;
/*!40000 ALTER TABLE `compliance_check_results` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `compliance_checks`
--

DROP TABLE IF EXISTS `compliance_checks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `compliance_checks` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `clause_number` varchar(20) DEFAULT NULL,
  `element_key` varchar(50) DEFAULT NULL,
  `dimension` enum('personnel','equipment','material','method','environment','document','record','management') NOT NULL,
  `check_code` varchar(100) NOT NULL,
  `check_name` varchar(200) NOT NULL,
  `check_description` text,
  `severity` enum('critical','major','minor') NOT NULL DEFAULT 'major',
  `weight` decimal(5,2) NOT NULL DEFAULT '1.00',
  `suggestion_template` text,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_check_code` (`company_id`,`check_code`),
  KEY `idx_dimension` (`dimension`),
  KEY `idx_element_key` (`element_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `compliance_checks`
--

LOCK TABLES `compliance_checks` WRITE;
/*!40000 ALTER TABLE `compliance_checks` DISABLE KEYS */;
/*!40000 ALTER TABLE `compliance_checks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `compliance_snapshots`
--

DROP TABLE IF EXISTS `compliance_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `compliance_snapshots` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `snapshot_time` datetime NOT NULL,
  `trigger_type` enum('scheduled','manual') NOT NULL DEFAULT 'manual',
  `total_score` decimal(5,2) NOT NULL DEFAULT '0.00',
  `dimension_scores` json NOT NULL,
  `summary` json NOT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_company_time` (`company_id`,`snapshot_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `compliance_snapshots`
--

LOCK TABLES `compliance_snapshots` WRITE;
/*!40000 ALTER TABLE `compliance_snapshots` DISABLE KEYS */;
/*!40000 ALTER TABLE `compliance_snapshots` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `controlled_print_logs`
--

DROP TABLE IF EXISTS `controlled_print_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `controlled_print_logs` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `document_id` varchar(36) NOT NULL,
  `print_number` varchar(80) NOT NULL,
  `copy_count` int DEFAULT '1',
  `purpose` varchar(200) DEFAULT NULL,
  `watermark_text` varchar(200) NOT NULL,
  `printed_by` varchar(36) DEFAULT NULL,
  `printed_at` datetime NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `print_number` (`print_number`),
  KEY `document_id` (`document_id`),
  KEY `printed_at` (`printed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `controlled_print_logs`
--

LOCK TABLES `controlled_print_logs` WRITE;
/*!40000 ALTER TABLE `controlled_print_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `controlled_print_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customer_complaints`
--

DROP TABLE IF EXISTS `customer_complaints`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `record_status` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_complaint_company_number` (`company_id`,`complaint_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customer_complaints`
--

LOCK TABLES `customer_complaints` WRITE;
/*!40000 ALTER TABLE `customer_complaints` DISABLE KEYS */;
/*!40000 ALTER TABLE `customer_complaints` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `departments`
--

DROP TABLE IF EXISTS `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `departments` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `name` varchar(100) NOT NULL COMMENT '部门名称',
  `code` varchar(20) DEFAULT NULL,
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `departments`
--

LOCK TABLES `departments` WRITE;
/*!40000 ALTER TABLE `departments` DISABLE KEYS */;
INSERT INTO `departments` VALUES ('00000000-0000-0000-0000-000000000010','00000000-0000-0000-0000-000000000001','质量管理部','QM',1,0,'2026-07-19 15:43:58','2026-07-19 15:43:58',NULL,NULL),('00000000-0000-0000-0000-000000000011','00000000-0000-0000-0000-000000000001','检测部','TEST',1,0,'2026-07-19 15:43:58','2026-07-19 15:43:58',NULL,NULL),('00000000-0000-0000-0000-000000000012','00000000-0000-0000-0000-000000000001','综合管理部','ADMIN',1,0,'2026-07-19 15:43:58','2026-07-19 15:43:58',NULL,NULL);
/*!40000 ALTER TABLE `departments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `designations`
--

DROP TABLE IF EXISTS `designations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `designations` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `name` varchar(100) NOT NULL COMMENT '职务',
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `designations`
--

LOCK TABLES `designations` WRITE;
/*!40000 ALTER TABLE `designations` DISABLE KEYS */;
INSERT INTO `designations` VALUES ('00000000-0000-0000-0000-000000000020','00000000-0000-0000-0000-000000000001','质量负责人',1,0,'2026-07-19 15:43:58',NULL),('00000000-0000-0000-0000-000000000021','00000000-0000-0000-0000-000000000001','技术负责人',1,0,'2026-07-19 15:43:58',NULL),('00000000-0000-0000-0000-000000000022','00000000-0000-0000-0000-000000000001','检测员',1,0,'2026-07-19 15:43:58',NULL),('00000000-0000-0000-0000-000000000023','00000000-0000-0000-0000-000000000001','检测辅助',1,0,'2026-07-19 15:43:58',NULL),('00000000-0000-0000-0000-000000000024','00000000-0000-0000-0000-000000000001','其他支持人员',1,0,'2026-07-19 15:43:58',NULL);
/*!40000 ALTER TABLE `designations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `doc_categories`
--

DROP TABLE IF EXISTS `doc_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `doc_categories` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `parent_id` varchar(36) DEFAULT NULL,
  `level` tinyint(1) NOT NULL COMMENT '1手册2程序3SOP4记录',
  `code` varchar(50) DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `sort_order` int DEFAULT '0',
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `doc_categories`
--

LOCK TABLES `doc_categories` WRITE;
/*!40000 ALTER TABLE `doc_categories` DISABLE KEYS */;
INSERT INTO `doc_categories` VALUES ('00000000-0000-0000-0000-000000000050','00000000-0000-0000-0000-000000000001',NULL,1,'QM','质量手册',1,1,0,'2026-07-19 15:43:58',NULL),('00000000-0000-0000-0000-000000000051','00000000-0000-0000-0000-000000000001',NULL,2,'QP','程序文件',2,1,0,'2026-07-19 15:43:58',NULL),('00000000-0000-0000-0000-000000000052','00000000-0000-0000-0000-000000000001',NULL,3,'SOP','作业指导书',3,1,0,'2026-07-19 15:43:58',NULL),('00000000-0000-0000-0000-000000000053','00000000-0000-0000-0000-000000000001',NULL,4,'REC','记录表格',4,1,0,'2026-07-19 15:43:58',NULL);
/*!40000 ALTER TABLE `doc_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `doc_templates`
--

DROP TABLE IF EXISTS `doc_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `doc_templates` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `level` tinyint(1) NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text,
  `file_path` varchar(500) DEFAULT NULL COMMENT 'Word模板路径',
  `file_name` varchar(255) DEFAULT NULL,
  `header_fields` text COMMENT 'JSON页眉字段配置',
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `doc_templates`
--

LOCK TABLES `doc_templates` WRITE;
/*!40000 ALTER TABLE `doc_templates` DISABLE KEYS */;
/*!40000 ALTER TABLE `doc_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `document_distributions`
--

DROP TABLE IF EXISTS `document_distributions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_distributions` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `document_id` varchar(36) NOT NULL,
  `user_id` varchar(36) NOT NULL,
  `site_id` varchar(36) DEFAULT NULL,
  `distributed_at` datetime NOT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `recalled_at` datetime DEFAULT NULL,
  `remarks` text,
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `document_id` (`document_id`),
  KEY `user_id` (`user_id`),
  KEY `site_id` (`site_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_distributions`
--

LOCK TABLES `document_distributions` WRITE;
/*!40000 ALTER TABLE `document_distributions` DISABLE KEYS */;
/*!40000 ALTER TABLE `document_distributions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `document_reviews`
--

DROP TABLE IF EXISTS `document_reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_reviews` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `document_id` varchar(36) NOT NULL,
  `review_date` date NOT NULL,
  `result` enum('continue','revise','obsolete') NOT NULL,
  `review_note` text NOT NULL,
  `next_review_date` date DEFAULT NULL,
  `reviewed_by` varchar(36) DEFAULT NULL,
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `document_id` (`document_id`),
  KEY `review_date` (`review_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_reviews`
--

LOCK TABLES `document_reviews` WRITE;
/*!40000 ALTER TABLE `document_reviews` DISABLE KEYS */;
/*!40000 ALTER TABLE `document_reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `document_revisions`
--

DROP TABLE IF EXISTS `document_revisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_revisions`
--

LOCK TABLES `document_revisions` WRITE;
/*!40000 ALTER TABLE `document_revisions` DISABLE KEYS */;
/*!40000 ALTER TABLE `document_revisions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `documents`
--

DROP TABLE IF EXISTS `documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `documents` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `category_id` varchar(36) DEFAULT NULL,
  `template_id` varchar(36) DEFAULT NULL,
  `level` tinyint(1) NOT NULL,
  `doc_number` varchar(50) NOT NULL COMMENT '文件编号',
  `title` varchar(300) NOT NULL,
  `version` varchar(20) DEFAULT 'A/0',
  `revision` int DEFAULT '0',
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
  `publish` tinyint(1) DEFAULT '0',
  `soft_delete` tinyint(1) DEFAULT '0',
  `record_status` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `doc_number` (`doc_number`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documents`
--

LOCK TABLES `documents` WRITE;
/*!40000 ALTER TABLE `documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employee_appointments`
--

DROP TABLE IF EXISTS `employee_appointments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `employee_appointments` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `employee_id` varchar(36) NOT NULL,
  `position_id` varchar(36) DEFAULT NULL,
  `site_id` varchar(36) DEFAULT NULL,
  `appointment_key` varchar(200) NOT NULL,
  `appointment_type` enum('role','authorization','responsibility') DEFAULT 'role',
  `position_name` varchar(200) NOT NULL,
  `appointment_scope` text,
  `appointed_at` date DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `source_document_id` varchar(36) DEFAULT NULL,
  `source_document_number` varchar(80) DEFAULT NULL,
  `source_excerpt` text,
  `source_kind` enum('legacy_document','corporate_evidence','responsibility_chain') NOT NULL DEFAULT 'legacy_document',
  `source_chain_version_id` varchar(36) DEFAULT NULL,
  `source_responsibility_id` varchar(36) DEFAULT NULL,
  `source_approval_id` varchar(36) DEFAULT NULL,
  `status` enum('active','inactive','expired','revoked') DEFAULT 'active',
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_appointment_key` (`company_id`,`appointment_key`),
  KEY `employee_id` (`employee_id`),
  KEY `position_id` (`position_id`),
  KEY `site_id` (`site_id`),
  KEY `appointment_type` (`appointment_type`),
  KEY `status` (`status`),
  KEY `source_chain_version_id` (`source_chain_version_id`),
  KEY `source_responsibility_id` (`source_responsibility_id`),
  KEY `source_approval_id` (`source_approval_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employee_appointments`
--

LOCK TABLES `employee_appointments` WRITE;
/*!40000 ALTER TABLE `employee_appointments` DISABLE KEYS */;
INSERT INTO `employee_appointments` VALUES ('sim-gov-appt-as','00000000-0000-0000-0000-000000000001','sim-gov-emp-as','sim-gov-pos-as',NULL,'SIM-GOV:SIM-E-AS:authorized_signatory:GLOBAL','authorization','SIM-æŽˆæƒç­¾å­—äºº','ä»…æ¨¡æ‹ŸæŽˆæƒèŒƒå›´æ£€æŸ¥ï¼Œä¸å¾—çœŸå®žç­¾å‘','2026-07-19',NULL,NULL,'SIM-GOV-ROLE-MATRIX-v0.1','SIMå¯¼æ¼”é…ç½®ï¼Œä¸æž„æˆçœŸå®žæŽˆæƒ','corporate_evidence',NULL,NULL,NULL,'active',1,0,'2026-07-19 15:46:52','2026-07-19 15:46:52',NULL,NULL),('sim-gov-appt-dc','00000000-0000-0000-0000-000000000001','sim-gov-emp-dc','sim-gov-pos-dc','00000000-0000-0000-0000-000000000070','SIM-GOV:SIM-E-DC:document_controller:MAIN','role','SIM-æ–‡ä»¶ç®¡ç†å‘˜','ä»…ä¸»åœºæ‰€æ–‡æŽ§','2026-07-19',NULL,NULL,'SIM-GOV-ROLE-MATRIX-v0.1','SIMå¯¼æ¼”é…ç½®ï¼Œä¸æž„æˆçœŸå®žä»»å‘½','corporate_evidence',NULL,NULL,NULL,'active',1,0,'2026-07-19 15:46:52','2026-07-19 15:46:52',NULL,NULL),('sim-gov-appt-em','00000000-0000-0000-0000-000000000001','sim-gov-emp-em','sim-gov-pos-em','00000000-0000-0000-0000-000000000070','SIM-GOV:SIM-E-EM:equipment_manager:MAIN','role','SIM-è®¾å¤‡ç®¡ç†å‘˜','ä»…ä¸»åœºæ‰€è®¾å¤‡','2026-07-19',NULL,NULL,'SIM-GOV-ROLE-MATRIX-v0.1','SIMå¯¼æ¼”é…ç½®ï¼Œä¸æž„æˆçœŸå®žä»»å‘½','corporate_evidence',NULL,NULL,NULL,'active',1,0,'2026-07-19 15:46:52','2026-07-19 15:46:52',NULL,NULL),('sim-gov-appt-ia','00000000-0000-0000-0000-000000000001','sim-gov-emp-ia','sim-gov-pos-ia','00000000-0000-0000-0000-000000000070','SIM-GOV:SIM-E-IA:internal_auditor:MAIN','role','SIM-å†…å®¡å‘˜','ä»…ä¸»åœºæ‰€åªè¯»å®¡æ ¸','2026-07-19',NULL,NULL,'SIM-GOV-ROLE-MATRIX-v0.1','SIMå¯¼æ¼”é…ç½®ï¼Œä¸æž„æˆçœŸå®žä»»å‘½','corporate_evidence',NULL,NULL,NULL,'active',1,0,'2026-07-19 15:46:52','2026-07-19 15:46:52',NULL,NULL),('sim-gov-appt-qm','00000000-0000-0000-0000-000000000001','sim-gov-emp-qm','sim-gov-pos-qm',NULL,'SIM-GOV:SIM-E-QM:quality_manager:GLOBAL','role','SIM-è´¨é‡è´Ÿè´£äºº','å®Œæ•´æ²™ç®±æ²»ç†å€™é€‰','2026-07-19',NULL,NULL,'SIM-GOV-ROLE-MATRIX-v0.1','SIMå¯¼æ¼”é…ç½®ï¼Œä¸æž„æˆçœŸå®žä»»å‘½','corporate_evidence',NULL,NULL,NULL,'active',1,0,'2026-07-19 15:46:52','2026-07-19 15:46:52',NULL,NULL),('sim-gov-appt-sm','00000000-0000-0000-0000-000000000001','sim-gov-emp-sm','sim-gov-pos-sm',NULL,'SIM-GOV:SIM-E-SM:sample_manager:GLOBAL','role','SIM-æ ·å“ç®¡ç†å‘˜','å®Œæ•´æ²™ç®±æ²»ç†å€™é€‰','2026-07-19',NULL,NULL,'SIM-GOV-ROLE-MATRIX-v0.1','SIMå¯¼æ¼”é…ç½®ï¼Œä¸æž„æˆçœŸå®žä»»å‘½','corporate_evidence',NULL,NULL,NULL,'active',1,0,'2026-07-19 15:46:52','2026-07-19 15:46:52',NULL,NULL),('sim-gov-appt-tm','00000000-0000-0000-0000-000000000001','sim-gov-emp-tm','sim-gov-pos-tm',NULL,'SIM-GOV:SIM-E-TM:technical_manager:GLOBAL','role','SIM-æŠ€æœ¯è´Ÿè´£äºº','å®Œæ•´æ²™ç®±æ²»ç†å€™é€‰','2026-07-19',NULL,NULL,'SIM-GOV-ROLE-MATRIX-v0.1','SIMå¯¼æ¼”é…ç½®ï¼Œä¸æž„æˆçœŸå®žä»»å‘½','corporate_evidence',NULL,NULL,NULL,'active',1,0,'2026-07-19 15:46:52','2026-07-19 15:46:52',NULL,NULL),('sim-gov-appt-tp','00000000-0000-0000-0000-000000000001','sim-gov-emp-tp','sim-gov-pos-tp',NULL,'SIM-GOV:SIM-E-TP:testing_personnel:GLOBAL','role','SIM-æ£€æµ‹å‘˜','å®Œæ•´æ²™ç®±æ²»ç†å€™é€‰','2026-07-19',NULL,NULL,'SIM-GOV-ROLE-MATRIX-v0.1','SIMå¯¼æ¼”é…ç½®ï¼Œä¸æž„æˆçœŸå®žä»»å‘½','corporate_evidence',NULL,NULL,NULL,'active',1,0,'2026-07-19 15:46:52','2026-07-19 15:46:52',NULL,NULL),('sim-gov-appt-tr','00000000-0000-0000-0000-000000000001','sim-gov-emp-tr','sim-gov-pos-tr',NULL,'SIM-GOV:SIM-E-TR:training_manager:GLOBAL','role','SIM-åŸ¹è®­æŽˆæƒç®¡ç†å‘˜','å®Œæ•´æ²™ç®±æ²»ç†å€™é€‰','2026-07-19',NULL,NULL,'SIM-GOV-ROLE-MATRIX-v0.1','SIMå¯¼æ¼”é…ç½®ï¼Œä¸æž„æˆçœŸå®žä»»å‘½','corporate_evidence',NULL,NULL,NULL,'active',1,0,'2026-07-19 15:46:52','2026-07-19 15:46:52',NULL,NULL);
/*!40000 ALTER TABLE `employee_appointments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employee_certificates`
--

DROP TABLE IF EXISTS `employee_certificates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `employee_certificates` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `employee_id` varchar(36) NOT NULL,
  `certificate_type` varchar(100) NOT NULL,
  `certificate_number` varchar(100) DEFAULT NULL,
  `issuing_authority` varchar(200) DEFAULT NULL,
  `issue_date` date DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `review_date` date DEFAULT NULL,
  `status` enum('active','expired','revoked','archived') DEFAULT 'active',
  `remarks` text,
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  KEY `valid_until` (`valid_until`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employee_certificates`
--

LOCK TABLES `employee_certificates` WRITE;
/*!40000 ALTER TABLE `employee_certificates` DISABLE KEYS */;
/*!40000 ALTER TABLE `employee_certificates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employees`
--

DROP TABLE IF EXISTS `employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `employees` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `department_id` varchar(36) DEFAULT NULL,
  `primary_site_id` varchar(36) DEFAULT NULL,
  `designation_id` varchar(36) DEFAULT NULL,
  `employee_number` varchar(50) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `education` varchar(200) DEFAULT NULL,
  `entry_date` date DEFAULT NULL,
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employees`
--

LOCK TABLES `employees` WRITE;
/*!40000 ALTER TABLE `employees` DISABLE KEYS */;
INSERT INTO `employees` VALUES ('00000000-0000-0000-0000-000000000030','00000000-0000-0000-0000-000000000001','00000000-0000-0000-0000-000000000010',NULL,'00000000-0000-0000-0000-000000000020','E001','系统管理员','admin@lab.com',NULL,NULL,NULL,1,0,'2026-07-19 15:43:58','2026-07-19 15:43:58',NULL,NULL),('sim-gov-emp-as','00000000-0000-0000-0000-000000000001','00000000-0000-0000-0000-000000000011','00000000-0000-0000-0000-000000000070',NULL,'SIM-E-AS','SIM-æŽˆæƒç­¾å­—äºº','sim-as@example.invalid',NULL,NULL,NULL,1,0,'2026-07-19 15:46:52','2026-07-19 15:46:52',NULL,NULL),('sim-gov-emp-dc','00000000-0000-0000-0000-000000000001','00000000-0000-0000-0000-000000000012','00000000-0000-0000-0000-000000000070',NULL,'SIM-E-DC','SIM-æ–‡ä»¶ç®¡ç†å‘˜','sim-dc@example.invalid',NULL,NULL,NULL,1,0,'2026-07-19 15:46:52','2026-07-19 15:46:52',NULL,NULL),('sim-gov-emp-em','00000000-0000-0000-0000-000000000001','00000000-0000-0000-0000-000000000012','00000000-0000-0000-0000-000000000070',NULL,'SIM-E-EM','SIM-è®¾å¤‡ç®¡ç†å‘˜','sim-em@example.invalid',NULL,NULL,NULL,1,0,'2026-07-19 15:46:52','2026-07-19 15:46:52',NULL,NULL),('sim-gov-emp-ia','00000000-0000-0000-0000-000000000001','00000000-0000-0000-0000-000000000010','00000000-0000-0000-0000-000000000070',NULL,'SIM-E-IA','SIM-å†…å®¡å‘˜','sim-ia@example.invalid',NULL,NULL,NULL,1,0,'2026-07-19 15:46:52','2026-07-19 15:46:52',NULL,NULL),('sim-gov-emp-qm','00000000-0000-0000-0000-000000000001','00000000-0000-0000-0000-000000000010','00000000-0000-0000-0000-000000000070',NULL,'SIM-E-QM','SIM-è´¨é‡è´Ÿè´£äºº','sim-qm@example.invalid',NULL,NULL,NULL,1,0,'2026-07-19 15:46:52','2026-07-19 15:46:52',NULL,NULL),('sim-gov-emp-sm','00000000-0000-0000-0000-000000000001','00000000-0000-0000-0000-000000000011','00000000-0000-0000-0000-000000000070',NULL,'SIM-E-SM','SIM-æ ·å“ç®¡ç†å‘˜','sim-sm@example.invalid',NULL,NULL,NULL,1,0,'2026-07-19 15:46:52','2026-07-19 15:46:52',NULL,NULL),('sim-gov-emp-tm','00000000-0000-0000-0000-000000000001','00000000-0000-0000-0000-000000000011','00000000-0000-0000-0000-000000000070',NULL,'SIM-E-TM','SIM-æŠ€æœ¯è´Ÿè´£äºº','sim-tm@example.invalid',NULL,NULL,NULL,1,0,'2026-07-19 15:46:52','2026-07-19 15:46:52',NULL,NULL),('sim-gov-emp-tp','00000000-0000-0000-0000-000000000001','00000000-0000-0000-0000-000000000011','00000000-0000-0000-0000-000000000070',NULL,'SIM-E-TP','SIM-æ£€æµ‹å‘˜','sim-tp@example.invalid',NULL,NULL,NULL,1,0,'2026-07-19 15:46:52','2026-07-19 15:46:52',NULL,NULL),('sim-gov-emp-tr','00000000-0000-0000-0000-000000000001','00000000-0000-0000-0000-000000000012','00000000-0000-0000-0000-000000000070',NULL,'SIM-E-TR','SIM-åŸ¹è®­æŽˆæƒç®¡ç†å‘˜','sim-tr@example.invalid',NULL,NULL,NULL,1,0,'2026-07-19 15:46:52','2026-07-19 15:46:52',NULL,NULL);
/*!40000 ALTER TABLE `employees` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `equipment_authorizations`
--

DROP TABLE IF EXISTS `equipment_authorizations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `equipment_authorizations` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `equipment_id` varchar(36) NOT NULL,
  `employee_id` varchar(36) NOT NULL,
  `authorized_date` date NOT NULL,
  `valid_until` date DEFAULT NULL,
  `authorization_scope` text,
  `authorized_by` varchar(36) DEFAULT NULL,
  `status` enum('active','revoked','expired') DEFAULT 'active',
  `remarks` text,
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `equipment_id` (`equipment_id`),
  KEY `employee_id` (`employee_id`),
  KEY `valid_until` (`valid_until`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `equipment_authorizations`
--

LOCK TABLES `equipment_authorizations` WRITE;
/*!40000 ALTER TABLE `equipment_authorizations` DISABLE KEYS */;
/*!40000 ALTER TABLE `equipment_authorizations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `equipment_maintenances`
--

DROP TABLE IF EXISTS `equipment_maintenances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `equipment_maintenances` (
  `id` varchar(36) NOT NULL,
  `equipment_id` varchar(36) NOT NULL,
  `maintenance_date` date NOT NULL,
  `maintenance_type` enum('routine','repair','verification') DEFAULT 'routine',
  `description` text,
  `performed_by` varchar(100) DEFAULT NULL,
  `next_due_date` date DEFAULT NULL,
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `equipment_maintenances`
--

LOCK TABLES `equipment_maintenances` WRITE;
/*!40000 ALTER TABLE `equipment_maintenances` DISABLE KEYS */;
/*!40000 ALTER TABLE `equipment_maintenances` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `equipment_transfers`
--

DROP TABLE IF EXISTS `equipment_transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `equipment_transfers` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `equipment_id` varchar(36) NOT NULL,
  `from_site_id` varchar(36) DEFAULT NULL,
  `to_site_id` varchar(36) NOT NULL,
  `transfer_date` date NOT NULL,
  `reason` text,
  `transferred_by` varchar(36) DEFAULT NULL,
  `remarks` text,
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `equipment_id` (`equipment_id`),
  KEY `to_site_id` (`to_site_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `equipment_transfers`
--

LOCK TABLES `equipment_transfers` WRITE;
/*!40000 ALTER TABLE `equipment_transfers` DISABLE KEYS */;
/*!40000 ALTER TABLE `equipment_transfers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `equipments`
--

DROP TABLE IF EXISTS `equipments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `equipments` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `equipment_number` varchar(50) NOT NULL,
  `name` varchar(200) NOT NULL,
  `model` varchar(100) DEFAULT NULL,
  `manufacturer` varchar(200) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `measurement_range` varchar(200) DEFAULT NULL,
  `traceability_method` varchar(50) DEFAULT NULL,
  `traceability_due_date` date DEFAULT NULL,
  `traceability_confirm_result` varchar(50) DEFAULT NULL,
  `department_id` varchar(36) DEFAULT NULL,
  `site_id` varchar(36) DEFAULT NULL,
  `location` varchar(200) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `calibration_required` tinyint(1) DEFAULT '1',
  `calibration_cycle_months` int DEFAULT '12',
  `last_calibration_date` date DEFAULT NULL,
  `next_calibration_date` date DEFAULT NULL,
  `status` enum('active','calibrating','maintenance','decommissioned') DEFAULT 'active',
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `equipments`
--

LOCK TABLES `equipments` WRITE;
/*!40000 ALTER TABLE `equipments` DISABLE KEYS */;
/*!40000 ALTER TABLE `equipments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `field_change_logs`
--

DROP TABLE IF EXISTS `field_change_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `field_change_logs` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `field_change_logs`
--

LOCK TABLES `field_change_logs` WRITE;
/*!40000 ALTER TABLE `field_change_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `field_change_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `file_uploads`
--

DROP TABLE IF EXISTS `file_uploads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `file_uploads` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `record` varchar(36) DEFAULT NULL,
  `model_name` varchar(100) DEFAULT NULL,
  `file_details` varchar(500) DEFAULT NULL,
  `file_dir` varchar(500) DEFAULT NULL,
  `file_type` varchar(20) DEFAULT NULL,
  `version` int DEFAULT '1',
  `archived` tinyint(1) DEFAULT '0',
  `comment` varchar(500) DEFAULT NULL,
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `file_uploads`
--

LOCK TABLES `file_uploads` WRITE;
/*!40000 ALTER TABLE `file_uploads` DISABLE KEYS */;
/*!40000 ALTER TABLE `file_uploads` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `histories`
--

DROP TABLE IF EXISTS `histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `histories`
--

LOCK TABLES `histories` WRITE;
/*!40000 ALTER TABLE `histories` DISABLE KEYS */;
/*!40000 ALTER TABLE `histories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `management_reviews`
--

DROP TABLE IF EXISTS `management_reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `management_reviews`
--

LOCK TABLES `management_reviews` WRITE;
/*!40000 ALTER TABLE `management_reviews` DISABLE KEYS */;
/*!40000 ALTER TABLE `management_reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `nonconformities`
--

DROP TABLE IF EXISTS `nonconformities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `record_status` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_nc_company_number` (`company_id`,`nc_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `nonconformities`
--

LOCK TABLES `nonconformities` WRITE;
/*!40000 ALTER TABLE `nonconformities` DISABLE KEYS */;
/*!40000 ALTER TABLE `nonconformities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notification_users`
--

DROP TABLE IF EXISTS `notification_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notification_users` (
  `id` varchar(36) NOT NULL,
  `notification_id` varchar(36) NOT NULL,
  `user_id` varchar(36) NOT NULL,
  `status` tinyint(1) DEFAULT '0' COMMENT '0未读1已读',
  `read_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `notification_user` (`notification_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notification_users`
--

LOCK TABLES `notification_users` WRITE;
/*!40000 ALTER TABLE `notification_users` DISABLE KEYS */;
/*!40000 ALTER TABLE `notification_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_notification_key` (`company_id`,`notification_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qms_activity_responsibilities`
--

DROP TABLE IF EXISTS `qms_activity_responsibilities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qms_activity_responsibilities` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `activity_id` varchar(36) NOT NULL,
  `step_code` varchar(200) NOT NULL,
  `duty_type` enum('organize','review','approve','execute','verify','record_keep','countersign','inform') NOT NULL,
  `duty_text` text NOT NULL,
  `slot_kind` enum('fixed_position','activity_role','dynamic_owner') NOT NULL,
  `assignment_mode` enum('named_person','activity_instance','derived_from_scope') NOT NULL,
  `fixed_position_id` varchar(36) DEFAULT NULL,
  `activity_role_code` varchar(100) DEFAULT NULL,
  `dynamic_owner_code` varchar(100) DEFAULT NULL,
  `required` tinyint(1) NOT NULL DEFAULT '1',
  `eligibility_rule` json DEFAULT NULL,
  `rule_codes` json DEFAULT NULL,
  `source_refs` json DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `activity_step` (`activity_id`,`step_code`),
  KEY `activity_id` (`activity_id`),
  KEY `fixed_position_id` (`fixed_position_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qms_activity_responsibilities`
--

LOCK TABLES `qms_activity_responsibilities` WRITE;
/*!40000 ALTER TABLE `qms_activity_responsibilities` DISABLE KEYS */;
/*!40000 ALTER TABLE `qms_activity_responsibilities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qms_agent_suggestions`
--

DROP TABLE IF EXISTS `qms_agent_suggestions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qms_agent_suggestions`
--

LOCK TABLES `qms_agent_suggestions` WRITE;
/*!40000 ALTER TABLE `qms_agent_suggestions` DISABLE KEYS */;
/*!40000 ALTER TABLE `qms_agent_suggestions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qms_business_module_elements`
--

DROP TABLE IF EXISTS `qms_business_module_elements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qms_business_module_elements` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `module_id` varchar(36) NOT NULL,
  `element_id` varchar(36) NOT NULL,
  `relation_type` enum('primary','supporting') DEFAULT 'supporting',
  `note` text,
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `module_element` (`module_id`,`element_id`),
  KEY `element_id` (`element_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qms_business_module_elements`
--

LOCK TABLES `qms_business_module_elements` WRITE;
/*!40000 ALTER TABLE `qms_business_module_elements` DISABLE KEYS */;
/*!40000 ALTER TABLE `qms_business_module_elements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qms_business_modules`
--

DROP TABLE IF EXISTS `qms_business_modules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qms_business_modules` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `code` varchar(80) NOT NULL,
  `name` varchar(200) NOT NULL,
  `controller_name` varchar(100) DEFAULT NULL,
  `primary_element_id` varchar(36) DEFAULT NULL,
  `url` varchar(200) DEFAULT NULL,
  `description` text,
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `module_code` (`code`),
  KEY `primary_element_id` (`primary_element_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qms_business_modules`
--

LOCK TABLES `qms_business_modules` WRITE;
/*!40000 ALTER TABLE `qms_business_modules` DISABLE KEYS */;
/*!40000 ALTER TABLE `qms_business_modules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qms_clause_texts`
--

DROP TABLE IF EXISTS `qms_clause_texts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `clause_text` (`clause_id`),
  KEY `source_clause` (`source_id`,`clause_number`),
  KEY `review_status` (`review_status`),
  KEY `text_hash` (`text_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qms_clause_texts`
--

LOCK TABLES `qms_clause_texts` WRITE;
/*!40000 ALTER TABLE `qms_clause_texts` DISABLE KEYS */;
/*!40000 ALTER TABLE `qms_clause_texts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qms_clauses`
--

DROP TABLE IF EXISTS `qms_clauses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qms_clauses` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `source_id` varchar(36) NOT NULL,
  `parent_id` varchar(36) DEFAULT NULL,
  `clause_number` varchar(80) NOT NULL,
  `title` varchar(500) NOT NULL,
  `level` tinyint DEFAULT '1',
  `page_number` int DEFAULT NULL,
  `locator` varchar(255) DEFAULT NULL,
  `applicability` enum('applicable','not_applicable','conditional') DEFAULT 'applicable',
  `review_status` enum('draft','published','obsolete') DEFAULT 'published',
  `summary` text,
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `source_clause` (`source_id`,`clause_number`),
  KEY `parent_id` (`parent_id`),
  KEY `review_status` (`review_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qms_clauses`
--

LOCK TABLES `qms_clauses` WRITE;
/*!40000 ALTER TABLE `qms_clauses` DISABLE KEYS */;
/*!40000 ALTER TABLE `qms_clauses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qms_document_assets`
--

DROP TABLE IF EXISTS `qms_document_assets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qms_document_assets`
--

LOCK TABLES `qms_document_assets` WRITE;
/*!40000 ALTER TABLE `qms_document_assets` DISABLE KEYS */;
/*!40000 ALTER TABLE `qms_document_assets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qms_document_block_links`
--

DROP TABLE IF EXISTS `qms_document_block_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qms_document_block_links`
--

LOCK TABLES `qms_document_block_links` WRITE;
/*!40000 ALTER TABLE `qms_document_block_links` DISABLE KEYS */;
/*!40000 ALTER TABLE `qms_document_block_links` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qms_document_blocks`
--

DROP TABLE IF EXISTS `qms_document_blocks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
  `sort_order` int DEFAULT '0',
  `source_locator` varchar(255) DEFAULT NULL,
  `status` enum('draft','effective','obsolete') DEFAULT 'effective',
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qms_document_blocks`
--

LOCK TABLES `qms_document_blocks` WRITE;
/*!40000 ALTER TABLE `qms_document_blocks` DISABLE KEYS */;
/*!40000 ALTER TABLE `qms_document_blocks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qms_document_change_logs`
--

DROP TABLE IF EXISTS `qms_document_change_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qms_document_change_logs`
--

LOCK TABLES `qms_document_change_logs` WRITE;
/*!40000 ALTER TABLE `qms_document_change_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `qms_document_change_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qms_element_clause_links`
--

DROP TABLE IF EXISTS `qms_element_clause_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qms_element_clause_links` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `element_id` varchar(36) NOT NULL,
  `clause_id` varchar(36) NOT NULL,
  `mapping_type` enum('equivalent','partial','supplement','reference') DEFAULT 'equivalent',
  `is_primary` tinyint(1) DEFAULT '0' COMMENT '主27025条款，用于默认排序',
  `note` text,
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `element_clause` (`element_id`,`clause_id`),
  KEY `element_id` (`element_id`),
  KEY `clause_id` (`clause_id`),
  KEY `is_primary` (`is_primary`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qms_element_clause_links`
--

LOCK TABLES `qms_element_clause_links` WRITE;
/*!40000 ALTER TABLE `qms_element_clause_links` DISABLE KEYS */;
/*!40000 ALTER TABLE `qms_element_clause_links` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qms_element_documents`
--

DROP TABLE IF EXISTS `qms_element_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qms_element_documents` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `element_id` varchar(36) NOT NULL,
  `document_id` varchar(36) NOT NULL,
  `relation_type` enum('primary','reference') DEFAULT 'primary',
  `note` text,
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `element_document` (`element_id`,`document_id`),
  KEY `document_id` (`document_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qms_element_documents`
--

LOCK TABLES `qms_element_documents` WRITE;
/*!40000 ALTER TABLE `qms_element_documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `qms_element_documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qms_element_responsibilities`
--

DROP TABLE IF EXISTS `qms_element_responsibilities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qms_element_responsibilities` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `element_id` varchar(36) NOT NULL,
  `position_id` varchar(36) NOT NULL,
  `responsibility_type` enum('decision_owner','organizer','participant') NOT NULL,
  `note` text,
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `element_position_type` (`element_id`,`position_id`,`responsibility_type`),
  KEY `position_id` (`position_id`),
  KEY `responsibility_type` (`responsibility_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qms_element_responsibilities`
--

LOCK TABLES `qms_element_responsibilities` WRITE;
/*!40000 ALTER TABLE `qms_element_responsibilities` DISABLE KEYS */;
/*!40000 ALTER TABLE `qms_element_responsibilities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qms_elements`
--

DROP TABLE IF EXISTS `qms_elements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
  `sort_order` int DEFAULT '0',
  `last_reviewed_at` datetime DEFAULT NULL,
  `next_review_due` date DEFAULT NULL,
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qms_elements`
--

LOCK TABLES `qms_elements` WRITE;
/*!40000 ALTER TABLE `qms_elements` DISABLE KEYS */;
/*!40000 ALTER TABLE `qms_elements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qms_external_change_candidates`
--

DROP TABLE IF EXISTS `qms_external_change_candidates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qms_external_change_candidates` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `monitor_run_id` varchar(36) NOT NULL,
  `source_key` varchar(100) NOT NULL,
  `source_mode` enum('html_list','manual_only') NOT NULL DEFAULT 'html_list',
  `source_item_key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT '来源项目唯一键',
  `source_url` varchar(500) DEFAULT NULL,
  `normalized_url` varchar(1000) DEFAULT NULL,
  `title` varchar(300) NOT NULL,
  `announcement_number` varchar(120) DEFAULT NULL,
  `document_type` varchar(80) DEFAULT NULL,
  `published_date` date DEFAULT NULL,
  `effective_date` date DEFAULT NULL,
  `first_seen_at` datetime NOT NULL,
  `last_seen_at` datetime NOT NULL,
  `content_hash` char(64) NOT NULL COMMENT '来源内容哈希',
  `evidence_summary` text,
  `evidence_refs` json DEFAULT NULL,
  `evidence_json` json DEFAULT NULL COMMENT '候选证据快照',
  `supersedes_candidate_id` varchar(36) DEFAULT NULL,
  `relevance` enum('high','medium','low','unknown') NOT NULL DEFAULT 'unknown',
  `preliminary_applicability` enum('likely_applicable','needs_review','likely_not_applicable') NOT NULL DEFAULT 'needs_review',
  `impact_analysis` json DEFAULT NULL COMMENT '六类影响初判',
  `analysis_rule_version` varchar(80) DEFAULT NULL,
  `analysis_confidence` decimal(5,4) DEFAULT NULL,
  `analysis_rationale` text,
  `graph_snapshot_hash` char(64) DEFAULT NULL,
  `review_status` enum('pending','confirmed_applicable','confirmed_not_applicable','deferred','promoted') NOT NULL DEFAULT 'pending',
  `reviewed_by` varchar(36) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `review_comment` text,
  `promoted_event_id` varchar(36) DEFAULT NULL,
  `promoted_at` datetime DEFAULT NULL,
  `promotion_error_summary` text,
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_source_item_content` (`company_id`,`source_key`,`source_item_key`,`content_hash`),
  KEY `monitor_run_id` (`monitor_run_id`),
  KEY `source_review_status` (`source_key`,`review_status`),
  KEY `supersedes_candidate_id` (`supersedes_candidate_id`),
  KEY `promoted_event_id` (`promoted_event_id`),
  KEY `published_date` (`published_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qms_external_change_candidates`
--

LOCK TABLES `qms_external_change_candidates` WRITE;
/*!40000 ALTER TABLE `qms_external_change_candidates` DISABLE KEYS */;
/*!40000 ALTER TABLE `qms_external_change_candidates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qms_external_change_events`
--

DROP TABLE IF EXISTS `qms_external_change_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qms_external_change_events` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `event_code` varchar(80) NOT NULL COMMENT '变更事件编号',
  `source_kind` enum('cnas','samr','standard_platform','gb','internal','other') NOT NULL DEFAULT 'cnas' COMMENT '公告来源类型',
  `source_name` varchar(300) NOT NULL COMMENT '公告/依据名称',
  `source_url` varchar(500) DEFAULT NULL COMMENT '公告或查新链接',
  `announcement_number` varchar(120) DEFAULT NULL COMMENT '公告编号',
  `old_source_id` varchar(36) DEFAULT NULL COMMENT '旧外部依据 qms_sources.id',
  `new_source_id` varchar(36) DEFAULT NULL COMMENT '新外部依据 qms_sources.id',
  `old_version` varchar(80) DEFAULT NULL,
  `new_version` varchar(80) DEFAULT NULL,
  `published_date` date DEFAULT NULL,
  `effective_date` date DEFAULT NULL,
  `event_summary` text NOT NULL,
  `graph_snapshot_hash` char(64) DEFAULT NULL COMMENT '登记时追溯图谱快照哈希',
  `status` enum('registered','assessing','revising','closed','exempted') DEFAULT 'registered',
  `close_reason` text,
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_code` (`event_code`),
  KEY `company_status` (`company_id`,`status`),
  KEY `source_kind` (`source_kind`),
  KEY `effective_date` (`effective_date`),
  KEY `old_source_id` (`old_source_id`),
  KEY `new_source_id` (`new_source_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qms_external_change_events`
--

LOCK TABLES `qms_external_change_events` WRITE;
/*!40000 ALTER TABLE `qms_external_change_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `qms_external_change_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qms_manual_sections`
--

DROP TABLE IF EXISTS `qms_manual_sections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qms_manual_sections` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `document_id` varchar(36) DEFAULT NULL,
  `element_id` varchar(36) DEFAULT NULL,
  `parent_id` varchar(36) DEFAULT NULL,
  `section_number` varchar(80) NOT NULL,
  `title` varchar(500) NOT NULL,
  `level` tinyint DEFAULT '1',
  `summary` text,
  `status` enum('draft','effective','obsolete') DEFAULT 'effective',
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `manual_section` (`document_id`,`section_number`),
  KEY `element_id` (`element_id`),
  KEY `section_number` (`section_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qms_manual_sections`
--

LOCK TABLES `qms_manual_sections` WRITE;
/*!40000 ALTER TABLE `qms_manual_sections` DISABLE KEYS */;
/*!40000 ALTER TABLE `qms_manual_sections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qms_position_aliases`
--

DROP TABLE IF EXISTS `qms_position_aliases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qms_position_aliases` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `position_id` varchar(36) NOT NULL,
  `alias` varchar(200) NOT NULL,
  `confirmation_status` enum('confirmed','review_required') NOT NULL DEFAULT 'review_required',
  `source_scope` varchar(100) NOT NULL DEFAULT 'position_catalog',
  `site_id` varchar(36) DEFAULT NULL,
  `site_scope_key` varchar(36) NOT NULL DEFAULT '*',
  `confirmation_note` text,
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_alias_scope` (`company_id`,`alias`,`source_scope`,`site_scope_key`),
  KEY `position_id` (`position_id`),
  KEY `confirmation_status` (`confirmation_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qms_position_aliases`
--

LOCK TABLES `qms_position_aliases` WRITE;
/*!40000 ALTER TABLE `qms_position_aliases` DISABLE KEYS */;
/*!40000 ALTER TABLE `qms_position_aliases` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qms_positions`
--

DROP TABLE IF EXISTS `qms_positions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qms_positions` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `code` varchar(100) NOT NULL,
  `name` varchar(200) NOT NULL,
  `department_hint` varchar(200) DEFAULT NULL,
  `source` varchar(120) DEFAULT NULL,
  `description` text,
  `review_status` enum('draft','published','obsolete') DEFAULT 'published',
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `review_status` (`review_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qms_positions`
--

LOCK TABLES `qms_positions` WRITE;
/*!40000 ALTER TABLE `qms_positions` DISABLE KEYS */;
INSERT INTO `qms_positions` VALUES ('sim-gov-pos-as','00000000-0000-0000-0000-000000000001','authorized_signatory','SIM-æŽˆæƒç­¾å­—äºº',NULL,'SIM-GOV-20260719','ä»…ç”¨äºŽå®Œæ•´æ²™ç®±æ¼”ç»ƒ','published',1,0,'2026-07-19 15:46:52','2026-07-19 15:46:52',NULL,NULL),('sim-gov-pos-dc','00000000-0000-0000-0000-000000000001','document_controller','SIM-æ–‡ä»¶ç®¡ç†å‘˜',NULL,'SIM-GOV-20260719','ä»…ç”¨äºŽå®Œæ•´æ²™ç®±æ¼”ç»ƒ','published',1,0,'2026-07-19 15:46:52','2026-07-19 15:46:52',NULL,NULL),('sim-gov-pos-em','00000000-0000-0000-0000-000000000001','equipment_manager','SIM-è®¾å¤‡ç®¡ç†å‘˜',NULL,'SIM-GOV-20260719','ä»…ç”¨äºŽå®Œæ•´æ²™ç®±æ¼”ç»ƒ','published',1,0,'2026-07-19 15:46:52','2026-07-19 15:46:52',NULL,NULL),('sim-gov-pos-ia','00000000-0000-0000-0000-000000000001','internal_auditor','SIM-å†…å®¡å‘˜',NULL,'SIM-GOV-20260719','ä»…ç”¨äºŽå®Œæ•´æ²™ç®±æ¼”ç»ƒ','published',1,0,'2026-07-19 15:46:52','2026-07-19 15:46:52',NULL,NULL),('sim-gov-pos-qm','00000000-0000-0000-0000-000000000001','quality_manager','SIM-è´¨é‡è´Ÿè´£äºº',NULL,'SIM-GOV-20260719','ä»…ç”¨äºŽå®Œæ•´æ²™ç®±æ¼”ç»ƒ','published',1,0,'2026-07-19 15:46:52','2026-07-19 15:46:52',NULL,NULL),('sim-gov-pos-sm','00000000-0000-0000-0000-000000000001','sample_manager','SIM-æ ·å“ç®¡ç†å‘˜',NULL,'SIM-GOV-20260719','ä»…ç”¨äºŽå®Œæ•´æ²™ç®±æ¼”ç»ƒ','published',1,0,'2026-07-19 15:46:52','2026-07-19 15:46:52',NULL,NULL),('sim-gov-pos-tm','00000000-0000-0000-0000-000000000001','technical_manager','SIM-æŠ€æœ¯è´Ÿè´£äºº',NULL,'SIM-GOV-20260719','ä»…ç”¨äºŽå®Œæ•´æ²™ç®±æ¼”ç»ƒ','published',1,0,'2026-07-19 15:46:52','2026-07-19 15:46:52',NULL,NULL),('sim-gov-pos-tp','00000000-0000-0000-0000-000000000001','testing_personnel','SIM-æ£€æµ‹å‘˜',NULL,'SIM-GOV-20260719','ä»…ç”¨äºŽå®Œæ•´æ²™ç®±æ¼”ç»ƒ','published',1,0,'2026-07-19 15:46:52','2026-07-19 15:46:52',NULL,NULL),('sim-gov-pos-tr','00000000-0000-0000-0000-000000000001','training_manager','SIM-åŸ¹è®­æŽˆæƒç®¡ç†å‘˜',NULL,'SIM-GOV-20260719','ä»…ç”¨äºŽå®Œæ•´æ²™ç®±æ¼”ç»ƒ','published',1,0,'2026-07-19 15:46:52','2026-07-19 15:46:52',NULL,NULL);
/*!40000 ALTER TABLE `qms_positions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qms_quality_objectives`
--

DROP TABLE IF EXISTS `qms_quality_objectives`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
  `management_review_input` tinyint(1) DEFAULT '1',
  `review_status` enum('candidate','draft','pending_review','published','rejected','obsolete') DEFAULT 'draft',
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `year` (`year`),
  KEY `department_id` (`department_id`),
  KEY `review_status` (`review_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qms_quality_objectives`
--

LOCK TABLES `qms_quality_objectives` WRITE;
/*!40000 ALTER TABLE `qms_quality_objectives` DISABLE KEYS */;
/*!40000 ALTER TABLE `qms_quality_objectives` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qms_quality_policies`
--

DROP TABLE IF EXISTS `qms_quality_policies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qms_quality_policies` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `title` varchar(300) NOT NULL,
  `policy_text` text NOT NULL,
  `version` varchar(80) DEFAULT NULL,
  `effective_date` date DEFAULT NULL,
  `source_document_id` varchar(36) DEFAULT NULL,
  `is_current` tinyint(1) DEFAULT '0',
  `management_review_input` tinyint(1) DEFAULT '1',
  `review_status` enum('candidate','draft','pending_review','published','rejected','obsolete') DEFAULT 'draft',
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `is_current` (`is_current`),
  KEY `review_status` (`review_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qms_quality_policies`
--

LOCK TABLES `qms_quality_policies` WRITE;
/*!40000 ALTER TABLE `qms_quality_policies` DISABLE KEYS */;
/*!40000 ALTER TABLE `qms_quality_policies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qms_reference_procedure_matches`
--

DROP TABLE IF EXISTS `qms_reference_procedure_matches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
  `soft_delete` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference_manual_match` (`reference_doc_number`,`reference_section_number`,`status`,`soft_delete`),
  KEY `procedure_document_id` (`procedure_document_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qms_reference_procedure_matches`
--

LOCK TABLES `qms_reference_procedure_matches` WRITE;
/*!40000 ALTER TABLE `qms_reference_procedure_matches` DISABLE KEYS */;
/*!40000 ALTER TABLE `qms_reference_procedure_matches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qms_regulatory_monitor_runs`
--

DROP TABLE IF EXISTS `qms_regulatory_monitor_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qms_regulatory_monitor_runs` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `run_code` varchar(80) NOT NULL COMMENT '监测批次编号',
  `trigger_mode` enum('scheduled','manual') NOT NULL,
  `started_at` datetime NOT NULL,
  `finished_at` datetime DEFAULT NULL,
  `source_stats` json DEFAULT NULL COMMENT '来源抓取统计',
  `candidate_stats` json DEFAULT NULL COMMENT '候选生成统计',
  `status` enum('running','completed','partial_failed','failed') NOT NULL DEFAULT 'running',
  `result_json` json DEFAULT NULL,
  `error_summary` text,
  `execution_version` varchar(80) DEFAULT NULL,
  `source_config_version` varchar(80) DEFAULT NULL,
  `rule_version` varchar(80) DEFAULT NULL,
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_run_code` (`company_id`,`run_code`),
  KEY `company_status` (`company_id`,`status`),
  KEY `started_at` (`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qms_regulatory_monitor_runs`
--

LOCK TABLES `qms_regulatory_monitor_runs` WRITE;
/*!40000 ALTER TABLE `qms_regulatory_monitor_runs` DISABLE KEYS */;
/*!40000 ALTER TABLE `qms_regulatory_monitor_runs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qms_responsibility_activities`
--

DROP TABLE IF EXISTS `qms_responsibility_activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qms_responsibility_activities` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `chain_version_id` varchar(36) NOT NULL,
  `activity_code` varchar(100) NOT NULL,
  `name` varchar(200) NOT NULL,
  `element_key` varchar(80) DEFAULT NULL,
  `site_scope` enum('all','site') NOT NULL DEFAULT 'all',
  `source_refs` json DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chain_activity` (`chain_version_id`,`activity_code`),
  KEY `chain_version_id` (`chain_version_id`),
  KEY `element_key` (`element_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qms_responsibility_activities`
--

LOCK TABLES `qms_responsibility_activities` WRITE;
/*!40000 ALTER TABLE `qms_responsibility_activities` DISABLE KEYS */;
/*!40000 ALTER TABLE `qms_responsibility_activities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qms_responsibility_approvals`
--

DROP TABLE IF EXISTS `qms_responsibility_approvals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qms_responsibility_approvals` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `approval_scope` enum('governance_bootstrap','chain_version','assignment') NOT NULL,
  `chain_version_id` varchar(36) DEFAULT NULL,
  `assignment_id` varchar(36) DEFAULT NULL,
  `subject_employee_id` varchar(36) DEFAULT NULL,
  `subject_position_id` varchar(36) DEFAULT NULL,
  `approver_user_id` varchar(36) DEFAULT NULL,
  `approver_employee_id` varchar(36) DEFAULT NULL,
  `approver_position_code` varchar(100) DEFAULT NULL,
  `batch_key` varchar(64) DEFAULT NULL,
  `decision` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `comments` text,
  `version_hash` char(64) DEFAULT NULL,
  `signature_metadata` json DEFAULT NULL,
  `signed_at` datetime DEFAULT NULL,
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `chain_decision` (`chain_version_id`,`decision`),
  KEY `approver_decision` (`approver_employee_id`,`decision`),
  KEY `batch_key` (`batch_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qms_responsibility_approvals`
--

LOCK TABLES `qms_responsibility_approvals` WRITE;
/*!40000 ALTER TABLE `qms_responsibility_approvals` DISABLE KEYS */;
/*!40000 ALTER TABLE `qms_responsibility_approvals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qms_responsibility_assignments`
--

DROP TABLE IF EXISTS `qms_responsibility_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qms_responsibility_assignments` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `responsibility_id` varchar(36) NOT NULL,
  `employee_id` varchar(36) NOT NULL,
  `site_id` varchar(36) DEFAULT NULL,
  `site_scope_key` varchar(36) NOT NULL DEFAULT '*',
  `proposed_from` date DEFAULT NULL,
  `proposed_until` date DEFAULT NULL,
  `competence_snapshot` json DEFAULT NULL,
  `validation_status` enum('pass','warning','blocker') DEFAULT NULL,
  `validation_details` json DEFAULT NULL,
  `status` enum('draft','pending_approval','active','revoked','expired') NOT NULL DEFAULT 'draft',
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `responsibility_employee_scope` (`responsibility_id`,`employee_id`,`site_scope_key`),
  KEY `responsibility_id` (`responsibility_id`),
  KEY `employee_id` (`employee_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qms_responsibility_assignments`
--

LOCK TABLES `qms_responsibility_assignments` WRITE;
/*!40000 ALTER TABLE `qms_responsibility_assignments` DISABLE KEYS */;
/*!40000 ALTER TABLE `qms_responsibility_assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qms_responsibility_chain_versions`
--

DROP TABLE IF EXISTS `qms_responsibility_chain_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qms_responsibility_chain_versions` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `chain_code` varchar(100) NOT NULL,
  `version_no` int NOT NULL,
  `status` enum('draft','pending_approval','effective','superseded','revoked') NOT NULL DEFAULT 'draft',
  `content_hash` char(64) DEFAULT NULL,
  `replaces_version_id` varchar(36) DEFAULT NULL,
  `locked_at` datetime DEFAULT NULL,
  `effective_at` datetime DEFAULT NULL,
  `superseded_at` datetime DEFAULT NULL,
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_chain_version` (`company_id`,`chain_code`,`version_no`),
  KEY `chain_status` (`chain_code`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qms_responsibility_chain_versions`
--

LOCK TABLES `qms_responsibility_chain_versions` WRITE;
/*!40000 ALTER TABLE `qms_responsibility_chain_versions` DISABLE KEYS */;
/*!40000 ALTER TABLE `qms_responsibility_chain_versions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qms_sources`
--

DROP TABLE IF EXISTS `qms_sources`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `source_code` (`source_code`),
  KEY `freshness_status` (`freshness_status`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qms_sources`
--

LOCK TABLES `qms_sources` WRITE;
/*!40000 ALTER TABLE `qms_sources` DISABLE KEYS */;
/*!40000 ALTER TABLE `qms_sources` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qms_structured_documents`
--

DROP TABLE IF EXISTS `qms_structured_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qms_structured_documents`
--

LOCK TABLES `qms_structured_documents` WRITE;
/*!40000 ALTER TABLE `qms_structured_documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `qms_structured_documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `record_form_instances`
--

DROP TABLE IF EXISTS `record_form_instances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `record_form_instances`
--

LOCK TABLES `record_form_instances` WRITE;
/*!40000 ALTER TABLE `record_form_instances` DISABLE KEYS */;
/*!40000 ALTER TABLE `record_form_instances` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `record_form_templates`
--

DROP TABLE IF EXISTS `record_form_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
  `review_status` enum('pending','ai_generated','field_confirmed','needs_fidelity','deferred','completed') DEFAULT 'pending',
  `review_note` text,
  `reviewed_at` datetime DEFAULT NULL,
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `record_form_templates`
--

LOCK TABLES `record_form_templates` WRITE;
/*!40000 ALTER TABLE `record_form_templates` DISABLE KEYS */;
/*!40000 ALTER TABLE `record_form_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reference_materials`
--

DROP TABLE IF EXISTS `reference_materials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reference_materials` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(200) NOT NULL,
  `lot_number` varchar(100) DEFAULT NULL,
  `manufacturer` varchar(200) DEFAULT NULL,
  `traceability_certificate_number` varchar(100) DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `storage_location` varchar(200) DEFAULT NULL,
  `status` enum('active','expired','depleted','discarded') DEFAULT 'active',
  `remarks` text,
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference_material_code` (`code`),
  KEY `valid_until` (`valid_until`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reference_materials`
--

LOCK TABLES `reference_materials` WRITE;
/*!40000 ALTER TABLE `reference_materials` DISABLE KEYS */;
/*!40000 ALTER TABLE `reference_materials` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `review_actions`
--

DROP TABLE IF EXISTS `review_actions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `review_actions` (
  `id` varchar(36) NOT NULL,
  `management_review_id` varchar(36) NOT NULL,
  `action_item` text NOT NULL,
  `responsible_id` varchar(36) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `status` enum('open','in_progress','completed','overdue') DEFAULT 'open',
  `completion_date` date DEFAULT NULL,
  `verification` text,
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `review_actions`
--

LOCK TABLES `review_actions` WRITE;
/*!40000 ALTER TABLE `review_actions` DISABLE KEYS */;
/*!40000 ALTER TABLE `review_actions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sites`
--

DROP TABLE IF EXISTS `sites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sites` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(200) NOT NULL,
  `address` text,
  `site_type` enum('main','branch') DEFAULT 'branch',
  `contact_person` varchar(100) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `sort_order` int DEFAULT '0',
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `site_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sites`
--

LOCK TABLES `sites` WRITE;
/*!40000 ALTER TABLE `sites` DISABLE KEYS */;
INSERT INTO `sites` VALUES ('00000000-0000-0000-0000-000000000070','00000000-0000-0000-0000-000000000001','MAIN','主场所',NULL,'main',NULL,NULL,'active',0,1,0,'2026-07-19 15:43:58',NULL,NULL);
/*!40000 ALTER TABLE `sites` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `supplier_evaluations`
--

DROP TABLE IF EXISTS `supplier_evaluations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `supplier_evaluations`
--

LOCK TABLES `supplier_evaluations` WRITE;
/*!40000 ALTER TABLE `supplier_evaluations` DISABLE KEYS */;
/*!40000 ALTER TABLE `supplier_evaluations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `suppliers`
--

DROP TABLE IF EXISTS `suppliers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `suppliers`
--

LOCK TABLES `suppliers` WRITE;
/*!40000 ALTER TABLE `suppliers` DISABLE KEYS */;
/*!40000 ALTER TABLE `suppliers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_settings`
--

DROP TABLE IF EXISTS `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_settings` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='系统级配置';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_settings`
--

LOCK TABLES `system_settings` WRITE;
/*!40000 ALTER TABLE `system_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `system_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `training_plans`
--

DROP TABLE IF EXISTS `training_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `training_plans` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `plan_year` year NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text,
  `status` enum('draft','approved','completed') DEFAULT 'draft',
  `approved_by` varchar(36) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `training_plans`
--

LOCK TABLES `training_plans` WRITE;
/*!40000 ALTER TABLE `training_plans` DISABLE KEYS */;
/*!40000 ALTER TABLE `training_plans` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `training_records`
--

DROP TABLE IF EXISTS `training_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `training_records` (
  `id` varchar(36) NOT NULL,
  `training_id` varchar(36) NOT NULL,
  `employee_id` varchar(36) NOT NULL,
  `attendance` enum('present','absent','excused') DEFAULT 'present',
  `evaluation_score` decimal(5,2) DEFAULT NULL,
  `evaluation_result` enum('pass','fail','pending') DEFAULT 'pending',
  `remarks` text,
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `training_records`
--

LOCK TABLES `training_records` WRITE;
/*!40000 ALTER TABLE `training_records` DISABLE KEYS */;
/*!40000 ALTER TABLE `training_records` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `trainings`
--

DROP TABLE IF EXISTS `trainings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `trainings`
--

LOCK TABLES `trainings` WRITE;
/*!40000 ALTER TABLE `trainings` DISABLE KEYS */;
/*!40000 ALTER TABLE `trainings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_sessions`
--

DROP TABLE IF EXISTS `user_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_sessions` (
  `id` varchar(36) NOT NULL,
  `user_id` varchar(36) NOT NULL,
  `start_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_sessions`
--

LOCK TABLES `user_sessions` WRITE;
/*!40000 ALTER TABLE `user_sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
  `is_mr` tinyint(1) DEFAULT '0' COMMENT '质量负责人',
  `is_approver` tinyint(1) DEFAULT '0',
  `user_access` text COMMENT 'JSON权限',
  `last_login` datetime DEFAULT NULL,
  `publish` tinyint(1) DEFAULT '1',
  `soft_delete` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES ('00000000-0000-0000-0000-000000000040','00000000-0000-0000-0000-000000000001','00000000-0000-0000-0000-000000000030','00000000-0000-0000-0000-000000000010','admin','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','系统管理员','admin@lab.com','admin',1,1,NULL,NULL,1,0,'2026-07-19 15:43:58','2026-07-19 15:43:58'),('sim-gov-user-as','00000000-0000-0000-0000-000000000001','sim-gov-emp-as','00000000-0000-0000-0000-000000000011','sim_as','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','SIM-æŽˆæƒç­¾å­—äºº','sim-as@example.invalid','staff',0,0,NULL,NULL,1,0,'2026-07-19 15:46:52','2026-07-19 15:46:52'),('sim-gov-user-dc','00000000-0000-0000-0000-000000000001','sim-gov-emp-dc','00000000-0000-0000-0000-000000000012','sim_dc','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','SIM-æ–‡ä»¶ç®¡ç†å‘˜','sim-dc@example.invalid','staff',0,0,NULL,NULL,1,0,'2026-07-19 15:46:52','2026-07-19 15:46:52'),('sim-gov-user-em','00000000-0000-0000-0000-000000000001','sim-gov-emp-em','00000000-0000-0000-0000-000000000012','sim_em','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','SIM-è®¾å¤‡ç®¡ç†å‘˜','sim-em@example.invalid','staff',0,0,NULL,NULL,1,0,'2026-07-19 15:46:52','2026-07-19 15:46:52'),('sim-gov-user-ia','00000000-0000-0000-0000-000000000001','sim-gov-emp-ia','00000000-0000-0000-0000-000000000010','sim_ia','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','SIM-å†…å®¡å‘˜','sim-ia@example.invalid','staff',0,0,NULL,NULL,1,0,'2026-07-19 15:46:52','2026-07-19 15:46:52'),('sim-gov-user-qm','00000000-0000-0000-0000-000000000001','sim-gov-emp-qm','00000000-0000-0000-0000-000000000010','sim_qm','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','SIM-è´¨é‡è´Ÿè´£äºº','sim-qm@example.invalid','staff',0,0,NULL,NULL,1,0,'2026-07-19 15:46:52','2026-07-19 15:46:52'),('sim-gov-user-sm','00000000-0000-0000-0000-000000000001','sim-gov-emp-sm','00000000-0000-0000-0000-000000000011','sim_sm','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','SIM-æ ·å“ç®¡ç†å‘˜','sim-sm@example.invalid','staff',0,0,NULL,NULL,1,0,'2026-07-19 15:46:52','2026-07-19 15:46:52'),('sim-gov-user-tm','00000000-0000-0000-0000-000000000001','sim-gov-emp-tm','00000000-0000-0000-0000-000000000011','sim_tm','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','SIM-æŠ€æœ¯è´Ÿè´£äºº','sim-tm@example.invalid','staff',0,0,NULL,NULL,1,0,'2026-07-19 15:46:52','2026-07-19 15:46:52'),('sim-gov-user-tp','00000000-0000-0000-0000-000000000001','sim-gov-emp-tp','00000000-0000-0000-0000-000000000011','sim_tp','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','SIM-æ£€æµ‹å‘˜','sim-tp@example.invalid','staff',0,0,NULL,NULL,1,0,'2026-07-19 15:46:52','2026-07-19 15:46:52'),('sim-gov-user-tr','00000000-0000-0000-0000-000000000001','sim-gov-emp-tr','00000000-0000-0000-0000-000000000012','sim_tr','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','SIM-åŸ¹è®­æŽˆæƒç®¡ç†å‘˜','sim-tr@example.invalid','staff',0,0,NULL,NULL,1,0,'2026-07-19 15:46:52','2026-07-19 15:46:52');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'jewelry_qms'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-19 15:47:40
