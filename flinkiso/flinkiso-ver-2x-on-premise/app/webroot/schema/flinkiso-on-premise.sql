SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
--
-- Database: `flinkiso`
--

-- --------------------------------------------------------

--
-- Table structure for table `approvals`
--

CREATE TABLE `approvals` (
  `id` varchar(36) NOT NULL,
  `sr_no` int(11) NOT NULL,
  `title` varchar(255) NOT NULL DEFAULT 'Approval Title',
  `model_name` varchar(250) NOT NULL,
  `controller_name` varchar(250) NOT NULL,
  `record` varchar(36) NOT NULL,
  `from` varchar(36) NOT NULL,
  `user_id` varchar(36) DEFAULT NULL,
  `comments` text,
  `approval_step` int(2) DEFAULT NULL,
  `status` varchar(120) DEFAULT NULL,
  `approval_mode` int(1) DEFAULT '0' COMMENT '0=view-only, 1=edit',
  `approval_type` int(1) DEFAULT '0' COMMENT '0=all, 1=any',
  `approval_cycle` int(11) DEFAULT '0',
  `approval_status` int(1) DEFAULT '0' COMMENT '0=open, 1=approved 2=reject',
  `approver_comments` text,
  `publish` tinyint(1) DEFAULT '1' COMMENT '0=Un 1=Pub',
  `record_status` tinyint(1) DEFAULT '0' COMMENT '0=Un-locked, 1=Locked',
  `status_user_id` varchar(36) DEFAULT NULL,
  `created_by` varchar(36) NOT NULL,
  `created` datetime NOT NULL,
  `modified_by` varchar(36) NOT NULL,
  `approved_by` varchar(36) DEFAULT NULL,
  `prepared_by` varchar(36) DEFAULT NULL,
  `modified` datetime NOT NULL,
  `soft_delete` tinyint(1) NOT NULL DEFAULT '0',
  `branchid` varchar(36) DEFAULT NULL,
  `departmentid` varchar(36) DEFAULT NULL,
  `company_id` varchar(36) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `approval_comments`
--

CREATE TABLE `approval_comments` (
  `id` varchar(36) NOT NULL,
  `sr_no` int(11) NOT NULL,
  `approval_id` varchar(36) NOT NULL,
  `from` varchar(36) DEFAULT NULL,
  `user_id` varchar(36) DEFAULT NULL,
  `comments` text,
  `response` text,
  `response_status` int(1) DEFAULT '0' COMMENT '0=open, 1=responded',
  `publish` tinyint(1) DEFAULT '1' COMMENT '0=Un 1=Pub',
  `created_by` varchar(36) NOT NULL,
  `created` datetime NOT NULL,
  `modified_by` varchar(36) NOT NULL,
  `modified` datetime NOT NULL,
  `soft_delete` tinyint(1) NOT NULL DEFAULT '0',
  `branchid` varchar(36) DEFAULT NULL,
  `departmentid` varchar(36) DEFAULT NULL,
  `company_id` varchar(36) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

CREATE TABLE `branches` (
  `id` varchar(36) NOT NULL,
  `sr_no` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `details` text,
  `departments` text,
  `publish` tinyint(1) DEFAULT '0' COMMENT '0=Un 1=Pub',
  `record_status` tinyint(1) DEFAULT '0' COMMENT '0=Un-locked, 1=Locked',
  `status_user_id` varchar(36) DEFAULT NULL,
  `soft_delete` tinyint(1) DEFAULT '0' COMMENT '1=deleted',
  `branchid` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `departmentid` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `created_by` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `created` datetime NOT NULL COMMENT 'system defined automatically add',
  `modified_by` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `approved_by` varchar(36) DEFAULT NULL,
  `prepared_by` varchar(36) DEFAULT NULL,
  `modified` datetime NOT NULL COMMENT 'system defined automatically add',
  `system_table_id` varchar(36) DEFAULT '0',
  `company_id` varchar(36) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `chd_mrm0_child_1_v1s`
--

CREATE TABLE `chd_mrm0_child_1_v1s` (
  `id` varchar(36) NOT NULL,
  `sr_no` int(11) NOT NULL,
  `current_status` int(1) DEFAULT NULL,
  `closure_comments` text,
  `target_date` date DEFAULT NULL,
  `assigned_to` varchar(36) DEFAULT NULL,
  `agenda_details` varchar(255) DEFAULT NULL,
  `audit_number` varchar(255) NOT NULL,
  `qc_document_id` varchar(36) NOT NULL DEFAULT 'ea17ca9b-c5ec-4f0d-ad7c-14f534b5f32d',
  `custom_table_id` varchar(36) NOT NULL DEFAULT '2fcdbccf-b34b-467e-957e-da9a1eb934e2',
  `file_id` varchar(36) DEFAULT NULL,
  `file_key` varchar(50) DEFAULT NULL,
  `parent_id` varchar(36) DEFAULT NULL,
  `additional_files` text,
  `publish` tinyint(1) DEFAULT '1' COMMENT '0=Un 1=Pub',
  `record_status` tinyint(1) DEFAULT '0' COMMENT '0=Un-locked, 1=Locked',
  `status_user_id` varchar(36) DEFAULT NULL,
  `created_by` varchar(36) NOT NULL,
  `created` datetime NOT NULL,
  `modified_by` varchar(36) NOT NULL,
  `approved_by` varchar(36) DEFAULT NULL,
  `prepared_by` varchar(36) DEFAULT NULL,
  `modified` datetime NOT NULL,
  `soft_delete` tinyint(1) NOT NULL DEFAULT '0',
  `branchid` varchar(36) DEFAULT NULL,
  `departmentid` varchar(36) DEFAULT NULL,
  `company_id` varchar(36) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `clauses`
--

CREATE TABLE `clauses` (
  `id` varchar(36) NOT NULL,
  `sr_no` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `standard` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `standard_id` varchar(36) NOT NULL,
  `clause` varchar(8) NOT NULL,
  `sub-clause` varchar(120) DEFAULT NULL,
  `details` text,
  `additional_details` text,
  `tabs` text,
  `external_link_1` varchar(255) DEFAULT NULL,
  `external_link_2` varchar(255) DEFAULT NULL,
  `external_link_3` varchar(255) DEFAULT NULL,
  `external_link_4` varchar(255) DEFAULT NULL,
  `external_link_5` varchar(255) DEFAULT NULL,
  `system_tables` text,
  `publish` tinyint(1) DEFAULT '0' COMMENT '0=Un 1=Pub',
  `record_status` tinyint(1) DEFAULT '0' COMMENT '0=Un-locked, 1=Locked',
  `status_user_id` varchar(36) DEFAULT NULL,
  `soft_delete` tinyint(1) DEFAULT '0' COMMENT '1=deleted',
  `branchid` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `departmentid` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `created_by` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `created` datetime NOT NULL COMMENT 'system defined automatically add',
  `modified_by` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `approved_by` varchar(36) DEFAULT NULL,
  `prepared_by` varchar(36) DEFAULT NULL,
  `modified` datetime NOT NULL COMMENT 'system defined automatically add',
  `system_table_id` varchar(36) DEFAULT '0',
  `company_id` varchar(36) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `clauses`
--

INSERT INTO `clauses` (`id`, `sr_no`, `title`, `standard`, `standard_id`, `clause`, `sub-clause`, `details`, `additional_details`, `tabs`, `external_link_1`, `external_link_2`, `external_link_3`, `external_link_4`, `external_link_5`, `system_tables`, `publish`, `record_status`, `status_user_id`, `soft_delete`, `branchid`, `departmentid`, `created_by`, `created`, `modified_by`, `approved_by`, `prepared_by`, `modified`, `system_table_id`, `company_id`) VALUES
('33457b87-110e-4bba-bf46-24f34952d44b', 1, 'Scope', '2015', '58511238-fba8-4db9-aad0-833fc20b8995', '1', '', '<p>add scope</p>\r\n<p><strong>Adding new changes to the Scope.</strong></p>', '', '', '', '', '', '', '', NULL, 1, 0, NULL, 0, '43bcb91e-d10b-4d9c-9d62-edc6e97b6261', 'da75b706-16d4-41f2-92f5-6f1a0b165057', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2021-09-02 04:52:53', 'a3d53ee2-adcd-4433-9363-87aed5be038c', NULL, NULL, '2025-04-17 13:16:30', NULL, 'update_comany_id'),
('f7c60cc8-5e18-4107-acd6-091b02939554', 2, 'Normative references', '2015', '58511238-fba8-4db9-aad0-833fc20b8995', '2', '', 'Click on edit button to update this section.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, 0, '56044726-2adc-4ab0-a1a1-03e4db1e6cf9', '5b1fb6ec-2488-445f-bf26-4fe70a8e0005', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2021-09-02 04:52:53', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2021-09-02 04:52:53', '0', 'update_comany_id'),
('250b58fb-3570-465a-ab3a-88519ed89edc', 3, 'Terms and Definitions', '2015', '58511238-fba8-4db9-aad0-833fc20b8995', '3', '', '<p>Click on edit button to update this section.</p>', '', '', '', '', '', '', '', NULL, NULL, 0, NULL, 0, '43bcb91e-d10b-4d9c-9d62-edc6e97b6261', 'da75b706-16d4-41f2-92f5-6f1a0b165057', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2021-09-02 04:52:53', 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', NULL, NULL, '2025-07-03 16:54:49', NULL, 'update_comany_id'),
('0810bbab-4c7c-4e40-9ee6-d19c336b7528', 4, 'Context of the organization', '2015', '58511238-fba8-4db9-aad0-833fc20b8995', '4', '', 'Click on edit button to update this section.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, 0, '56044726-2adc-4ab0-a1a1-03e4db1e6cf9', '5b1fb6ec-2488-445f-bf26-4fe70a8e0005', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2021-09-02 04:52:53', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2021-09-02 04:52:53', '0', 'update_comany_id'),
('477653e6-09ee-43a7-a586-c9e996cb9241', 5, 'Understanding Context of the Organization', '2015', '58511238-fba8-4db9-aad0-833fc20b8995', '4', '4.1', 'Click on edit button to update this section.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, 0, '56044726-2adc-4ab0-a1a1-03e4db1e6cf9', '5b1fb6ec-2488-445f-bf26-4fe70a8e0005', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2021-09-02 04:52:53', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2021-09-02 04:52:53', '0', 'update_comany_id'),
('80eb0cb7-6d76-4d56-8728-9037d19874ce', 6, 'Understanding the needs and expectations of interested parties', '2015', '58511238-fba8-4db9-aad0-833fc20b8995', '4', '4.2', 'Click on edit button to update this section.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, 0, '56044726-2adc-4ab0-a1a1-03e4db1e6cf9', '5b1fb6ec-2488-445f-bf26-4fe70a8e0005', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2021-09-02 04:52:53', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2021-09-02 04:52:53', '0', 'update_comany_id'),
('039d000a-8400-4b14-b113-ff555ef3e491', 7, 'Quality management system and its processes', '2015', '58511238-fba8-4db9-aad0-833fc20b8995', '4', '4.4', 'Click on edit button to update this section.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, 0, '56044726-2adc-4ab0-a1a1-03e4db1e6cf9', '5b1fb6ec-2488-445f-bf26-4fe70a8e0005', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2021-09-02 04:52:53', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2021-09-02 04:52:53', '0', 'update_comany_id'),
('feebded0-121d-4a3f-bc65-8c867677686b', 8, 'Leadership', '2015', '58511238-fba8-4db9-aad0-833fc20b8995', '5', '', 'Click on edit button to update this section.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, 0, '56044726-2adc-4ab0-a1a1-03e4db1e6cf9', '5b1fb6ec-2488-445f-bf26-4fe70a8e0005', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2021-09-02 04:52:53', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2021-09-02 04:52:53', '0', 'update_comany_id'),
('0ff63756-7898-42fe-82c6-02a8e89a34da', 9, 'Leadership and commitment', '2015', '58511238-fba8-4db9-aad0-833fc20b8995', '5', '5.1', 'Click on edit button to update this section.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, 0, '56044726-2adc-4ab0-a1a1-03e4db1e6cf9', '5b1fb6ec-2488-445f-bf26-4fe70a8e0005', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2021-09-02 04:52:53', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2021-09-02 04:52:53', '0', 'update_comany_id'),
('282667ae-f4a1-4738-89ae-73c4e1533fb6', 10, 'Leadership And Commitment For The Quality Management System', '2015', '58511238-fba8-4db9-aad0-833fc20b8995', '5', '5.1.1', 'Click on edit button to update this section.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, 0, '56044726-2adc-4ab0-a1a1-03e4db1e6cf9', '5b1fb6ec-2488-445f-bf26-4fe70a8e0005', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2021-09-02 04:52:53', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2021-09-02 04:52:53', '0', 'update_comany_id'),
('1d11ae04-059b-4286-954a-f06a9826ce4a', 11, 'Customer Focus', '2015', '58511238-fba8-4db9-aad0-833fc20b8995', '5', '5.1.2', 'Click on edit button to update this section.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, 0, '56044726-2adc-4ab0-a1a1-03e4db1e6cf9', '5b1fb6ec-2488-445f-bf26-4fe70a8e0005', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2021-09-02 04:52:53', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2021-09-02 04:52:53', '0', 'update_comany_id'),
('d6bebd89-0ff2-4f1f-9846-f001cd01a5cc', 12, 'Policy', '2015', '58511238-fba8-4db9-aad0-833fc20b8995', '5', '5.2', 'Click on edit button to update this section.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, 0, '56044726-2adc-4ab0-a1a1-03e4db1e6cf9', '5b1fb6ec-2488-445f-bf26-4fe70a8e0005', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2021-09-02 04:52:53', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2021-09-02 04:52:53', '0', 'update_comany_id'),
('1144448a-6125-4517-b329-ca68e941a6a0', 13, 'Establishing the quality policy', '2015', '58511238-fba8-4db9-aad0-833fc20b8995', '5', '5.2.1', 'Click on edit button to update this section.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, 0, '56044726-2adc-4ab0-a1a1-03e4db1e6cf9', '5b1fb6ec-2488-445f-bf26-4fe70a8e0005', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2021-09-02 04:52:53', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2021-09-02 04:52:53', '0', 'update_comany_id'),
('887984b1-65dc-43c4-b361-4fa770e8c05b', 14, 'Communicating the quality policy', '2015', '58511238-fba8-4db9-aad0-833fc20b8995', '5', '5.2.2', 'Click on edit button to update this section.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, 0, '56044726-2adc-4ab0-a1a1-03e4db1e6cf9', '5b1fb6ec-2488-445f-bf26-4fe70a8e0005', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2021-09-02 04:52:53', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2021-09-02 04:52:53', '0', 'update_comany_id'),
('cfde6b48-6e46-45c9-9d54-96d807dedec3', 15, 'Planning', '2015', '58511238-fba8-4db9-aad0-833fc20b8995', '6', '', 'Click on edit button to update this section.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, 0, '56044726-2adc-4ab0-a1a1-03e4db1e6cf9', '5b1fb6ec-2488-445f-bf26-4fe70a8e0005', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2021-09-02 04:52:53', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2021-09-02 04:52:53', '0', 'update_comany_id'),
('eba0952e-52c2-4bbd-a9ca-19df876b2e34', 16, 'Actions to address risks and opportunities', '2015', '58511238-fba8-4db9-aad0-833fc20b8995', '6', '6.1', 'Click on edit button to update this section.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, 0, '56044726-2adc-4ab0-a1a1-03e4db1e6cf9', '5b1fb6ec-2488-445f-bf26-4fe70a8e0005', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2021-09-02 04:52:53', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2021-09-02 04:52:53', '0', 'update_comany_id'),
('3fe227cd-543d-468e-aa80-6a2b5b18dd63', 17, 'Quality objectives and planning to achieve them', '2015', '58511238-fba8-4db9-aad0-833fc20b8995', '6', '6.2', 'Click on edit button to update this section.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, 0, '56044726-2adc-4ab0-a1a1-03e4db1e6cf9', '5b1fb6ec-2488-445f-bf26-4fe70a8e0005', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2021-09-02 04:52:53', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2021-09-02 04:52:53', '0', 'update_comany_id'),
('ffe8d9ac-52b0-4fe8-a9e5-c0cf3c85a2c4', 18, 'Planning of changes', '2015', '58511238-fba8-4db9-aad0-833fc20b8995', '6', '6.3', 'Click on edit button to update this section.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, 0, '56044726-2adc-4ab0-a1a1-03e4db1e6cf9', '5b1fb6ec-2488-445f-bf26-4fe70a8e0005', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2021-09-02 04:52:53', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2021-09-02 04:52:53', '0', 'update_comany_id'),
('9b7a6dee-2bbc-42d5-b874-612f63ce9e04', 19, 'Support', '2015', '58511238-fba8-4db9-aad0-833fc20b8995', '7', '', 'Click on edit button to update this section.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, 0, '56044726-2adc-4ab0-a1a1-03e4db1e6cf9', '5b1fb6ec-2488-445f-bf26-4fe70a8e0005', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2021-09-02 04:52:53', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2021-09-02 04:52:53', '0', 'update_comany_id'),
('98d225bb-dd85-426f-b393-f46b35de4971', 20, 'Resources', '2015', '58511238-fba8-4db9-aad0-833fc20b8995', '7', '7.1', 'Click on edit button to update this section.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, 0, '56044726-2adc-4ab0-a1a1-03e4db1e6cf9', '5b1fb6ec-2488-445f-bf26-4fe70a8e0005', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2021-09-02 04:52:53', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2021-09-02 04:52:53', '0', 'update_comany_id'),
('c3a6f9a9-564d-4d80-8259-c85060e615fa', 21, 'General', '2015', '58511238-fba8-4db9-aad0-833fc20b8995', '7', '7.1.1', 'Click on edit button to update this section.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, 0, '56044726-2adc-4ab0-a1a1-03e4db1e6cf9', '5b1fb6ec-2488-445f-bf26-4fe70a8e0005', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2021-09-02 04:52:53', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2021-09-02 04:52:53', '0', 'update_comany_id'),
('db2eba0c-7cd2-451d-863f-64ad6f8282d8', 22, 'People', '2015', '58511238-fba8-4db9-aad0-833fc20b8995', '7', '7.1.2', 'Click on edit button to update this section.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, 0, '56044726-2adc-4ab0-a1a1-03e4db1e6cf9', '5b1fb6ec-2488-445f-bf26-4fe70a8e0005', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2021-09-02 04:52:53', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2021-09-02 04:52:53', '0', 'update_comany_id'),
('3083465f-044f-4d06-afbc-0dbb2bfc977e', 23, 'Infrastructure', '2015', '58511238-fba8-4db9-aad0-833fc20b8995', '7', '7.1.3', 'Click on edit button to update this section.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, 0, '56044726-2adc-4ab0-a1a1-03e4db1e6cf9', '5b1fb6ec-2488-445f-bf26-4fe70a8e0005', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2021-09-02 04:52:53', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2021-09-02 04:52:53', '0', 'update_comany_id'),
('c887821c-f01c-44a6-b045-fbc0ea99bcff', 24, 'Environment for the operation of processes', '2015', '58511238-fba8-4db9-aad0-833fc20b8995', '7', '7.1.4', 'Click on edit button to update this section.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, 0, '56044726-2adc-4ab0-a1a1-03e4db1e6cf9', '5b1fb6ec-2488-445f-bf26-4fe70a8e0005', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2021-09-02 04:52:53', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2021-09-02 04:52:53', '0', 'update_comany_id'),
('51aa0728-a93b-4f31-b035-1d28146e2dee', 25, 'Monitoring and measuring resources', '2015', '58511238-fba8-4db9-aad0-833fc20b8995', '7', '7.1.5', 'Click on edit button to update this section.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, 0, '56044726-2adc-4ab0-a1a1-03e4db1e6cf9', '5b1fb6ec-2488-445f-bf26-4fe70a8e0005', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2021-09-02 04:52:53', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2021-09-02 04:52:53', '0', 'update_comany_id'),
('795a904c-c569-4ce9-9f12-09ae100716c9', 26, 'Organizational knowledge', '2015', '58511238-fba8-4db9-aad0-833fc20b8995', '7', '7.1.6', 'Click on edit button to update this section.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, 0, '56044726-2adc-4ab0-a1a1-03e4db1e6cf9', '5b1fb6ec-2488-445f-bf26-4fe70a8e0005', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2021-09-02 04:52:53', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2021-09-02 04:52:53', '0', 'update_comany_id'),
('496d4be4-b336-4fe7-84b8-037a5ebc833a', 27, 'Competence', '2015', '58511238-fba8-4db9-aad0-833fc20b8995', '7', '7.2', 'Click on edit button to update this section.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, 0, '56044726-2adc-4ab0-a1a1-03e4db1e6cf9', '5b1fb6ec-2488-445f-bf26-4fe70a8e0005', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2021-09-02 04:52:53', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2021-09-02 04:52:53', '0', 'update_comany_id'),
('b5e6bdae-e15d-4fce-83f0-55b6adf864c3', 28, 'Awareness', '2015', '58511238-fba8-4db9-aad0-833fc20b8995', '7', '7.3', 'Click on edit button to update this section.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, 0, '56044726-2adc-4ab0-a1a1-03e4db1e6cf9', '5b1fb6ec-2488-445f-bf26-4fe70a8e0005', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2021-09-02 04:52:53', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2021-09-02 04:52:53', '0', 'update_comany_id'),
('597ea05c-3322-43ac-ad37-afc84d16b08b', 29, 'Communication', '2015', '58511238-fba8-4db9-aad0-833fc20b8995', '7', '7.4', 'Click on edit button to update this section.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, 0, '56044726-2adc-4ab0-a1a1-03e4db1e6cf9', '5b1fb6ec-2488-445f-bf26-4fe70a8e0005', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2021-09-02 04:52:53', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2021-09-02 04:52:53', '0', 'update_comany_id'),
('7b2b7ba9-4163-425f-88c2-c23c64a66e82', 30, 'Documented information', '2015', '58511238-fba8-4db9-aad0-833fc20b8995', '7', '7.5', 'Click on edit button to update this section.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, 0, '56044726-2adc-4ab0-a1a1-03e4db1e6cf9', '5b1fb6ec-2488-445f-bf26-4fe70a8e0005', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2021-09-02 04:52:53', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2021-09-02 04:52:53', '0', 'update_comany_id'),
('a97065be-58d8-44c2-bc1a-01307a810c66', 31, 'General', '2015', '58511238-fba8-4db9-aad0-833fc20b8995', '7', '7.5.1', 'Click on edit button to update this section.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, 0, '56044726-2adc-4ab0-a1a1-03e4db1e6cf9', '5b1fb6ec-2488-445f-bf26-4fe70a8e0005', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2021-09-02 04:52:53', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2021-09-02 04:52:53', '0', 'update_comany_id'),
('c713d46d-a0c8-4a89-9919-87e429a88945', 32, 'Creating and updating documented information', '2015', '58511238-fba8-4db9-aad0-833fc20b8995', '7', '7.5.2', 'Click on edit button to update this section.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, 0, '56044726-2adc-4ab0-a1a1-03e4db1e6cf9', '5b1fb6ec-2488-445f-bf26-4fe70a8e0005', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2021-09-02 04:52:53', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2021-09-02 04:52:53', '0', 'update_comany_id'),
('ca564977-1dbb-4df6-a558-7db23297b063', 33, 'Control of documented information', '2015', '58511238-fba8-4db9-aad0-833fc20b8995', '7', '7.5.3', 'Click on edit button to update this section.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, 0, '56044726-2adc-4ab0-a1a1-03e4db1e6cf9', '5b1fb6ec-2488-445f-bf26-4fe70a8e0005', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2021-09-02 04:52:53', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2021-09-02 04:52:53', '0', 'update_comany_id'),
('1717b03b-218a-4772-a7d8-c7f7a422bb09', 34, 'Operation', '2015', '58511238-fba8-4db9-aad0-833fc20b8995', '8', '', 'Click on edit button to update this section.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, 0, '56044726-2adc-4ab0-a1a1-03e4db1e6cf9', '5b1fb6ec-2488-445f-bf26-4fe70a8e0005', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2021-09-02 04:52:53', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2021-09-02 04:52:53', '0', 'update_comany_id'),
('ac91acb1-e3c8-4aa4-b789-ae6c0fbc663c', 35, 'Operational planning and control', '2015', '58511238-fba8-4db9-aad0-833fc20b8995', '8', '8.1', 'Click on edit button to update this section.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, 0, '56044726-2adc-4ab0-a1a1-03e4db1e6cf9', '5b1fb6ec-2488-445f-bf26-4fe70a8e0005', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2021-09-02 04:52:53', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2021-09-02 04:52:53', '0', 'update_comany_id'),
('f7ba6e3f-e5a5-4c8f-a767-1ec48ae2a31b', 36, 'Requirements for products and services', '2015', '58511238-fba8-4db9-aad0-833fc20b8995', '8', '8.2', 'Click on edit button to update this section.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, 0, '56044726-2adc-4ab0-a1a1-03e4db1e6cf9', '5b1fb6ec-2488-445f-bf26-4fe70a8e0005', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2021-09-02 04:52:53', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2021-09-02 04:52:53', '0', 'update_comany_id'),
('986a6cbc-17ea-46a2-afc4-4af9c5ee69cb', 37, 'Design and development of products and services', '2015', '58511238-fba8-4db9-aad0-833fc20b8995', '8', '8.3', 'Click on edit button to update this section.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, 0, '56044726-2adc-4ab0-a1a1-03e4db1e6cf9', '5b1fb6ec-2488-445f-bf26-4fe70a8e0005', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2021-09-02 04:52:53', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2021-09-02 04:52:53', '0', 'update_comany_id'),
('2169385d-e42e-4ede-98de-12b3ed4eb957', 38, 'Product and service provision', '2015', '58511238-fba8-4db9-aad0-833fc20b8995', '8', '8.5', 'Click on edit button to update this section.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, 0, '56044726-2adc-4ab0-a1a1-03e4db1e6cf9', '5b1fb6ec-2488-445f-bf26-4fe70a8e0005', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2021-09-02 04:52:53', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2021-09-02 04:52:53', '0', 'update_comany_id'),
('b715aae0-a3a4-4674-93db-7757f0c0b728', 39, 'Release of products and services', '2015', '58511238-fba8-4db9-aad0-833fc20b8995', '8', '8.6', 'Click on edit button to update this section.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, 0, '56044726-2adc-4ab0-a1a1-03e4db1e6cf9', '5b1fb6ec-2488-445f-bf26-4fe70a8e0005', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2021-09-02 04:52:53', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2021-09-02 04:52:53', '0', 'update_comany_id'),
('c4c1b0f3-5981-439f-b8ae-d62fef03f03c', 40, 'Control of nonconforming outputs', '2015', '58511238-fba8-4db9-aad0-833fc20b8995', '8', '8.7', 'Click on edit button to update this section.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, 0, '56044726-2adc-4ab0-a1a1-03e4db1e6cf9', '5b1fb6ec-2488-445f-bf26-4fe70a8e0005', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2021-09-02 04:52:53', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2021-09-02 04:52:53', '0', 'update_comany_id'),
('d6174ce2-2a50-4388-bb94-9ef2c8989d78', 41, 'Performance evaluation', '2015', '58511238-fba8-4db9-aad0-833fc20b8995', '9', '', 'Click on edit button to update this section.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, 0, '56044726-2adc-4ab0-a1a1-03e4db1e6cf9', '5b1fb6ec-2488-445f-bf26-4fe70a8e0005', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2021-09-02 04:52:53', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2021-09-02 04:52:53', '0', 'update_comany_id'),
('9dea3f9d-39af-4ff0-979c-75b48e111d4b', 42, 'Customer Satisfaction', '2015', '58511238-fba8-4db9-aad0-833fc20b8995', '9', '9.1.2', 'Click on edit button to update this section.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, 0, '56044726-2adc-4ab0-a1a1-03e4db1e6cf9', '5b1fb6ec-2488-445f-bf26-4fe70a8e0005', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2021-09-02 04:52:53', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2021-09-02 04:52:53', '0', 'update_comany_id'),
('12b19b1e-635c-4a2e-8556-93ab681b5c4e', 43, 'Internal Audit', '2015', '58511238-fba8-4db9-aad0-833fc20b8995', '9', '9.2', 'Click on edit button to update this section.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, 0, '56044726-2adc-4ab0-a1a1-03e4db1e6cf9', '5b1fb6ec-2488-445f-bf26-4fe70a8e0005', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2021-09-02 04:52:53', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2021-09-02 04:52:53', '0', 'update_comany_id'),
('f6b9a9f0-1005-4825-a37e-2bf147a54bee', 44, 'Management Review', '2015', '58511238-fba8-4db9-aad0-833fc20b8995', '9', '9.3', 'Click on edit button to update this section.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, 0, '56044726-2adc-4ab0-a1a1-03e4db1e6cf9', '5b1fb6ec-2488-445f-bf26-4fe70a8e0005', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2021-09-02 04:52:53', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2021-09-02 04:52:53', '0', 'update_comany_id'),
('01f0f02d-4da7-467a-b165-010f8aed86f0', 45, 'Improvement', '2015', '58511238-fba8-4db9-aad0-833fc20b8995', '10', '', 'Click on edit button to update this section.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, 0, '56044726-2adc-4ab0-a1a1-03e4db1e6cf9', '5b1fb6ec-2488-445f-bf26-4fe70a8e0005', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2021-09-02 04:52:53', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2021-09-02 04:52:53', '0', 'update_comany_id'),
('47672c9e-9453-4550-99e5-8a251b258ea3', 46, 'General', '2015', '58511238-fba8-4db9-aad0-833fc20b8995', '10', '10.1', 'Click on edit button to update this section.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, 0, '56044726-2adc-4ab0-a1a1-03e4db1e6cf9', '5b1fb6ec-2488-445f-bf26-4fe70a8e0005', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2021-09-02 04:52:53', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2021-09-02 04:52:53', '0', 'update_comany_id'),
('cfb499b2-1c86-41f2-9816-6c51905d88b5', 47, 'Nonconformity in ISO 9001', '2015', '58511238-fba8-4db9-aad0-833fc20b8995', '10', '10.2', 'Click on edit button to update this section.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, 0, '56044726-2adc-4ab0-a1a1-03e4db1e6cf9', '5b1fb6ec-2488-445f-bf26-4fe70a8e0005', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2021-09-02 04:52:53', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2021-09-02 04:52:53', '0', 'update_comany_id'),
('2210da76-0fe8-4e57-88db-bcf2dbe898a4', 48, 'What is Non-conformance?', '2015', '58511238-fba8-4db9-aad0-833fc20b8995', '10', '10.2', 'Click on edit button to update this section.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, 0, '56044726-2adc-4ab0-a1a1-03e4db1e6cf9', '5b1fb6ec-2488-445f-bf26-4fe70a8e0005', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2021-09-02 04:52:53', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2021-09-02 04:52:53', '0', 'update_comany_id'),
('ff1e1c9a-6135-4c6f-be59-1d5a3a270370', 49, 'Corrective Action', '2015', '58511238-fba8-4db9-aad0-833fc20b8995', '10', '10.2', 'Click on edit button to update this section.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, 0, '56044726-2adc-4ab0-a1a1-03e4db1e6cf9', '5b1fb6ec-2488-445f-bf26-4fe70a8e0005', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2021-09-02 04:52:53', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2021-09-02 04:52:53', '0', 'update_comany_id'),
('52e6ee36-582b-4c44-a883-0c181659ea44', 50, 'Continual Improvement', '2015', '58511238-fba8-4db9-aad0-833fc20b8995', '10', '10.3', 'Click on edit button to update this section.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, 0, '56044726-2adc-4ab0-a1a1-03e4db1e6cf9', '5b1fb6ec-2488-445f-bf26-4fe70a8e0005', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2021-09-02 04:52:53', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2021-09-02 04:52:53', '0', 'update_comany_id'),
('2f12892b-9264-48e7-848e-ef98ad0537ce', 3844, 'scope 2', 'abc', '712a4904-6cbe-4e5c-a616-5fd777a60037', '2', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, 1, '43bcb91e-d10b-4d9c-9d62-edc6e97b6261', 'da75b706-16d4-41f2-92f5-6f1a0b165057', 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', '2025-12-05 04:38:28', 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', NULL, NULL, '2026-01-11 19:43:27', '0', 'update_comany_id'),
('7e65ab67-a561-46b3-965b-946e3c3d1f67', 3845, 'Change Clause Title', 'abc', '712a4904-6cbe-4e5c-a616-5fd777a60037', '2', '2', '<p>Add clause details.&nbsp;</p>', '', '', '', '', '', '', '', NULL, 0, 0, NULL, 1, '43bcb91e-d10b-4d9c-9d62-edc6e97b6261', 'da75b706-16d4-41f2-92f5-6f1a0b165057', 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', '2025-12-05 04:38:28', 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', NULL, NULL, '2026-01-11 19:43:27', '0', 'update_comany_id'),
('d251419a-ef47-432a-9038-f511e12ab1b6', 3843, 'Scope', 'abc', '712a4904-6cbe-4e5c-a616-5fd777a60037', '1', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, 1, '43bcb91e-d10b-4d9c-9d62-edc6e97b6261', 'da75b706-16d4-41f2-92f5-6f1a0b165057', 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', '2025-12-05 04:38:28', 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', NULL, NULL, '2026-01-11 19:43:27', '0', 'update_comany_id');

-- --------------------------------------------------------

--
-- Table structure for table `companies`
--

CREATE TABLE `companies` (
  `id` varchar(36) NOT NULL,
  `sr_no` int(11) NOT NULL,
  `name` varchar(225) NOT NULL,
  `description` text NOT NULL,
  `logo` int(1) DEFAULT '0' COMMENT '0 = default, 1 = custom logo',
  `company_logo` varchar(225) DEFAULT NULL,
  `number_of_branches` int(1) NOT NULL,
  `allow_multiple_login` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0= Not allow, 1=Allow',
  `limit_login_attempt` tinyint(1) NOT NULL DEFAULT '1' COMMENT '0= No limit, 1= limit upto 3 attempt',
  `flinkiso_start_date` date NOT NULL,
  `flinkiso_end_date` date NOT NULL,
  `welcome_message` text,
  `quality_policy` text,
  `vision_statement` text,
  `mission_statement` text,
  `scope_of_qms` text,
  `schedule_id` varchar(36) DEFAULT NULL,
  `smtp_setup` tinyint(1) DEFAULT '0',
  `is_smtp` tinyint(1) DEFAULT '0',
  `liscence_key` varchar(36) DEFAULT NULL,
  `sample_data` tinyint(1) NOT NULL DEFAULT '0',
  `audit_plan` text,
  `activate_password_setting` int(1) NOT NULL DEFAULT '0',
  `two_way_authentication` tinyint(1) DEFAULT NULL,
  `dir_name` varchar(50) NOT NULL,
  `timezone` varchar(90) DEFAULT NULL,
  `version` float(11,2) DEFAULT NULL,
  `change_management_table` varchar(36) DEFAULT NULL,
  `change_management_table_fields` text,
  `publish` tinyint(1) DEFAULT '0' COMMENT '0=Un 1=Pub',
  `record_status` tinyint(1) DEFAULT '0' COMMENT '0=Un-locked, 1=Locked',
  `status_user_id` varchar(36) DEFAULT NULL,
  `soft_delete` tinyint(4) DEFAULT '0',
  `branchid` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `departmentid` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `created_by` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `created` datetime NOT NULL COMMENT 'system defined automatically add',
  `modified_by` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `approved_by` varchar(36) DEFAULT NULL,
  `prepared_by` varchar(36) DEFAULT NULL,
  `modified` datetime NOT NULL COMMENT 'system defined automatically add',
  `system_table_id` varchar(36) DEFAULT '0',
  `division_id` varchar(36) DEFAULT '0',
  `company_id` varchar(36) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` varchar(36) NOT NULL,
  `sr_no` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `contact` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `gstin` varchar(45) NOT NULL,
  `notes` text,
  `company_id` varchar(36) NOT NULL,
  `currency` varchar(5) NOT NULL DEFAULT 'USD',
  `data_cost_per_unit` float(11,2) NOT NULL,
  `db_cost_per_unit` float(11,2) NOT NULL,
  `discount` float(11,2) NOT NULL,
  `credit_days` int(2) NOT NULL DEFAULT '7',
  `retention_period` int(2) NOT NULL DEFAULT '45',
  `created_at` datetime NOT NULL,
  `publish` tinyint(1) DEFAULT '0' COMMENT '0=Un 1=Pub',
  `record_status` tinyint(1) DEFAULT '0' COMMENT '0=Un-locked, 1=Locked',
  `status_user_id` varchar(36) DEFAULT NULL,
  `created_by` varchar(36) NOT NULL,
  `created` datetime NOT NULL,
  `modified_by` varchar(36) NOT NULL,
  `approved_by` varchar(36) DEFAULT NULL,
  `prepared_by` varchar(36) DEFAULT NULL,
  `modified` datetime NOT NULL,
  `soft_delete` tinyint(1) NOT NULL DEFAULT '0',
  `branchid` varchar(36) DEFAULT NULL,
  `departmentid` varchar(36) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `custom_codes`
--

CREATE TABLE `custom_codes` (
  `id` varchar(36) NOT NULL,
  `sr_no` int(11) NOT NULL,
  `custom_table_id` varchar(36) NOT NULL,
  `name` varchar(120) NOT NULL,
  `css` text,
  `js` text,
  `custom_code` text,
  `publish` tinyint(1) DEFAULT '0' COMMENT '0=Un 1=Pub',
  `record_status` tinyint(1) DEFAULT '0' COMMENT '0=Un-locked, 1=Locked',
  `status_user_id` varchar(36) DEFAULT NULL,
  `created_by` varchar(36) NOT NULL,
  `created` datetime NOT NULL,
  `modified_by` varchar(36) NOT NULL,
  `approved_by` varchar(36) DEFAULT NULL,
  `prepared_by` varchar(36) DEFAULT NULL,
  `modified` datetime NOT NULL,
  `soft_delete` tinyint(1) NOT NULL DEFAULT '0',
  `branchid` varchar(36) DEFAULT NULL,
  `departmentid` varchar(36) DEFAULT NULL,
  `company_id` varchar(36) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `custom_files`
--

CREATE TABLE `custom_files` (
  `id` varchar(36) NOT NULL,
  `sr_no` int(11) NOT NULL,
  `model` varchar(255) NOT NULL,
  `controller` varchar(255) NOT NULL,
  `record` varchar(36) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_type` varchar(5) NOT NULL,
  `user_id` varchar(36) NOT NULL,
  `employee_id` varchar(36) NOT NULL,
  `action` int(1) DEFAULT '0' COMMENT '0=Download, 1=Delete',
  `record_status` tinyint(1) DEFAULT '0' COMMENT '0=Un-locked, 1=Locked',
  `status_user_id` varchar(36) DEFAULT NULL,
  `created_by` varchar(36) NOT NULL,
  `created` datetime NOT NULL,
  `modified_by` varchar(36) NOT NULL,
  `approved_by` varchar(36) DEFAULT NULL,
  `prepared_by` varchar(36) DEFAULT NULL,
  `modified` datetime NOT NULL,
  `soft_delete` tinyint(1) NOT NULL DEFAULT '0',
  `branchid` varchar(36) DEFAULT NULL,
  `departmentid` varchar(36) DEFAULT NULL,
  `company_id` varchar(36) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `custom_tables`
--

CREATE TABLE `custom_tables` (
  `id` varchar(36) NOT NULL,
  `sr_no` int(11) NOT NULL,
  `password` varchar(255) NOT NULL,
  `default_field` varchar(255) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
  `table_name` varchar(255) NOT NULL,
  `table_version` int(11) NOT NULL,
  `table_type` int(1) NOT NULL DEFAULT '0' COMMENT '0=doc;1=process',
  `file_key` varchar(50) DEFAULT NULL,
  `version_keys` text,
  `file_status` int(1) DEFAULT NULL,
  `last_saved` datetime DEFAULT NULL,
  `qc_document_id` varchar(36) DEFAULT NULL,
  `field_name` varchar(255) DEFAULT NULL,
  `field_value` int(1) DEFAULT NULL,
  `process_id` varchar(36) DEFAULT NULL,
  `custom_table_id` varchar(36) DEFAULT NULL,
  `display_field` int(1) NOT NULL DEFAULT '0',
  `fields` text,
  `belongs_to` text,
  `has_many` text,
  `child_tables_fields` text,
  `form_layout` int(1) NOT NULL DEFAULT '2' COMMENT '1=regular,2=table',
  `add_form_script` text,
  `edit_form_script` text,
  `branches` text,
  `departments` text,
  `designations` text,
  `users` text,
  `creators` text,
  `editors` text,
  `viewers` text,
  `approvers` text,
  `publish` tinyint(1) DEFAULT '1' COMMENT '0=Un 1=Pub',
  `table_locked` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0=locked 1=unlocked',
  `record_status` tinyint(1) DEFAULT '0' COMMENT '0=Un-locked, 1=Locked',
  `status_user_id` varchar(36) DEFAULT NULL,
  `created_by` varchar(36) NOT NULL,
  `created` datetime NOT NULL,
  `modified_by` varchar(36) NOT NULL,
  `approved_by` varchar(36) DEFAULT NULL,
  `prepared_by` varchar(36) DEFAULT NULL,
  `modified` datetime NOT NULL,
  `soft_delete` tinyint(1) NOT NULL DEFAULT '0',
  `branchid` varchar(36) DEFAULT NULL,
  `departmentid` varchar(36) DEFAULT NULL,
  `company_id` varchar(36) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `custom_tables`
--

INSERT INTO `custom_tables` (`id`, `sr_no`, `password`, `default_field`, `name`, `description`, `table_name`, `table_version`, `table_type`, `file_key`, `version_keys`, `file_status`, `last_saved`, `qc_document_id`, `field_name`, `field_value`, `process_id`, `custom_table_id`, `display_field`, `fields`, `belongs_to`, `has_many`, `child_tables_fields`, `form_layout`, `add_form_script`, `edit_form_script`, `branches`, `departments`, `designations`, `users`, `creators`, `editors`, `viewers`, `approvers`, `publish`, `table_locked`, `record_status`, `status_user_id`, `created_by`, `created`, `modified_by`, `approved_by`, `prepared_by`, `modified`, `soft_delete`, `branchid`, `departmentid`, `company_id`) VALUES
('0e7f279f-2a4d-414e-b103-a40f40983189', 1, '581c1bb5db15645d76a7e672f882ac71', '0', 'Audit Catetory', NULL, 'tbl_audit_catetory_v0s', 0, 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '[{\"dummy\":\"\",\"field_name\":\"name\",\"field_label\":\"Name\",\"old_field_name\":\"name\",\"linked_to\":\"-1\",\"display_type\":\"0\",\"field_type\":\"0\",\"length\":\"255\",\"size\":\"12\",\"data_type\":\"text\",\"mandatory\":\"1\",\"default_field\":\"1\",\"index_show\":\"1\",\"drop\":\"0\",\"new\":\"0\",\"sequence\":\"\",\"add_disabled\":\"0\",\"who_can_edit\":\"\\\"\\\"\",\"show_comments\":\"\"}]', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, NULL, 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', '2026-01-11 20:51:07', 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', NULL, NULL, '2026-01-11 20:51:07', 0, '43bcb91e-d10b-4d9c-9d62-edc6e97b6261', 'da75b706-16d4-41f2-92f5-6f1a0b165057', 'update_comany_id'),
('28eb2068-cf9d-4dce-8ea6-2347e14b3d60', 2, '581c1bb5db15645d76a7e672f882ac71', NULL, 'Audit Schedule', '', 'tbl_audit_schedule_0_v0s', 0, 0, '1260018022', NULL, NULL, NULL, 'c29a4cae-ee08-4dc8-9e30-ba6bce0005b4', NULL, NULL, '', '', 0, '[{\"dummy\":\"\",\"field_name\":\"audit_number\",\"field_label\":\"QXVkaXQgTnVtYmVy\",\"old_field_name\":\"audit_number\",\"linked_to\":\"-1\",\"display_type\":\"0\",\"field_type\":\"0\",\"length\":\"255\",\"size\":\"4\",\"data_type\":\"text\",\"mandatory\":\"1\",\"default_field\":\"1\",\"is_unique\":\"1\",\"index_show\":\"1\",\"drop\":\"0\",\"new\":\"0\",\"sequence\":\"\",\"show_last_value\":\"1\",\"add_disabled\":\"0\",\"edit_disabled\":\"0\",\"who_can_edit\":\"\",\"session_value\":\"\"},{\"dummy\":\"-1\",\"field_name\":\"standard\",\"field_label\":\"U3RhbmRhcmQ=\",\"old_field_name\":\"standard\",\"linked_to\":\"Standards\",\"display_type\":\"3\",\"field_type\":\"0\",\"length\":\"36\",\"size\":\"4\",\"data_type\":\"dropdown-s\",\"mandatory\":\"1\",\"index_show\":\"0\",\"drop\":\"0\",\"new\":\"0\",\"sequence\":\"\",\"showdocs\":\"\",\"showdocs_mode\":\"\",\"showdocs_copy\":\"\",\"add_disabled\":\"0\",\"edit_disabled\":\"0\",\"who_can_edit\":\"\",\"session_value\":\"\"},{\"dummy\":\"-1\",\"field_name\":\"audit_category\",\"field_label\":\"QXVkaXQgQ2F0ZWdvcnk=\",\"old_field_name\":\"audit_category\",\"linked_to\":\"TblAuditCatetoryV0\",\"display_type\":\"3\",\"field_type\":\"0\",\"length\":\"36\",\"size\":\"4\",\"data_type\":\"dropdown-s\",\"mandatory\":\"1\",\"index_show\":\"0\",\"drop\":\"0\",\"new\":\"0\",\"sequence\":\"\",\"showdocs\":\"0\",\"showdocs_mode\":\"\",\"showdocs_copy\":\"\",\"add_disabled\":\"0\",\"edit_disabled\":\"0\",\"who_can_edit\":\"\",\"session_value\":\"\"},{\"dummy\":\"\",\"field_name\":\"schedule_start_date\",\"field_label\":\"U2NoZWR1bGUgU3RhcnQgRGF0ZQ==\",\"old_field_name\":\"schedule_start_date\",\"linked_to\":\"-1\",\"display_type\":\"0\",\"field_type\":\"5\",\"length\":\"255\",\"size\":\"6\",\"data_type\":\"date\",\"mandatory\":\"1\",\"default_field\":\"0\",\"is_unique\":\"0\",\"index_show\":\"0\",\"drop\":\"0\",\"new\":\"0\",\"sequence\":\"\",\"add_disabled\":\"0\",\"edit_disabled\":\"0\",\"who_can_edit\":\"\",\"session_value\":\"\",\"default_date_number\":\"0\",\"default_date_type\":\"-1\",\"default_date_from\":\"-1\"},{\"dummy\":\"\",\"field_name\":\"scheduled_end_date\",\"field_label\":\"U2NoZWR1bGVkIEVuZCBEYXRl\",\"old_field_name\":\"scheduled_end_date\",\"linked_to\":\"-1\",\"display_type\":\"0\",\"field_type\":\"5\",\"length\":\"255\",\"size\":\"6\",\"data_type\":\"date\",\"mandatory\":\"1\",\"default_field\":\"0\",\"is_unique\":\"0\",\"index_show\":\"0\",\"drop\":\"0\",\"new\":\"0\",\"sequence\":\"\",\"add_disabled\":\"0\",\"edit_disabled\":\"0\",\"who_can_edit\":\"\",\"session_value\":\"\",\"default_date_number\":\"0\",\"default_date_type\":\"-1\",\"default_date_from\":\"schedule_start_date\"},{\"field_name\":\"audit_locations\",\"field_label\":\"QXVkaXQgTG9jYXRpb25z\",\"old_field_name\":\"audit_locations\",\"linked_to\":\"Branches\",\"display_type\":\"4\",\"field_type\":\"1\",\"length\":\"0\",\"size\":\"12\",\"data_type\":\"dropdown-m\",\"mandatory\":\"1\",\"index_show\":\"0\",\"drop\":\"0\",\"new\":\"0\",\"sequence\":\"\",\"add_disabled\":\"0\",\"edit_disabled\":\"0\",\"who_can_edit\":\"\",\"session_value\":\"\"},{\"field_name\":\"departments_to_be_audited\",\"field_label\":\"RGVwYXJ0bWVudHMgVG8gQmUgQXVkaXRlZA==\",\"old_field_name\":\"departments_to_be_audited\",\"linked_to\":\"Departments\",\"display_type\":\"4\",\"field_type\":\"1\",\"length\":\"0\",\"size\":\"12\",\"data_type\":\"dropdown-m\",\"mandatory\":\"1\",\"index_show\":\"0\",\"drop\":\"0\",\"new\":\"0\",\"sequence\":\"\",\"add_disabled\":\"0\",\"edit_disabled\":\"0\",\"who_can_edit\":\"\",\"session_value\":\"\"},{\"dummy\":\"0\",\"field_name\":\"current_status\",\"field_label\":\"Q3VycmVudCBTdGF0dXM=\",\"old_field_name\":\"current_status\",\"linked_to\":\"-1\",\"display_type\":\"1\",\"field_type\":\"2\",\"length\":\"1\",\"size\":\"12\",\"data_type\":\"radio\",\"csvoptions\":\"scheduled,on-going,completed,cancled\",\"mandatory\":\"1\",\"index_show\":\"1\",\"drop\":\"0\",\"new\":\"0\",\"sequence\":\"\",\"add_disabled\":\"0\",\"edit_disabled\":\"0\",\"who_can_edit\":\"\",\"session_value\":\"\"},{\"dummy\":\"\",\"field_name\":\"notes\",\"field_label\":\"Tm90ZXM=\",\"old_field_name\":\"notes\",\"linked_to\":\"-1\",\"display_type\":\"0\",\"field_type\":\"1\",\"length\":\"0\",\"size\":\"12\",\"data_type\":\"textarea\",\"mandatory\":\"1\",\"index_show\":\"0\",\"drop\":\"0\",\"new\":\"0\",\"sequence\":\"\",\"add_disabled\":\"0\",\"edit_disabled\":\"0\",\"who_can_edit\":\"\",\"session_value\":\"\"}]', '{\"standard\":\"Standards\",\"audit_category\":\"TblAuditCatetoryV0\",\"audit_locations\":\"Branches\",\"departments_to_be_audited\":\"Departments\"}', '', NULL, 2, NULL, NULL, NULL, NULL, NULL, NULL, '[\"e6c97faa-f602-4f37-ae2d-7c95c11e1817\",\"a3d53ee2-adcd-4433-9363-87aed5be038c\",\"c185cfd1-e92c-4fcf-8f5d-9365a18ab81a\",\"6e98f2d1-879d-43a2-96b5-1e153a39ece6\",\"5e665aa1-4a3c-4baa-b6cc-09bfe007f8b1\",\"b3bac56f-2beb-4e94-92b4-c06bef2914f9\"]', '[\"e6c97faa-f602-4f37-ae2d-7c95c11e1817\",\"a3d53ee2-adcd-4433-9363-87aed5be038c\",\"c185cfd1-e92c-4fcf-8f5d-9365a18ab81a\",\"6e98f2d1-879d-43a2-96b5-1e153a39ece6\",\"5e665aa1-4a3c-4baa-b6cc-09bfe007f8b1\",\"b3bac56f-2beb-4e94-92b4-c06bef2914f9\"]', '[\"e6c97faa-f602-4f37-ae2d-7c95c11e1817\",\"a3d53ee2-adcd-4433-9363-87aed5be038c\",\"c185cfd1-e92c-4fcf-8f5d-9365a18ab81a\",\"6e98f2d1-879d-43a2-96b5-1e153a39ece6\",\"5e665aa1-4a3c-4baa-b6cc-09bfe007f8b1\",\"b3bac56f-2beb-4e94-92b4-c06bef2914f9\"]', '[\"e6c97faa-f602-4f37-ae2d-7c95c11e1817\",\"a3d53ee2-adcd-4433-9363-87aed5be038c\",\"c185cfd1-e92c-4fcf-8f5d-9365a18ab81a\",\"6e98f2d1-879d-43a2-96b5-1e153a39ece6\",\"5e665aa1-4a3c-4baa-b6cc-09bfe007f8b1\",\"b3bac56f-2beb-4e94-92b4-c06bef2914f9\"]', 1, 0, 0, NULL, 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', '2026-01-11 20:51:41', 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', NULL, NULL, '2026-01-11 20:52:12', 0, '43bcb91e-d10b-4d9c-9d62-edc6e97b6261', 'da75b706-16d4-41f2-92f5-6f1a0b165057', 'update_comany_id'),
('b2636904-8dff-4356-a06a-4887341c8def', 3, '581c1bb5db15645d76a7e672f882ac71', NULL, 'Audit Checklist', '', 'tbl_audit_checklist_0_v0s', 0, 0, '3808101762', NULL, NULL, NULL, 'cdf45c35-4a25-4cc9-bec7-4692f40d27af', 'current_status', 0, '', '', 0, '[{\"dummy\":\"\",\"field_name\":\"checklist_title\",\"field_label\":\"Q2hlY2tsaXN0IFRpdGxl\",\"old_field_name\":\"checklist_title\",\"linked_to\":\"-1\",\"display_type\":\"0\",\"field_type\":\"0\",\"length\":\"255\",\"size\":\"12\",\"data_type\":\"text\",\"mandatory\":\"0\",\"default_field\":\"1\",\"is_unique\":\"0\",\"index_show\":\"1\",\"drop\":\"0\",\"new\":\"0\",\"sequence\":\"\",\"show_last_value\":\"0\",\"add_disabled\":\"0\",\"edit_disabled\":\"0\",\"who_can_edit\":\"\",\"session_value\":\"\"},{\"dummy\":\"\",\"field_name\":\"date_added\",\"field_label\":\"RGF0ZSBBZGRlZA==\",\"old_field_name\":\"date_added\",\"linked_to\":\"-1\",\"display_type\":\"0\",\"field_type\":\"5\",\"length\":\"255\",\"size\":\"6\",\"data_type\":\"date\",\"mandatory\":\"1\",\"default_field\":\"0\",\"is_unique\":\"0\",\"index_show\":\"1\",\"drop\":\"0\",\"new\":\"0\",\"sequence\":\"\",\"add_disabled\":\"0\",\"edit_disabled\":\"0\",\"who_can_edit\":\"\",\"session_value\":\"\",\"default_date_number\":\"0\",\"default_date_type\":\"-1\",\"default_date_from\":\"-1\"},{\"dummy\":\"-1\",\"field_name\":\"added_by\",\"field_label\":\"QWRkZWQgQnk=\",\"old_field_name\":\"added_by\",\"linked_to\":\"Employees\",\"display_type\":\"3\",\"field_type\":\"0\",\"length\":\"36\",\"size\":\"6\",\"data_type\":\"dropdown-s\",\"mandatory\":\"1\",\"index_show\":\"1\",\"drop\":\"0\",\"new\":\"0\",\"sequence\":\"\",\"add_signature\":\"1\",\"add_disabled\":\"0\",\"edit_disabled\":\"0\",\"who_can_edit\":\"\",\"session_value\":\"\"},{\"dummy\":\"\",\"field_name\":\"comments\",\"field_label\":\"Q29tbWVudHM=\",\"old_field_name\":\"comments\",\"linked_to\":\"-1\",\"display_type\":\"0\",\"field_type\":\"1\",\"length\":\"0\",\"size\":\"12\",\"data_type\":\"textarea\",\"mandatory\":\"1\",\"index_show\":\"1\",\"drop\":\"0\",\"new\":\"0\",\"sequence\":\"\",\"add_disabled\":\"0\",\"edit_disabled\":\"0\",\"who_can_edit\":\"\",\"session_value\":\"\"}]', '{\"added_by\":\"Employees\"}', '', NULL, 2, NULL, NULL, NULL, NULL, NULL, NULL, '[\"e6c97faa-f602-4f37-ae2d-7c95c11e1817\",\"a3d53ee2-adcd-4433-9363-87aed5be038c\",\"c185cfd1-e92c-4fcf-8f5d-9365a18ab81a\",\"6e98f2d1-879d-43a2-96b5-1e153a39ece6\",\"5e665aa1-4a3c-4baa-b6cc-09bfe007f8b1\",\"b3bac56f-2beb-4e94-92b4-c06bef2914f9\"]', '[\"e6c97faa-f602-4f37-ae2d-7c95c11e1817\",\"a3d53ee2-adcd-4433-9363-87aed5be038c\",\"c185cfd1-e92c-4fcf-8f5d-9365a18ab81a\",\"6e98f2d1-879d-43a2-96b5-1e153a39ece6\",\"5e665aa1-4a3c-4baa-b6cc-09bfe007f8b1\",\"b3bac56f-2beb-4e94-92b4-c06bef2914f9\"]', '[\"e6c97faa-f602-4f37-ae2d-7c95c11e1817\",\"a3d53ee2-adcd-4433-9363-87aed5be038c\",\"c185cfd1-e92c-4fcf-8f5d-9365a18ab81a\",\"6e98f2d1-879d-43a2-96b5-1e153a39ece6\",\"5e665aa1-4a3c-4baa-b6cc-09bfe007f8b1\",\"b3bac56f-2beb-4e94-92b4-c06bef2914f9\"]', '[\"e6c97faa-f602-4f37-ae2d-7c95c11e1817\",\"a3d53ee2-adcd-4433-9363-87aed5be038c\",\"c185cfd1-e92c-4fcf-8f5d-9365a18ab81a\",\"6e98f2d1-879d-43a2-96b5-1e153a39ece6\",\"5e665aa1-4a3c-4baa-b6cc-09bfe007f8b1\",\"b3bac56f-2beb-4e94-92b4-c06bef2914f9\"]', 1, 0, 0, NULL, 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', '2026-01-11 20:57:58', 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', NULL, NULL, '2026-01-11 21:01:57', 0, '43bcb91e-d10b-4d9c-9d62-edc6e97b6261', 'da75b706-16d4-41f2-92f5-6f1a0b165057', 'update_comany_id'),
('83c439cf-e6ab-42de-b1c8-a941a4ba18f7', 4, '581c1bb5db15645d76a7e672f882ac71', NULL, 'Audit Findings', '', 'tbl_audit_findings_0_v0s', 0, 0, '2575069392', NULL, NULL, NULL, '5abc3b5b-d7e5-4252-8fe2-d21200c26556', 'current_status', 1, '', '', 0, '[{\"dummy\":\"\",\"field_name\":\"finding_number\",\"field_label\":\"RmluZGluZyBOdW1iZXI=\",\"old_field_name\":\"finding_number\",\"linked_to\":\"-1\",\"display_type\":\"0\",\"field_type\":\"0\",\"length\":\"255\",\"size\":\"3\",\"data_type\":\"text\",\"mandatory\":\"1\",\"default_field\":\"1\",\"is_unique\":\"0\",\"index_show\":\"1\",\"drop\":\"0\",\"new\":\"0\",\"sequence\":\"\",\"show_last_value\":\"0\",\"add_disabled\":\"0\",\"edit_disabled\":\"0\",\"who_can_edit\":\"\",\"session_value\":\"\"},{\"dummy\":\"\",\"field_name\":\"audit_start_date\",\"field_label\":\"QXVkaXQgU3RhcnQgRGF0ZQ==\",\"old_field_name\":\"audit_start_date\",\"linked_to\":\"-1\",\"display_type\":\"0\",\"field_type\":\"6\",\"length\":\"255\",\"size\":\"4\",\"data_type\":\"date\",\"mandatory\":\"1\",\"default_field\":\"0\",\"is_unique\":\"0\",\"index_show\":\"0\",\"drop\":\"0\",\"new\":\"0\",\"sequence\":\"\",\"add_disabled\":\"0\",\"edit_disabled\":\"0\",\"who_can_edit\":\"\",\"session_value\":\"\",\"default_date_number\":\"0\",\"default_date_type\":\"-1\",\"default_date_from\":\"-1\"},{\"dummy\":\"\",\"field_name\":\"audit_end_date\",\"field_label\":\"QXVkaXQgRW5kIERhdGU=\",\"old_field_name\":\"audit_end_date\",\"linked_to\":\"-1\",\"display_type\":\"0\",\"field_type\":\"5\",\"length\":\"255\",\"size\":\"5\",\"data_type\":\"date\",\"mandatory\":\"1\",\"default_field\":\"0\",\"is_unique\":\"0\",\"index_show\":\"0\",\"drop\":\"0\",\"new\":\"0\",\"sequence\":\"\",\"add_disabled\":\"0\",\"edit_disabled\":\"0\",\"who_can_edit\":\"\",\"session_value\":\"\",\"default_date_number\":\"0\",\"default_date_type\":\"-1\",\"default_date_from\":\"-1\"},{\"dummy\":\"-1\",\"field_name\":\"auditor\",\"field_label\":\"QXVkaXRvcg==\",\"old_field_name\":\"auditor\",\"linked_to\":\"Employees\",\"display_type\":\"3\",\"field_type\":\"0\",\"length\":\"36\",\"size\":\"6\",\"data_type\":\"dropdown-s\",\"mandatory\":\"1\",\"index_show\":\"1\",\"drop\":\"0\",\"new\":\"0\",\"sequence\":\"\",\"add_signature\":\"1\",\"add_disabled\":\"0\",\"edit_disabled\":\"0\",\"who_can_edit\":\"\",\"session_value\":\"\"},{\"dummy\":\"-1\",\"field_name\":\"auditee\",\"field_label\":\"QXVkaXRlZQ==\",\"old_field_name\":\"auditee\",\"linked_to\":\"Employees\",\"display_type\":\"3\",\"field_type\":\"0\",\"length\":\"36\",\"size\":\"6\",\"data_type\":\"dropdown-s\",\"mandatory\":\"1\",\"index_show\":\"1\",\"drop\":\"0\",\"new\":\"0\",\"sequence\":\"\",\"add_signature\":\"1\",\"add_disabled\":\"0\",\"edit_disabled\":\"0\",\"who_can_edit\":\"\",\"session_value\":\"\"},{\"dummy\":\"0\",\"field_name\":\"finding_type\",\"field_label\":\"RmluZGluZyBUeXBl\",\"old_field_name\":\"finding_type\",\"linked_to\":\"-1\",\"display_type\":\"1\",\"field_type\":\"2\",\"length\":\"1\",\"size\":\"6\",\"data_type\":\"radio\",\"csvoptions\":\"Observation, Non-conformity\",\"mandatory\":\"1\",\"index_show\":\"0\",\"drop\":\"0\",\"new\":\"0\",\"sequence\":\"\",\"add_disabled\":\"0\",\"edit_disabled\":\"0\",\"who_can_edit\":\"\",\"session_value\":\"\"},{\"dummy\":\"0\",\"field_name\":\"current_status\",\"field_label\":\"Q3VycmVudCBTdGF0dXM=\",\"old_field_name\":\"current_status\",\"linked_to\":\"-1\",\"display_type\":\"1\",\"field_type\":\"2\",\"length\":\"1\",\"size\":\"6\",\"data_type\":\"radio\",\"csvoptions\":\"Open,Closed\",\"mandatory\":\"1\",\"index_show\":\"0\",\"drop\":\"0\",\"new\":\"0\",\"sequence\":\"\",\"add_disabled\":\"0\",\"edit_disabled\":\"0\",\"who_can_edit\":\"\",\"session_value\":\"\"},{\"dummy\":\"\",\"field_name\":\"findings\",\"field_label\":\"RmluZGluZ3M=\",\"old_field_name\":\"findings\",\"linked_to\":\"-1\",\"display_type\":\"0\",\"field_type\":\"1\",\"length\":\"0\",\"size\":\"12\",\"data_type\":\"textarea\",\"mandatory\":\"1\",\"index_show\":\"0\",\"drop\":\"0\",\"new\":\"0\",\"sequence\":\"\",\"add_disabled\":\"0\",\"edit_disabled\":\"0\",\"who_can_edit\":\"[\\\"auditor\\\"]\",\"session_value\":\"\"},{\"dummy\":\"\",\"field_name\":\"response_from_auditee\",\"field_label\":\"UmVzcG9uc2UgRnJvbSBBdWRpdGVl\",\"old_field_name\":\"response_from_auditee\",\"linked_to\":\"-1\",\"display_type\":\"0\",\"field_type\":\"1\",\"length\":\"0\",\"size\":\"12\",\"data_type\":\"textarea\",\"mandatory\":\"0\",\"index_show\":\"0\",\"drop\":\"0\",\"new\":\"0\",\"sequence\":\"\",\"add_disabled\":\"1\",\"edit_disabled\":\"0\",\"who_can_edit\":\"\",\"session_value\":\"\"}]', '{\"auditor\":\"Employees\",\"auditee\":\"Employees\"}', '', NULL, 2, NULL, NULL, NULL, NULL, NULL, NULL, '[\"e6c97faa-f602-4f37-ae2d-7c95c11e1817\",\"a3d53ee2-adcd-4433-9363-87aed5be038c\",\"c185cfd1-e92c-4fcf-8f5d-9365a18ab81a\",\"6e98f2d1-879d-43a2-96b5-1e153a39ece6\",\"5e665aa1-4a3c-4baa-b6cc-09bfe007f8b1\",\"b3bac56f-2beb-4e94-92b4-c06bef2914f9\"]', '[\"e6c97faa-f602-4f37-ae2d-7c95c11e1817\",\"a3d53ee2-adcd-4433-9363-87aed5be038c\",\"c185cfd1-e92c-4fcf-8f5d-9365a18ab81a\",\"6e98f2d1-879d-43a2-96b5-1e153a39ece6\",\"5e665aa1-4a3c-4baa-b6cc-09bfe007f8b1\",\"b3bac56f-2beb-4e94-92b4-c06bef2914f9\"]', '[\"e6c97faa-f602-4f37-ae2d-7c95c11e1817\",\"a3d53ee2-adcd-4433-9363-87aed5be038c\",\"c185cfd1-e92c-4fcf-8f5d-9365a18ab81a\",\"6e98f2d1-879d-43a2-96b5-1e153a39ece6\",\"5e665aa1-4a3c-4baa-b6cc-09bfe007f8b1\",\"b3bac56f-2beb-4e94-92b4-c06bef2914f9\"]', '[\"e6c97faa-f602-4f37-ae2d-7c95c11e1817\",\"a3d53ee2-adcd-4433-9363-87aed5be038c\",\"c185cfd1-e92c-4fcf-8f5d-9365a18ab81a\",\"6e98f2d1-879d-43a2-96b5-1e153a39ece6\",\"5e665aa1-4a3c-4baa-b6cc-09bfe007f8b1\",\"b3bac56f-2beb-4e94-92b4-c06bef2914f9\"]', 1, 1, 0, NULL, 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', '2026-01-11 20:59:30', 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', NULL, NULL, '2026-01-11 21:01:03', 0, '43bcb91e-d10b-4d9c-9d62-edc6e97b6261', 'da75b706-16d4-41f2-92f5-6f1a0b165057', 'update_comany_id'),
('d9385db9-54d3-4569-b625-7507181d00d5', 5, '581c1bb5db15645d76a7e672f882ac71', NULL, 'MRM', '', 'tbl_mrm_0_v0s', 0, 0, '441572600', NULL, NULL, NULL, 'ea17ca9b-c5ec-4f0d-ad7c-14f534b5f32d', NULL, NULL, '', '', 0, '[{\"dummy\":\"\",\"field_name\":\"meeting_number\",\"field_label\":\"TWVldGluZyBOdW1iZXI=\",\"old_field_name\":\"meeting_number\",\"linked_to\":\"-1\",\"display_type\":\"0\",\"field_type\":\"0\",\"length\":\"255\",\"size\":\"4\",\"data_type\":\"text\",\"mandatory\":\"1\",\"default_field\":\"1\",\"is_unique\":\"0\",\"index_show\":\"1\",\"drop\":\"0\",\"new\":\"0\",\"sequence\":\"0\",\"show_last_value\":\"0\",\"add_disabled\":\"0\",\"edit_disabled\":\"0\",\"who_can_edit\":\"\",\"session_value\":\"\"},{\"dummy\":\"\",\"field_name\":\"scheduled_date_time\",\"field_label\":\"U2NoZWR1bGVkIERhdGUgVGltZQ==\",\"old_field_name\":\"scheduled_date_time\",\"linked_to\":\"-1\",\"display_type\":\"0\",\"field_type\":\"6\",\"length\":\"255\",\"size\":\"4\",\"data_type\":\"datetime\",\"mandatory\":\"1\",\"index_show\":\"0\",\"drop\":\"0\",\"new\":\"0\",\"sequence\":\"1\",\"add_disabled\":\"0\",\"edit_disabled\":\"0\",\"who_can_edit\":\"\",\"session_value\":\"\",\"default_date_number\":\"0\",\"default_date_type\":\"-1\",\"default_date_from\":\"-1\"},{\"dummy\":\"-1\",\"field_name\":\"proposed_by\",\"field_label\":\"UHJvcG9zZWQgQnk=\",\"old_field_name\":\"proposed_by\",\"linked_to\":\"Employees\",\"display_type\":\"3\",\"field_type\":\"0\",\"length\":\"36\",\"size\":\"4\",\"data_type\":\"dropdown-s\",\"mandatory\":\"1\",\"index_show\":\"1\",\"drop\":\"0\",\"new\":\"0\",\"sequence\":\"2\",\"add_signature\":\"0\",\"add_disabled\":\"0\",\"edit_disabled\":\"0\",\"who_can_edit\":\"\",\"session_value\":\"\"},{\"dummy\":\"\",\"field_name\":\"meeting_details\",\"field_label\":\"TWVldGluZyBEZXRhaWxz\",\"old_field_name\":\"meeting_details\",\"linked_to\":\"-1\",\"display_type\":\"0\",\"field_type\":\"1\",\"length\":\"0\",\"size\":\"12\",\"data_type\":\"textarea\",\"mandatory\":\"1\",\"index_show\":\"0\",\"drop\":\"0\",\"new\":\"0\",\"sequence\":\"3\",\"add_disabled\":\"0\",\"edit_disabled\":\"0\",\"who_can_edit\":\"\",\"session_value\":\"\"},{\"field_name\":\"invitees\",\"field_label\":\"SW52aXRlZXM=\",\"old_field_name\":\"invitees\",\"linked_to\":\"Employees\",\"display_type\":\"4\",\"field_type\":\"1\",\"length\":\"0\",\"size\":\"12\",\"data_type\":\"dropdown-m\",\"mandatory\":\"1\",\"index_show\":\"0\",\"drop\":\"0\",\"new\":\"0\",\"sequence\":\"4\",\"add_signature\":\"0\",\"add_disabled\":\"0\",\"edit_disabled\":\"0\",\"who_can_edit\":\"\",\"session_value\":\"\"},{\"dummy\":\"0\",\"field_name\":\"meeting_status\",\"field_label\":\"TWVldGluZyBTdGF0dXM=\",\"old_field_name\":\"meeting_status\",\"linked_to\":\"-1\",\"display_type\":\"1\",\"field_type\":\"2\",\"length\":\"1\",\"size\":\"12\",\"data_type\":\"radio\",\"csvoptions\":\"Scheduled,Conducted,Cancled\",\"mandatory\":\"1\",\"index_show\":\"1\",\"drop\":\"0\",\"new\":\"0\",\"sequence\":\"6\",\"add_disabled\":\"0\",\"edit_disabled\":\"0\",\"who_can_edit\":\"\",\"session_value\":\"\"},{\"field_name\":\"comments5\",\"show_comments\":\"RGF0YSB0byBiZSBhZGRlZCBhZnRlciBtZWV0aW5n\",\"size\":\"12\",\"display_type\":\"7\",\"field_type\":\"0\",\"data_type\":\"comments\",\"index_show\":\"0\",\"sequence\":\"7\",\"linked_to\":\"-1\",\"dummy\":\"0\",\"drop\":\"0\",\"old_field_name\":\"0\",\"add_disabled\":\"0\",\"edit_disabled\":\"0\",\"who_can_edit\":\"\",\"session_value\":\"\"},{\"dummy\":\"\",\"field_name\":\"actual_meeting_date_time\",\"field_label\":\"QWN0dWFsIE1lZXRpbmcgRGF0ZSBUaW1l\",\"old_field_name\":\"actual_meeting_date_time\",\"linked_to\":\"-1\",\"display_type\":\"0\",\"field_type\":\"6\",\"length\":\"255\",\"size\":\"5\",\"data_type\":\"datetime\",\"mandatory\":\"0\",\"index_show\":\"0\",\"drop\":\"0\",\"new\":\"0\",\"sequence\":\"8\",\"add_disabled\":\"1\",\"edit_disabled\":\"0\",\"who_can_edit\":\"\",\"session_value\":\"\",\"default_date_number\":\"0\",\"default_date_type\":\"-1\",\"default_date_from\":\"-1\"},{\"field_name\":\"attainted_by\",\"field_label\":\"QXR0YWludGVkIEJ5\",\"old_field_name\":\"attainted_by\",\"linked_to\":\"Employees\",\"display_type\":\"4\",\"field_type\":\"1\",\"length\":\"0\",\"size\":\"7\",\"data_type\":\"dropdown-m\",\"mandatory\":\"0\",\"index_show\":\"0\",\"drop\":\"0\",\"new\":\"0\",\"sequence\":\"9\",\"add_signature\":\"0\",\"add_disabled\":\"1\",\"edit_disabled\":\"0\",\"who_can_edit\":\"\",\"session_value\":\"\"}]', '{\"proposed_by\":\"Employees\",\"invitees\":\"Employees\",\"attainted_by\":\"Employees\"}', '', NULL, 2, NULL, NULL, NULL, NULL, NULL, NULL, '[\"e6c97faa-f602-4f37-ae2d-7c95c11e1817\",\"a3d53ee2-adcd-4433-9363-87aed5be038c\",\"c185cfd1-e92c-4fcf-8f5d-9365a18ab81a\",\"6e98f2d1-879d-43a2-96b5-1e153a39ece6\",\"5e665aa1-4a3c-4baa-b6cc-09bfe007f8b1\",\"b3bac56f-2beb-4e94-92b4-c06bef2914f9\"]', '[\"e6c97faa-f602-4f37-ae2d-7c95c11e1817\",\"a3d53ee2-adcd-4433-9363-87aed5be038c\",\"c185cfd1-e92c-4fcf-8f5d-9365a18ab81a\",\"6e98f2d1-879d-43a2-96b5-1e153a39ece6\",\"5e665aa1-4a3c-4baa-b6cc-09bfe007f8b1\",\"b3bac56f-2beb-4e94-92b4-c06bef2914f9\"]', '[\"e6c97faa-f602-4f37-ae2d-7c95c11e1817\",\"a3d53ee2-adcd-4433-9363-87aed5be038c\",\"c185cfd1-e92c-4fcf-8f5d-9365a18ab81a\",\"6e98f2d1-879d-43a2-96b5-1e153a39ece6\",\"5e665aa1-4a3c-4baa-b6cc-09bfe007f8b1\",\"b3bac56f-2beb-4e94-92b4-c06bef2914f9\"]', '[\"e6c97faa-f602-4f37-ae2d-7c95c11e1817\",\"a3d53ee2-adcd-4433-9363-87aed5be038c\",\"c185cfd1-e92c-4fcf-8f5d-9365a18ab81a\",\"6e98f2d1-879d-43a2-96b5-1e153a39ece6\",\"5e665aa1-4a3c-4baa-b6cc-09bfe007f8b1\",\"b3bac56f-2beb-4e94-92b4-c06bef2914f9\"]', 1, 0, 0, NULL, 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', '2026-01-11 21:03:39', 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', NULL, NULL, '2026-01-11 21:19:03', 0, '43bcb91e-d10b-4d9c-9d62-edc6e97b6261', 'da75b706-16d4-41f2-92f5-6f1a0b165057', 'update_comany_id'),
('06ab4495-44dc-48b3-86da-61f67f492cb7', 6, '581c1bb5db15645d76a7e672f882ac71', NULL, 'Customer Details', '', 'tbl_customer_details_0_v0s', 0, 0, '1668503338', NULL, NULL, NULL, '06d28e70-8ed6-424d-8a8c-deb73f5f5510', NULL, NULL, NULL, NULL, 0, '[{\"dummy\":\"\",\"field_name\":\"customer_name\",\"field_label\":\"Q3VzdG9tZXIgTmFtZQ==\",\"old_field_name\":\"customer_name\",\"linked_to\":\"-1\",\"display_type\":\"0\",\"field_type\":\"0\",\"length\":\"255\",\"size\":\"12\",\"data_type\":\"text\",\"default_field\":\"1\",\"mandatory\":\"1\",\"index_show\":\"1\",\"drop\":\"0\",\"new\":\"1\",\"sequence\":\"\",\"add_disabled\":\"0\",\"who_can_edit\":\"\\\"\\\"\"},{\"dummy\":\"\",\"field_name\":\"customer_details\",\"field_label\":\"Q3VzdG9tZXIgRGV0YWlscw==\",\"old_field_name\":\"customer_details\",\"linked_to\":\"-1\",\"display_type\":\"0\",\"field_type\":\"1\",\"length\":\"0\",\"size\":\"12\",\"data_type\":\"textarea\",\"mandatory\":\"1\",\"index_show\":\"0\",\"drop\":\"0\",\"new\":\"1\",\"sequence\":\"\",\"add_disabled\":\"0\",\"who_can_edit\":\"\\\"\\\"\"},{\"dummy\":\"\",\"field_name\":\"official_email\",\"field_label\":\"T2ZmaWNpYWwgRW1haWw=\",\"old_field_name\":\"official_email\",\"linked_to\":\"-1\",\"display_type\":\"0\",\"field_type\":\"0\",\"length\":\"255\",\"size\":\"4\",\"data_type\":\"email\",\"mandatory\":\"1\",\"index_show\":\"0\",\"drop\":\"0\",\"new\":\"1\",\"sequence\":\"\",\"add_disabled\":\"0\",\"who_can_edit\":\"\\\"\\\"\"},{\"dummy\":\"\",\"field_name\":\"phone\",\"field_label\":\"UGhvbmU=\",\"old_field_name\":\"phone\",\"linked_to\":\"-1\",\"display_type\":\"0\",\"field_type\":\"0\",\"length\":\"55\",\"size\":\"4\",\"data_type\":\"phone\",\"mandatory\":\"1\",\"index_show\":\"0\",\"drop\":\"0\",\"new\":\"1\",\"sequence\":\"\",\"add_disabled\":\"0\",\"who_can_edit\":\"\\\"\\\"\"},{\"dummy\":\"\",\"field_name\":\"fax\",\"field_label\":\"RmF4\",\"old_field_name\":\"fax\",\"linked_to\":\"-1\",\"display_type\":\"0\",\"field_type\":\"0\",\"length\":\"55\",\"size\":\"4\",\"data_type\":\"phone\",\"mandatory\":\"0\",\"index_show\":\"0\",\"drop\":\"0\",\"new\":\"1\",\"sequence\":\"\",\"add_disabled\":\"0\",\"who_can_edit\":\"\\\"\\\"\"},{\"dummy\":\"\",\"field_name\":\"head_office_address\",\"field_label\":\"SGVhZCBPZmZpY2UgQWRkcmVzcw==\",\"old_field_name\":\"head_office_address\",\"linked_to\":\"-1\",\"display_type\":\"0\",\"field_type\":\"1\",\"length\":\"0\",\"size\":\"12\",\"data_type\":\"textarea\",\"mandatory\":\"1\",\"index_show\":\"0\",\"drop\":\"0\",\"new\":\"1\",\"sequence\":\"\",\"add_disabled\":\"0\",\"who_can_edit\":\"\\\"\\\"\"},{\"dummy\":\"0\",\"field_name\":\"customer_status\",\"field_label\":\"Q3VzdG9tZXIgU3RhdHVz\",\"old_field_name\":\"customer_status\",\"linked_to\":\"-1\",\"display_type\":\"1\",\"field_type\":\"2\",\"length\":\"1\",\"size\":\"6\",\"data_type\":\"radio\",\"csvoptions\":\"Active,Inactive\",\"mandatory\":\"1\",\"index_show\":\"0\",\"drop\":\"0\",\"new\":\"1\",\"sequence\":\"\",\"add_disabled\":\"0\",\"who_can_edit\":\"\\\"\\\"\"},{\"dummy\":\"0\",\"field_name\":\"customer_type\",\"field_label\":\"Q3VzdG9tZXIgVHlwZQ==\",\"old_field_name\":\"customer_type\",\"linked_to\":\"-1\",\"display_type\":\"1\",\"field_type\":\"2\",\"length\":\"1\",\"size\":\"6\",\"data_type\":\"radio\",\"csvoptions\":\"Lead,New,Existing\",\"mandatory\":\"1\",\"index_show\":\"0\",\"drop\":\"0\",\"new\":\"1\",\"sequence\":\"\",\"add_disabled\":\"0\",\"who_can_edit\":\"\\\"\\\"\"}]', NULL, NULL, NULL, 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, NULL, 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', '2026-01-11 21:04:19', 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', NULL, NULL, '2026-01-11 21:04:19', 0, '43bcb91e-d10b-4d9c-9d62-edc6e97b6261', 'da75b706-16d4-41f2-92f5-6f1a0b165057', 'update_comany_id'),
('93b76a14-a8be-43b2-8fd0-eda477388006', 7, '581c1bb5db15645d76a7e672f882ac71', NULL, 'Customer Complaints', '', 'tbl_customer_complaints_0_v0s', 0, 0, '1402808120', NULL, NULL, NULL, '888fb842-bc2d-41c4-b17e-7026dd7643a5', NULL, NULL, NULL, NULL, 0, '[{\"dummy\":\"-1\",\"field_name\":\"customer\",\"field_label\":\"Q3VzdG9tZXI=\",\"old_field_name\":\"customer\",\"linked_to\":\"TblCustomerDetails0V0s\",\"display_type\":\"3\",\"field_type\":\"0\",\"length\":\"36\",\"size\":\"12\",\"data_type\":\"dropdown-s\",\"mandatory\":\"1\",\"index_show\":\"1\",\"drop\":\"0\",\"new\":\"1\",\"sequence\":\"0\",\"add_disabled\":\"0\",\"who_can_edit\":\"\\\"\\\"\"},{\"dummy\":\"\",\"field_name\":\"complaint_details\",\"field_label\":\"Q29tcGxhaW50IERldGFpbHM=\",\"old_field_name\":\"complaint_details\",\"linked_to\":\"-1\",\"display_type\":\"0\",\"field_type\":\"1\",\"length\":\"0\",\"size\":\"12\",\"data_type\":\"textarea\",\"mandatory\":\"1\",\"index_show\":\"0\",\"drop\":\"0\",\"new\":\"1\",\"sequence\":\"6\",\"add_disabled\":\"0\",\"who_can_edit\":\"\\\"\\\"\"},{\"dummy\":\"\",\"field_name\":\"date_received\",\"field_label\":\"RGF0ZSBSZWNlaXZlZA==\",\"old_field_name\":\"date_received\",\"linked_to\":\"-1\",\"display_type\":\"0\",\"field_type\":\"5\",\"length\":\"255\",\"size\":\"4\",\"data_type\":\"date\",\"mandatory\":\"1\",\"index_show\":\"1\",\"drop\":\"0\",\"new\":\"1\",\"sequence\":\"7\",\"add_disabled\":\"0\",\"who_can_edit\":\"\\\"\\\"\",\"default_date_number\":\"0\",\"default_date_type\":\"-1\",\"default_date_from\":\"-1\"},{\"dummy\":\"\",\"field_name\":\"target_date\",\"field_label\":\"VGFyZ2V0IERhdGU=\",\"old_field_name\":\"target_date\",\"linked_to\":\"-1\",\"display_type\":\"0\",\"field_type\":\"5\",\"length\":\"255\",\"size\":\"4\",\"data_type\":\"date\",\"mandatory\":\"1\",\"index_show\":\"1\",\"drop\":\"0\",\"new\":\"1\",\"sequence\":\"8\",\"add_disabled\":\"0\",\"who_can_edit\":\"\\\"\\\"\",\"default_date_number\":\"0\",\"default_date_type\":\"-1\",\"default_date_from\":\"date_received\"},{\"dummy\":\"\",\"field_name\":\"closure_date\",\"field_label\":\"Q2xvc3VyZSBEYXRl\",\"old_field_name\":\"closure_date\",\"linked_to\":\"-1\",\"display_type\":\"0\",\"field_type\":\"5\",\"length\":\"255\",\"size\":\"4\",\"data_type\":\"date\",\"mandatory\":\"0\",\"index_show\":\"1\",\"drop\":\"0\",\"new\":\"1\",\"sequence\":\"9\",\"add_disabled\":\"1\",\"who_can_edit\":\"\\\"\\\"\",\"default_date_number\":\"0\",\"default_date_type\":\"-1\",\"default_date_from\":\"-1\"},{\"dummy\":\"-1\",\"field_name\":\"assigned_to\",\"field_label\":\"QXNzaWduZWQgVG8=\",\"old_field_name\":\"assigned_to\",\"linked_to\":\"Employees\",\"display_type\":\"3\",\"field_type\":\"0\",\"length\":\"36\",\"size\":\"6\",\"data_type\":\"dropdown-s\",\"mandatory\":\"1\",\"index_show\":\"1\",\"drop\":\"0\",\"new\":\"1\",\"sequence\":\"10\",\"add_disabled\":\"0\",\"who_can_edit\":\"\\\"\\\"\"},{\"dummy\":\"0\",\"field_name\":\"current_status\",\"field_label\":\"Q3VycmVudCBTdGF0dXM=\",\"old_field_name\":\"current_status\",\"linked_to\":\"-1\",\"display_type\":\"1\",\"field_type\":\"2\",\"length\":\"1\",\"size\":\"6\",\"data_type\":\"radio\",\"csvoptions\":\"Open,Closed\",\"mandatory\":\"1\",\"index_show\":\"1\",\"drop\":\"0\",\"new\":\"1\",\"sequence\":\"11\",\"add_disabled\":\"0\",\"who_can_edit\":\"\\\"\\\"\"},{\"dummy\":\"\",\"field_name\":\"resolution_details\",\"field_label\":\"UmVzb2x1dGlvbiBEZXRhaWxz\",\"old_field_name\":\"resolution_details\",\"linked_to\":\"-1\",\"display_type\":\"0\",\"field_type\":\"1\",\"length\":\"0\",\"size\":\"12\",\"data_type\":\"textarea\",\"mandatory\":\"0\",\"index_show\":\"0\",\"drop\":\"0\",\"new\":\"1\",\"sequence\":\"12\",\"add_disabled\":\"1\",\"who_can_edit\":\"\\\"\\\"\"},{\"dummy\":\"\",\"field_name\":\"customer_number\",\"field_label\":\"Q3VzdG9tZXIgTnVtYmVy\",\"linked_to\":\"-1\",\"display_type\":\"0\",\"field_type\":\"0\",\"length\":\"255\",\"size\":\"12\",\"data_type\":\"text\",\"mandatory\":\"1\",\"is_unique\":\"1\",\"default_field\":\"1\",\"index_show\":\"1\",\"new\":\"1\",\"sequence\":\"9\",\"who_can_edit\":\"null\"}]', NULL, NULL, NULL, 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, NULL, 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', '2026-01-11 21:05:25', 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', NULL, NULL, '2026-01-11 21:05:25', 0, '43bcb91e-d10b-4d9c-9d62-edc6e97b6261', 'da75b706-16d4-41f2-92f5-6f1a0b165057', 'update_comany_id'),
('395bcbab-a29b-4f92-9dce-e80c5a8ddb96', 8, '581c1bb5db15645d76a7e672f882ac71', NULL, 'Supplier Details', '', 'tbl_supplier_details_0_v0s', 0, 0, '1158818613', NULL, NULL, NULL, 'e53215e1-ccea-4017-8fee-bd56f4b9975d', NULL, NULL, NULL, NULL, 0, '[{\"dummy\":\"\",\"field_name\":\"name\",\"field_label\":\"TmFtZQ==\",\"old_field_name\":\"name\",\"linked_to\":\"-1\",\"display_type\":\"0\",\"field_type\":\"0\",\"length\":\"255\",\"size\":\"12\",\"data_type\":\"text\",\"default_field\":\"0\",\"mandatory\":\"1\",\"index_show\":\"1\",\"drop\":\"0\",\"new\":\"1\",\"sequence\":\"0\",\"add_disabled\":\"0\",\"who_can_edit\":\"\\\"\\\"\"},{\"dummy\":\"\",\"field_name\":\"supplier_address\",\"field_label\":\"U3VwcGxpZXIgQWRkcmVzcw==\",\"old_field_name\":\"supplier_address\",\"linked_to\":\"-1\",\"display_type\":\"0\",\"field_type\":\"1\",\"length\":\"0\",\"size\":\"12\",\"data_type\":\"textarea\",\"mandatory\":\"1\",\"index_show\":\"0\",\"drop\":\"0\",\"new\":\"1\",\"sequence\":\"1\",\"add_disabled\":\"0\",\"who_can_edit\":\"\\\"\\\"\"},{\"dummy\":\"\",\"field_name\":\"supplier_phone\",\"field_label\":\"U3VwcGxpZXIgUGhvbmU=\",\"old_field_name\":\"supplier_phone\",\"linked_to\":\"-1\",\"display_type\":\"0\",\"field_type\":\"0\",\"length\":\"55\",\"size\":\"6\",\"data_type\":\"phone\",\"mandatory\":\"1\",\"index_show\":\"0\",\"drop\":\"0\",\"new\":\"1\",\"sequence\":\"2\",\"add_disabled\":\"0\",\"who_can_edit\":\"\\\"\\\"\"},{\"dummy\":\"\",\"field_name\":\"supplier_email\",\"field_label\":\"U3VwcGxpZXIgRW1haWw=\",\"old_field_name\":\"supplier_email\",\"linked_to\":\"-1\",\"display_type\":\"0\",\"field_type\":\"0\",\"length\":\"255\",\"size\":\"6\",\"data_type\":\"email\",\"mandatory\":\"1\",\"index_show\":\"0\",\"drop\":\"0\",\"new\":\"1\",\"sequence\":\"3\",\"add_disabled\":\"0\",\"who_can_edit\":\"\\\"\\\"\"},{\"dummy\":\"\",\"field_name\":\"supplier_since\",\"field_label\":\"U3VwcGxpZXIgU2luY2U=\",\"old_field_name\":\"supplier_since\",\"linked_to\":\"-1\",\"display_type\":\"0\",\"field_type\":\"5\",\"length\":\"255\",\"size\":\"6\",\"data_type\":\"date\",\"mandatory\":\"0\",\"index_show\":\"0\",\"drop\":\"0\",\"new\":\"1\",\"sequence\":\"4\",\"add_disabled\":\"0\",\"who_can_edit\":\"\\\"\\\"\",\"default_date_number\":\"0\",\"default_date_type\":\"-1\",\"default_date_from\":\"-1\"},{\"dummy\":\"0\",\"field_name\":\"supplier_company_type\",\"field_label\":\"U3VwcGxpZXIgQ29tcGFueSBUeXBl\",\"old_field_name\":\"supplier_company_type\",\"linked_to\":\"-1\",\"display_type\":\"1\",\"field_type\":\"2\",\"length\":\"1\",\"size\":\"6\",\"data_type\":\"radio\",\"csvoptions\":\"Self-owned, Partnership Firm, LLP, Pvt Ltd., Ltd. \",\"mandatory\":\"1\",\"index_show\":\"0\",\"drop\":\"0\",\"new\":\"1\",\"sequence\":\"5\",\"add_disabled\":\"0\",\"who_can_edit\":\"\\\"\\\"\"},{\"dummy\":\"\",\"field_name\":\"supplier_number\",\"field_label\":\"U3VwcGxpZXIgTnVtYmVy\",\"linked_to\":\"-1\",\"display_type\":\"0\",\"field_type\":\"0\",\"length\":\"255\",\"size\":\"12\",\"data_type\":\"text\",\"mandatory\":\"1\",\"is_unique\":\"1\",\"default_field\":\"1\",\"index_show\":\"1\",\"new\":\"1\",\"sequence\":\"7\",\"who_can_edit\":\"null\"}]', NULL, NULL, NULL, 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, NULL, 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', '2026-01-11 21:06:15', 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', NULL, NULL, '2026-01-11 21:06:15', 0, '43bcb91e-d10b-4d9c-9d62-edc6e97b6261', 'da75b706-16d4-41f2-92f5-6f1a0b165057', 'update_comany_id'),
('b5eaa2ea-e624-4028-887c-c55d3dc1cece', 9, '581c1bb5db15645d76a7e672f882ac71', NULL, 'Device Equipment', '', 'tbl_device_equipment_0_v0s', 0, 0, '2861404218', NULL, NULL, NULL, '5eea859b-aab2-4c26-8af3-abd16bbd02df', NULL, NULL, NULL, NULL, 0, '[{\"dummy\":\"\",\"field_name\":\"number\",\"field_label\":\"bnVtYmVy\",\"old_field_name\":\"name\",\"linked_to\":\"-1\",\"display_type\":\"0\",\"field_type\":\"0\",\"length\":\"255\",\"size\":\"6\",\"data_type\":\"text\",\"default_field\":\"1\",\"mandatory\":\"1\",\"index_show\":\"1\",\"drop\":\"0\",\"new\":\"1\",\"sequence\":\"\",\"add_disabled\":\"0\",\"who_can_edit\":\"\\\"\\\"\"},{\"dummy\":\"\",\"field_name\":\"equipment_name\",\"field_label\":\"RXF1aXBtZW50IE5hbWU=\",\"old_field_name\":\"equipment_name\",\"linked_to\":\"-1\",\"display_type\":\"0\",\"field_type\":\"0\",\"length\":\"255\",\"size\":\"6\",\"data_type\":\"text\",\"default_field\":\"0\",\"mandatory\":\"1\",\"index_show\":\"0\",\"drop\":\"0\",\"new\":\"1\",\"sequence\":\"\",\"add_disabled\":\"0\",\"who_can_edit\":\"\\\"\\\"\"},{\"dummy\":\"-1\",\"field_name\":\"location\",\"field_label\":\"TG9jYXRpb24=\",\"old_field_name\":\"location\",\"linked_to\":\"Branches\",\"display_type\":\"3\",\"field_type\":\"0\",\"length\":\"36\",\"size\":\"6\",\"data_type\":\"dropdown-s\",\"mandatory\":\"1\",\"index_show\":\"0\",\"drop\":\"0\",\"new\":\"1\",\"sequence\":\"\",\"add_disabled\":\"0\",\"who_can_edit\":\"\\\"\\\"\"},{\"dummy\":\"\",\"field_name\":\"in_service_since\",\"field_label\":\"SW4gU2VydmljZSBTaW5jZQ==\",\"old_field_name\":\"in_service_since\",\"linked_to\":\"-1\",\"display_type\":\"0\",\"field_type\":\"5\",\"length\":\"255\",\"size\":\"6\",\"data_type\":\"date\",\"mandatory\":\"1\",\"index_show\":\"0\",\"drop\":\"0\",\"new\":\"1\",\"sequence\":\"\",\"add_disabled\":\"0\",\"who_can_edit\":\"\\\"\\\"\",\"default_date_number\":\"0\",\"default_date_type\":\"-1\",\"default_date_from\":\"-1\"},{\"dummy\":\"\",\"field_name\":\"equipment_details\",\"field_label\":\"RXF1aXBtZW50IERldGFpbHM=\",\"old_field_name\":\"equipment_details\",\"linked_to\":\"-1\",\"display_type\":\"0\",\"field_type\":\"1\",\"length\":\"0\",\"size\":\"12\",\"data_type\":\"textarea\",\"mandatory\":\"1\",\"index_show\":\"0\",\"drop\":\"0\",\"new\":\"1\",\"sequence\":\"\",\"add_disabled\":\"0\",\"who_can_edit\":\"\\\"\\\"\"},{\"dummy\":\"\",\"field_name\":\"manufacturer_part_number\",\"field_label\":\"TWFudWZhY3R1cmVyIFBhcnQgTnVtYmVy\",\"old_field_name\":\"manufacturer_part_number\",\"linked_to\":\"-1\",\"display_type\":\"0\",\"field_type\":\"0\",\"length\":\"255\",\"size\":\"6\",\"data_type\":\"text\",\"default_field\":\"0\",\"mandatory\":\"0\",\"index_show\":\"0\",\"drop\":\"0\",\"new\":\"1\",\"sequence\":\"\",\"add_disabled\":\"0\",\"who_can_edit\":\"\\\"\\\"\"},{\"dummy\":\"-1\",\"field_name\":\"maintenance_schedule\",\"field_label\":\"TWFpbnRlbmFuY2UgU2NoZWR1bGU=\",\"old_field_name\":\"maintenance_schedule\",\"linked_to\":\"Schedules\",\"display_type\":\"3\",\"field_type\":\"0\",\"length\":\"36\",\"size\":\"6\",\"data_type\":\"dropdown-s\",\"mandatory\":\"1\",\"index_show\":\"0\",\"drop\":\"0\",\"new\":\"1\",\"sequence\":\"\",\"add_disabled\":\"0\",\"who_can_edit\":\"\\\"\\\"\"}]', NULL, NULL, NULL, 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, NULL, 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', '2026-01-11 21:07:59', 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', NULL, NULL, '2026-01-11 21:07:59', 0, '43bcb91e-d10b-4d9c-9d62-edc6e97b6261', 'da75b706-16d4-41f2-92f5-6f1a0b165057', 'update_comany_id'),
('e60b9ebd-6a04-4caa-975d-c7882a65a704', 10, '581c1bb5db15645d76a7e672f882ac71', NULL, 'Calibration', '', 'tbl_calibration_0_v0s', 0, 0, '185519025', NULL, NULL, NULL, 'a9d9fd99-157e-4028-a223-0d72b6d241c8', NULL, NULL, NULL, NULL, 0, '[{\"dummy\":\"\",\"field_name\":\"number\",\"field_label\":\"bnVtYmVy\",\"old_field_name\":\"name\",\"linked_to\":\"-1\",\"display_type\":\"0\",\"field_type\":\"0\",\"length\":\"255\",\"size\":\"6\",\"data_type\":\"text\",\"default_field\":\"1\",\"mandatory\":\"1\",\"index_show\":\"1\",\"drop\":\"0\",\"new\":\"1\",\"sequence\":\"\",\"add_disabled\":\"0\",\"who_can_edit\":\"\\\"\\\"\"},{\"dummy\":\"\",\"field_name\":\"equipement\",\"field_label\":\"RXF1aXBlbWVudA==\",\"old_field_name\":\"equipement\",\"linked_to\":\"-1\",\"display_type\":\"0\",\"field_type\":\"0\",\"length\":\"255\",\"size\":\"6\",\"data_type\":\"text\",\"default_field\":\"0\",\"mandatory\":\"1\",\"index_show\":\"0\",\"drop\":\"0\",\"new\":\"1\",\"sequence\":\"\",\"add_disabled\":\"0\",\"who_can_edit\":\"\\\"\\\"\"},{\"dummy\":\"\",\"field_name\":\"tool_details\",\"field_label\":\"VG9vbCBEZXRhaWxz\",\"old_field_name\":\"tool_details\",\"linked_to\":\"-1\",\"display_type\":\"0\",\"field_type\":\"0\",\"length\":\"255\",\"size\":\"6\",\"data_type\":\"text\",\"default_field\":\"0\",\"mandatory\":\"1\",\"index_show\":\"0\",\"drop\":\"0\",\"new\":\"1\",\"sequence\":\"\",\"add_disabled\":\"0\",\"who_can_edit\":\"\\\"\\\"\"},{\"dummy\":\"-1\",\"field_name\":\"tool_location\",\"field_label\":\"VG9vbCBMb2NhdGlvbg==\",\"old_field_name\":\"tool_location\",\"linked_to\":\"Branches\",\"display_type\":\"3\",\"field_type\":\"0\",\"length\":\"36\",\"size\":\"6\",\"data_type\":\"dropdown-s\",\"mandatory\":\"1\",\"index_show\":\"0\",\"drop\":\"0\",\"new\":\"1\",\"sequence\":\"\",\"add_disabled\":\"0\",\"who_can_edit\":\"\\\"\\\"\"},{\"dummy\":\"-1\",\"field_name\":\"calibration_frequency\",\"field_label\":\"Q2FsaWJyYXRpb24gRnJlcXVlbmN5\",\"old_field_name\":\"calibration_frequency\",\"linked_to\":\"Schedules\",\"display_type\":\"3\",\"field_type\":\"0\",\"length\":\"36\",\"size\":\"6\",\"data_type\":\"dropdown-s\",\"mandatory\":\"1\",\"index_show\":\"0\",\"drop\":\"0\",\"new\":\"1\",\"sequence\":\"\",\"add_disabled\":\"0\",\"who_can_edit\":\"\\\"\\\"\"},{\"dummy\":\"\",\"field_name\":\"previous_calibration_date\",\"field_label\":\"UHJldmlvdXMgQ2FsaWJyYXRpb24gRGF0ZQ==\",\"old_field_name\":\"previous_calibration_date\",\"linked_to\":\"-1\",\"display_type\":\"0\",\"field_type\":\"5\",\"length\":\"255\",\"size\":\"6\",\"data_type\":\"date\",\"mandatory\":\"0\",\"index_show\":\"0\",\"drop\":\"0\",\"new\":\"1\",\"sequence\":\"\",\"add_disabled\":\"0\",\"who_can_edit\":\"\\\"\\\"\",\"default_date_number\":\"0\",\"default_date_type\":\"-1\",\"default_date_from\":\"-1\"},{\"dummy\":\"\",\"field_name\":\"next_calibration_date\",\"field_label\":\"TmV4dCBDYWxpYnJhdGlvbiBEYXRl\",\"old_field_name\":\"next_calibration_date\",\"linked_to\":\"-1\",\"display_type\":\"0\",\"field_type\":\"5\",\"length\":\"255\",\"size\":\"6\",\"data_type\":\"date\",\"mandatory\":\"0\",\"index_show\":\"0\",\"drop\":\"0\",\"new\":\"1\",\"sequence\":\"\",\"add_disabled\":\"0\",\"who_can_edit\":\"\\\"\\\"\",\"default_date_number\":\"0\",\"default_date_type\":\"-1\",\"default_date_from\":\"-1\"},{\"dummy\":\"-1\",\"field_name\":\"calibration_performed_by\",\"field_label\":\"Q2FsaWJyYXRpb24gUGVyZm9ybWVkIEJ5\",\"old_field_name\":\"calibration_performed_by\",\"linked_to\":\"Employees\",\"display_type\":\"3\",\"field_type\":\"0\",\"length\":\"36\",\"size\":\"6\",\"data_type\":\"dropdown-s\",\"mandatory\":\"1\",\"index_show\":\"0\",\"drop\":\"0\",\"new\":\"1\",\"sequence\":\"\",\"add_disabled\":\"0\",\"who_can_edit\":\"\\\"\\\"\"},{\"field_name\":\"comments8\",\"show_comments\":\"WW91IGNhbiBhZGQgYWN0dWFsIGNhbGlicmF0aW9uIHJlYWRpbmdzIGluc2lkZSB0aGUgY2FsaWJyYXRpb24gZG9jdW1lbnQu\",\"size\":\"12\",\"display_type\":\"7\",\"field_type\":\"0\",\"data_type\":\"comments\",\"mandatory\":\"0\",\"index_show\":\"0\",\"new\":\"1\",\"sequence\":\"8\",\"linked_to\":\"-1\",\"dummy\":\"0\",\"drop\":\"0\",\"old_field_name\":\"0\",\"add_disabled\":\"0\",\"who_can_edit\":\"\\\"\\\"\",\"field_label\":\"\"}]', NULL, NULL, NULL, 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, NULL, 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', '2026-01-11 21:08:52', 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', NULL, NULL, '2026-01-11 21:08:52', 0, '43bcb91e-d10b-4d9c-9d62-edc6e97b6261', 'da75b706-16d4-41f2-92f5-6f1a0b165057', 'update_comany_id'),
('2fcdbccf-b34b-467e-957e-da9a1eb934e2', 11, '581c1bb5db15645d76a7e672f882ac71', NULL, 'MRM Child 1', '', 'chd_mrm0_child_1_v1s', 1, 0, NULL, NULL, NULL, NULL, 'ea17ca9b-c5ec-4f0d-ad7c-14f534b5f32d', NULL, NULL, '', 'd9385db9-54d3-4569-b625-7507181d00d5', 0, '[{\"dummy\":\"\",\"field_name\":\"audit_number\",\"field_label\":\"QXVkaXQgTnVtYmVy\",\"old_field_name\":\"audit_number\",\"linked_to\":\"-1\",\"display_type\":\"0\",\"field_type\":\"0\",\"length\":\"255\",\"size\":\"12\",\"data_type\":\"text\",\"mandatory\":\"1\",\"default_field\":\"1\",\"is_unique\":\"0\",\"index_show\":\"1\",\"drop\":\"0\",\"new\":\"0\",\"sequence\":\"\",\"show_last_value\":\"0\",\"add_disabled\":\"0\",\"edit_disabled\":\"0\",\"who_can_edit\":\"\",\"session_value\":\"\"},{\"dummy\":\"\",\"field_name\":\"agenda_details\",\"field_label\":\"QWdlbmRhIERldGFpbHM=\",\"old_field_name\":\"agenda_details\",\"linked_to\":\"-1\",\"display_type\":\"0\",\"field_type\":\"0\",\"length\":\"255\",\"size\":\"12\",\"data_type\":\"text\",\"mandatory\":\"0\",\"default_field\":\"0\",\"is_unique\":\"0\",\"index_show\":\"0\",\"drop\":\"0\",\"new\":\"0\",\"sequence\":\"\",\"add_disabled\":\"0\",\"edit_disabled\":\"0\",\"who_can_edit\":\"\",\"session_value\":\"\"},{\"dummy\":\"-1\",\"field_name\":\"assigned_to\",\"field_label\":\"QXNzaWduZWQgVG8=\",\"old_field_name\":\"assigned_to\",\"linked_to\":\"Employees\",\"display_type\":\"3\",\"field_type\":\"0\",\"length\":\"36\",\"size\":\"6\",\"data_type\":\"dropdown-s\",\"mandatory\":\"0\",\"index_show\":\"0\",\"drop\":\"0\",\"new\":\"0\",\"sequence\":\"\",\"add_signature\":\"0\",\"add_disabled\":\"0\",\"edit_disabled\":\"0\",\"who_can_edit\":\"\",\"session_value\":\"\"},{\"dummy\":\"\",\"field_name\":\"target_date\",\"field_label\":\"VGFyZ2V0IERhdGU=\",\"old_field_name\":\"target_date\",\"linked_to\":\"-1\",\"display_type\":\"0\",\"field_type\":\"5\",\"length\":\"255\",\"size\":\"6\",\"data_type\":\"date\",\"mandatory\":\"1\",\"default_field\":\"0\",\"is_unique\":\"0\",\"index_show\":\"1\",\"drop\":\"0\",\"new\":\"0\",\"sequence\":\"\",\"add_disabled\":\"0\",\"edit_disabled\":\"0\",\"who_can_edit\":\"[\\\"prepared_by\\\"]\",\"session_value\":\"\",\"default_date_number\":\"1\",\"default_date_type\":\"1\",\"default_date_from\":\"Today\"},{\"dummy\":\"\",\"field_name\":\"closure_comments\",\"field_label\":\"Q2xvc3VyZSBDb21tZW50cw==\",\"old_field_name\":\"closure_comments\",\"linked_to\":\"-1\",\"display_type\":\"0\",\"field_type\":\"1\",\"length\":\"0\",\"size\":\"12\",\"data_type\":\"textarea\",\"mandatory\":\"0\",\"index_show\":\"0\",\"drop\":\"0\",\"new\":\"0\",\"sequence\":\"\",\"add_disabled\":\"1\",\"edit_disabled\":\"0\",\"who_can_edit\":\"\",\"session_value\":\"\"},{\"dummy\":\"0\",\"field_name\":\"current_status\",\"field_label\":\"Q3VycmVudCBTdGF0dXM=\",\"old_field_name\":\"current_status\",\"linked_to\":\"-1\",\"display_type\":\"1\",\"field_type\":\"2\",\"length\":\"1\",\"size\":\"12\",\"data_type\":\"radio\",\"csvoptions\":\"Open,Closed\",\"mandatory\":\"0\",\"index_show\":\"0\",\"drop\":\"0\",\"new\":\"0\",\"sequence\":\"\",\"add_disabled\":\"1\",\"edit_disabled\":\"0\",\"who_can_edit\":\"\",\"session_value\":\"\"}]', NULL, '', NULL, 2, NULL, NULL, NULL, NULL, NULL, NULL, '[\"e6c97faa-f602-4f37-ae2d-7c95c11e1817\",\"a3d53ee2-adcd-4433-9363-87aed5be038c\",\"c185cfd1-e92c-4fcf-8f5d-9365a18ab81a\",\"6e98f2d1-879d-43a2-96b5-1e153a39ece6\",\"5e665aa1-4a3c-4baa-b6cc-09bfe007f8b1\",\"b3bac56f-2beb-4e94-92b4-c06bef2914f9\"]', '[\"e6c97faa-f602-4f37-ae2d-7c95c11e1817\",\"a3d53ee2-adcd-4433-9363-87aed5be038c\",\"c185cfd1-e92c-4fcf-8f5d-9365a18ab81a\",\"6e98f2d1-879d-43a2-96b5-1e153a39ece6\",\"5e665aa1-4a3c-4baa-b6cc-09bfe007f8b1\",\"b3bac56f-2beb-4e94-92b4-c06bef2914f9\"]', '[\"e6c97faa-f602-4f37-ae2d-7c95c11e1817\",\"a3d53ee2-adcd-4433-9363-87aed5be038c\",\"c185cfd1-e92c-4fcf-8f5d-9365a18ab81a\",\"6e98f2d1-879d-43a2-96b5-1e153a39ece6\",\"5e665aa1-4a3c-4baa-b6cc-09bfe007f8b1\",\"b3bac56f-2beb-4e94-92b4-c06bef2914f9\"]', '[\"e6c97faa-f602-4f37-ae2d-7c95c11e1817\",\"a3d53ee2-adcd-4433-9363-87aed5be038c\",\"c185cfd1-e92c-4fcf-8f5d-9365a18ab81a\",\"6e98f2d1-879d-43a2-96b5-1e153a39ece6\",\"5e665aa1-4a3c-4baa-b6cc-09bfe007f8b1\",\"b3bac56f-2beb-4e94-92b4-c06bef2914f9\"]', 1, 0, 0, NULL, 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', '2026-01-11 21:14:20', 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', NULL, NULL, '2026-01-11 21:16:30', 0, '43bcb91e-d10b-4d9c-9d62-edc6e97b6261', 'da75b706-16d4-41f2-92f5-6f1a0b165057', 'update_comany_id');

-- --------------------------------------------------------

--
-- Table structure for table `custom_table_processes`
--

CREATE TABLE `custom_table_processes` (
  `id` varchar(36) NOT NULL,
  `sr_no` int(11) NOT NULL,
  `custom_table_id` varchar(36) NOT NULL,
  `process_id` varchar(36) NOT NULL,
  `sequence` int(11) NOT NULL,
  `publish` tinyint(1) DEFAULT '1' COMMENT '0=Un 1=Pub',
  `record_status` tinyint(1) DEFAULT '0' COMMENT '0=Un-locked, 1=Locked',
  `status_user_id` varchar(36) DEFAULT NULL,
  `created_by` varchar(36) NOT NULL,
  `created` datetime NOT NULL,
  `modified_by` varchar(36) NOT NULL,
  `approved_by` varchar(36) DEFAULT NULL,
  `prepared_by` varchar(36) DEFAULT NULL,
  `modified` datetime NOT NULL,
  `soft_delete` tinyint(1) NOT NULL DEFAULT '0',
  `branchid` varchar(36) DEFAULT NULL,
  `departmentid` varchar(36) DEFAULT NULL,
  `company_id` varchar(36) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `custom_table_tasks`
--

CREATE TABLE `custom_table_tasks` (
  `id` varchar(36) NOT NULL,
  `sr_no` int(11) NOT NULL,
  `custom_table_id` varchar(36) NOT NULL,
  `employee_field` varchar(250) NOT NULL,
  `condition_field` varchar(250) NOT NULL,
  `condition` varchar(10) NOT NULL,
  `csvoption` int(1) DEFAULT '0',
  `date_field` varchar(250) NOT NULL,
  `message` text,
  `publish` tinyint(1) DEFAULT '0' COMMENT '0=Un 1=Pub',
  `record_status` tinyint(1) DEFAULT '0' COMMENT '0=Un-locked, 1=Locked',
  `status_user_id` varchar(36) DEFAULT NULL,
  `soft_delete` tinyint(1) DEFAULT '0' COMMENT '1=deleted',
  `branchid` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `departmentid` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `created_by` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `created` datetime NOT NULL COMMENT 'system defined automatically add',
  `modified_by` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `approved_by` varchar(36) DEFAULT NULL,
  `prepared_by` varchar(36) DEFAULT NULL,
  `modified` datetime NOT NULL COMMENT 'system defined automatically add',
  `system_table_id` varchar(36) DEFAULT '0',
  `company_id` varchar(36) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `custom_triggers`
--

CREATE TABLE `custom_triggers` (
  `id` varchar(36) NOT NULL,
  `sr_no` int(11) NOT NULL,
  `custom_table_id` varchar(36) NOT NULL,
  `action` int(1) DEFAULT NULL,
  `name` varchar(120) NOT NULL,
  `details` text,
  `field_name` varchar(250) DEFAULT NULL,
  `changed_field_value` varchar(250) DEFAULT NULL,
  `notify_user` varchar(36) DEFAULT NULL,
  `notify_users` text,
  `hod_departments` text,
  `notify_admins` tinyint(1) DEFAULT '0',
  `notify_hods` tinyint(1) DEFAULT '0',
  `notify_departments` tinyint(1) DEFAULT NULL,
  `notify_branches` tinyint(1) DEFAULT NULL,
  `notify_designations` tinyint(1) DEFAULT NULL,
  `recipents` text,
  `message` text,
  `record_status` tinyint(1) DEFAULT '0' COMMENT '0=Un-locked, 1=Locked',
  `status_user_id` varchar(36) DEFAULT NULL,
  `created_by` varchar(36) NOT NULL,
  `created` datetime NOT NULL,
  `modified_by` varchar(36) NOT NULL,
  `approved_by` varchar(36) DEFAULT NULL,
  `prepared_by` varchar(36) DEFAULT NULL,
  `modified` datetime NOT NULL,
  `publish` tinyint(1) DEFAULT NULL,
  `soft_delete` tinyint(1) NOT NULL DEFAULT '0',
  `branchid` varchar(36) DEFAULT NULL,
  `departmentid` varchar(36) DEFAULT NULL,
  `company_id` varchar(36) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` varchar(36) NOT NULL,
  `sr_no` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `clauses` text,
  `details` text,
  `publish` tinyint(1) DEFAULT '0' COMMENT '0=Un 1=Pub',
  `record_status` tinyint(1) DEFAULT '0' COMMENT '0=Un-locked, 1=Locked',
  `status_user_id` varchar(36) DEFAULT NULL,
  `soft_delete` tinyint(1) DEFAULT '0' COMMENT '1=deleted',
  `branchid` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `departmentid` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `created_by` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `created` datetime NOT NULL COMMENT 'system defined automatically add',
  `modified_by` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `approved_by` varchar(36) DEFAULT NULL,
  `prepared_by` varchar(36) DEFAULT NULL,
  `modified` datetime NOT NULL COMMENT 'system defined automatically add',
  `system_table_id` varchar(36) DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `designations`
--

CREATE TABLE `designations` (
  `id` varchar(36) NOT NULL,
  `sr_no` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `parent_id` varchar(36) DEFAULT NULL,
  `level` int(11) NOT NULL DEFAULT '0',
  `publish` tinyint(1) DEFAULT '0' COMMENT '0=Un 1=Pub',
  `record_status` tinyint(1) DEFAULT '0' COMMENT '0=Un-locked, 1=Locked',
  `status_user_id` varchar(36) DEFAULT NULL,
  `soft_delete` tinyint(1) DEFAULT '0' COMMENT '1=deleted',
  `branchid` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `departmentid` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `created_by` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `created` datetime NOT NULL COMMENT 'system defined automatically add',
  `modified_by` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `approved_by` varchar(36) DEFAULT NULL,
  `prepared_by` varchar(36) DEFAULT NULL,
  `modified` datetime NOT NULL COMMENT 'system defined automatically add',
  `system_table_id` varchar(36) DEFAULT '0',
  `division_id` varchar(36) DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `document_change_requests`
--

CREATE TABLE `document_change_requests` (
  `id` varchar(36) NOT NULL,
  `sr_no` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `details` text,
  `publish` tinyint(1) DEFAULT '0' COMMENT '0=Un 1=Pub',
  `record_status` tinyint(1) DEFAULT '0' COMMENT '0=Un-locked, 1=Locked',
  `status_user_id` varchar(36) DEFAULT NULL,
  `soft_delete` tinyint(1) DEFAULT '0' COMMENT '1=deleted',
  `branchid` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `departmentid` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `created_by` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `created` datetime NOT NULL COMMENT 'system defined automatically add',
  `modified_by` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `approved_by` varchar(36) DEFAULT NULL,
  `prepared_by` varchar(36) DEFAULT NULL,
  `modified` datetime NOT NULL COMMENT 'system defined automatically add',
  `system_table_id` varchar(36) DEFAULT '0',
  `division_id` varchar(36) DEFAULT '0',
  `company_id` varchar(36) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `document_downloads`
--

CREATE TABLE `document_downloads` (
  `id` varchar(36) NOT NULL,
  `sr_no` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `qc_document_id` varchar(36) DEFAULT NULL,
  `custom_table_id` varchar(36) DEFAULT NULL,
  `record_id` varchar(36) DEFAULT NULL,
  `file_id` varchar(36) DEFAULT NULL,
  `download_by` varchar(36) NOT NULL,
  `issue` int(11) DEFAULT '0',
  `signature` text,
  `digital_signature` text,
  `add_document` tinyint(1) NOT NULL COMMENT '0=yes,1=no',
  `add_cover_page` tinyint(1) DEFAULT NULL COMMENT '0=yes,1=No',
  `add_parent_records` tinyint(1) DEFAULT NULL COMMENT '0=yes,1=No',
  `add_child_records` tinyint(1) DEFAULT NULL COMMENT '0=yes,1=No',
  `add_linked_form_records` tinyint(1) DEFAULT NULL COMMENT '0=yes,1=No',
  `created_by` varchar(36) NOT NULL,
  `download` tinyint(1) DEFAULT NULL,
  `downoad_time` datetime DEFAULT NULL,
  `created` datetime NOT NULL,
  `modified_by` varchar(36) NOT NULL,
  `approved_by` varchar(36) DEFAULT NULL,
  `prepared_by` varchar(36) DEFAULT NULL,
  `modified` datetime NOT NULL,
  `soft_delete` tinyint(1) NOT NULL DEFAULT '0',
  `branchid` varchar(36) DEFAULT NULL,
  `departmentid` varchar(36) DEFAULT NULL,
  `company_id` varchar(36) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` varchar(36) NOT NULL,
  `sr_no` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `parent_id` varchar(36) DEFAULT NULL,
  `employee_number` char(20) NOT NULL,
  `identification_number` char(20) DEFAULT NULL,
  `branch_id` varchar(36) NOT NULL,
  `department_id` varchar(36) DEFAULT NULL,
  `designation_id` varchar(36) DEFAULT NULL,
  `is_hod` int(1) NOT NULL DEFAULT '0',
  `qualification` varchar(255) DEFAULT NULL,
  `joining_date` date DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `pancard_number` char(15) DEFAULT 'NULL',
  `personal_telephone` varchar(120) DEFAULT NULL,
  `office_telephone` varchar(120) DEFAULT 'NULL',
  `mobile` varchar(120) DEFAULT 'NULL',
  `personal_email` varchar(250) DEFAULT 'NULL',
  `office_email` varchar(255) NOT NULL,
  `residence_address` text,
  `permenant_address` text,
  `maritial_status` int(11) DEFAULT NULL,
  `driving_license` char(40) DEFAULT 'NULL',
  `employment_status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '0=Resigned, 1=Active',
  `is_approver` tinyint(1) DEFAULT '0',
  `signature` text,
  `publish` tinyint(1) DEFAULT '0' COMMENT '0=Un 1=Pub',
  `record_status` tinyint(1) DEFAULT '0' COMMENT '0=Un-locked, 1=Locked',
  `status_user_id` varchar(36) DEFAULT NULL,
  `soft_delete` tinyint(1) DEFAULT '0' COMMENT '1=deleted',
  `branchid` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `departmentid` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `created_by` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `created` datetime NOT NULL COMMENT 'system defined automatically add',
  `modified_by` varchar(36) DEFAULT NULL COMMENT 'system defined automatically add',
  `approved_by` varchar(36) DEFAULT NULL,
  `prepared_by` varchar(36) DEFAULT NULL,
  `modified` datetime DEFAULT NULL COMMENT 'system defined automatically add',
  `system_table_id` varchar(36) DEFAULT '0',
  `company_id` varchar(36) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `employee_leaves`
--

CREATE TABLE `employee_leaves` (
  `id` varchar(36) NOT NULL,
  `sr_no` int(11) NOT NULL,
  `employee_id` varchar(36) DEFAULT NULL,
  `from` date DEFAULT NULL,
  `to` date DEFAULT NULL,
  `who_approved` varchar(36) DEFAULT NULL,
  `reason` text,
  `other` text,
  `publish` tinyint(1) DEFAULT '1' COMMENT '0=Un 1=Pub',
  `record_status` tinyint(1) DEFAULT '0' COMMENT '0=Un-locked, 1=Locked',
  `status_user_id` varchar(36) DEFAULT NULL,
  `created_by` varchar(36) NOT NULL,
  `created` datetime NOT NULL,
  `modified_by` varchar(36) NOT NULL,
  `approved_by` varchar(36) DEFAULT NULL,
  `prepared_by` varchar(36) DEFAULT NULL,
  `modified` datetime NOT NULL,
  `soft_delete` tinyint(1) NOT NULL DEFAULT '0',
  `branchid` varchar(36) DEFAULT NULL,
  `departmentid` varchar(36) DEFAULT NULL,
  `company_id` varchar(36) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `files`
--

CREATE TABLE `files` (
  `id` varchar(36) NOT NULL,
  `sr_no` int(11) NOT NULL,
  `data_received` text,
  `name` varchar(255) NOT NULL,
  `file_type` varchar(5) NOT NULL,
  `file_status` int(1) DEFAULT '0' COMMENT '0=copied,1=saved',
  `last_saved` datetime DEFAULT NULL,
  `model` varchar(255) NOT NULL,
  `controller` varchar(255) NOT NULL,
  `pre_file_id` varchar(50) DEFAULT NULL,
  `file_key` varchar(50) NOT NULL,
  `pre_file_key` varchar(50) DEFAULT NULL,
  `version_keys` text,
  `versions` text,
  `user_id` varchar(36) NOT NULL,
  `qc_document_id` varchar(36) DEFAULT NULL,
  `process_id` varchar(36) DEFAULT NULL,
  `custom_table_id` varchar(36) NOT NULL,
  `record_id` varchar(36) NOT NULL,
  `record_status` tinyint(1) DEFAULT '0' COMMENT '0=Un-locked, 1=Locked',
  `status_user_id` varchar(36) DEFAULT NULL,
  `created_by` varchar(36) NOT NULL,
  `created` datetime NOT NULL,
  `modified_by` varchar(36) NOT NULL,
  `approved_by` varchar(36) DEFAULT NULL,
  `prepared_by` varchar(36) DEFAULT NULL,
  `modified` datetime NOT NULL,
  `soft_delete` tinyint(1) NOT NULL DEFAULT '0',
  `branchid` varchar(36) DEFAULT NULL,
  `departmentid` varchar(36) DEFAULT NULL,
  `company_id` varchar(36) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


-- --------------------------------------------------------

--
-- Table structure for table `graph_panels`
--

CREATE TABLE `graph_panels` (
  `id` varchar(36) NOT NULL,
  `sr_no` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `custom_table_id` varchar(36) NOT NULL,
  `field_name` varchar(250) NOT NULL,
  `linked_to` varchar(250) DEFAULT NULL,
  `date_condition` int(1) NOT NULL,
  `graph_type` int(1) NOT NULL DEFAULT '0',
  `data_type` int(11) DEFAULT '0' COMMENT '0=count,1=sum,2=avg',
  `value_field` varchar(255) DEFAULT NULL,
  `color` varchar(8) DEFAULT NULL,
  `position` int(11) DEFAULT '0',
  `size` int(11) DEFAULT '3',
  `admin_only` int(1) DEFAULT NULL,
  `branches` text,
  `departments` text,
  `designations` text,
  `publish` tinyint(1) DEFAULT '0' COMMENT '0=Un 1=Pub',
  `created_by` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `created` datetime NOT NULL COMMENT 'system defined automatically add',
  `system_table_id` varchar(36) DEFAULT '0',
  `company_id` varchar(36) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `histories`
--

CREATE TABLE `histories` (
  `id` varchar(36) NOT NULL,
  `sr_no` int(11) NOT NULL,
  `model_name` varchar(225) DEFAULT NULL,
  `controller_name` varchar(250) DEFAULT NULL,
  `action` varchar(225) DEFAULT NULL,
  `record_id` varchar(36) DEFAULT NULL,
  `get_values` longtext,
  `pre_post_values` longtext,
  `post_values` longtext,
  `user_session_id` varchar(36) DEFAULT NULL,
  `branch_id` varchar(36) DEFAULT NULL,
  `department_id` varchar(36) DEFAULT NULL,
  `publish` tinyint(1) DEFAULT '0' COMMENT '0=Un 1=Pub',
  `record_status` tinyint(1) DEFAULT '0' COMMENT '0=Un-locked, 1=Locked',
  `status_user_id` varchar(36) DEFAULT NULL,
  `soft_delete` tinyint(1) DEFAULT '0' COMMENT '1=deleted',
  `branchid` varchar(36) DEFAULT NULL COMMENT 'system defined automatically add',
  `departmentid` varchar(36) DEFAULT NULL COMMENT 'system defined automatically add',
  `created_by` varchar(36) DEFAULT NULL COMMENT 'system defined automatically add',
  `created` datetime DEFAULT NULL COMMENT 'system defined automatically add',
  `modified_by` varchar(36) DEFAULT NULL COMMENT 'system defined automatically add',
  `approved_by` varchar(36) DEFAULT NULL,
  `prepared_by` varchar(36) DEFAULT NULL,
  `modified` datetime DEFAULT NULL COMMENT 'system defined automatically add',
  `system_table_id` varchar(36) DEFAULT '0',
  `division_id` varchar(36) DEFAULT '0',
  `company_id` varchar(36) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` varchar(36) NOT NULL,
  `sr_no` int(11) NOT NULL,
  `razorpay_id` varchar(36) DEFAULT NULL,
  `entity` varchar(36) DEFAULT 'invoice',
  `type` varchar(36) DEFAULT 'invoice',
  `draft` int(1) DEFAULT NULL,
  `invoice_number` varchar(120) NOT NULL,
  `invoice_date` date NOT NULL,
  `customer_id` varchar(36) NOT NULL,
  `order_id` varchar(36) DEFAULT NULL,
  `payment_id` varchar(36) DEFAULT NULL,
  `status` varchar(30) DEFAULT 'draft',
  `expire_by` date NOT NULL,
  `issued_at` date NOT NULL,
  `paid_at` date DEFAULT NULL,
  `cancelled_at` date DEFAULT NULL,
  `expired_at` date DEFAULT NULL,
  `email_status` varchar(10) DEFAULT NULL,
  `partial_payment` tinyint(1) NOT NULL DEFAULT '0',
  `amount` float(11,2) NOT NULL,
  `amount_paid` int(11) DEFAULT NULL,
  `amount_due` int(11) DEFAULT NULL,
  `currency` varchar(5) NOT NULL,
  `description` varchar(255) NOT NULL,
  `item_details` text NOT NULL,
  `notes` text,
  `short_url` text,
  `date` date DEFAULT NULL,
  `terms` text,
  `comment` varchar(250) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `publish` tinyint(1) DEFAULT '0' COMMENT '0=Un 1=Pub',
  `record_status` tinyint(1) DEFAULT '0' COMMENT '0=Un-locked, 1=Locked',
  `status_user_id` varchar(36) DEFAULT NULL,
  `created_by` varchar(36) NOT NULL,
  `created` datetime NOT NULL,
  `modified_by` varchar(36) NOT NULL,
  `approved_by` varchar(36) DEFAULT NULL,
  `prepared_by` varchar(36) DEFAULT NULL,
  `modified` datetime NOT NULL,
  `soft_delete` tinyint(1) NOT NULL DEFAULT '0',
  `branchid` varchar(36) DEFAULT NULL,
  `departmentid` varchar(36) DEFAULT NULL,
  `company_id` varchar(36) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `password_settings`
--

CREATE TABLE `password_settings` (
  `id` varchar(36) NOT NULL,
  `sr_no` int(11) NOT NULL,
  `password_max_len` int(2) DEFAULT NULL,
  `password_min_len` int(2) DEFAULT NULL,
  `display_policy` tinyint(1) DEFAULT NULL,
  `concurrent_login` int(1) DEFAULT NULL,
  `password_change_remind` int(2) DEFAULT NULL,
  `password_uppercase_length` int(2) DEFAULT NULL,
  `password_uppercase_start` int(2) DEFAULT NULL,
  `password_special_character` int(1) DEFAULT NULL,
  `password_same_username` int(1) DEFAULT NULL,
  `password_repeat` int(1) DEFAULT NULL,
  `publish` tinyint(1) DEFAULT NULL,
  `record_status` tinyint(1) DEFAULT NULL,
  `status_user_id` varchar(36) DEFAULT NULL,
  `soft_delete` tinyint(1) DEFAULT '0',
  `branchid` varchar(36) NOT NULL,
  `departmentid` varchar(36) NOT NULL,
  `created_by` varchar(36) NOT NULL,
  `created` datetime NOT NULL,
  `modified_by` varchar(36) NOT NULL,
  `modified` datetime NOT NULL,
  `approved_by` varchar(36) DEFAULT NULL,
  `prepared_by` varchar(36) DEFAULT NULL,
  `system_table_id` varchar(36) DEFAULT NULL,
  `company_id` varchar(36) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `password_settings`
--

INSERT INTO `password_settings` (`id`, `sr_no`, `password_max_len`, `password_min_len`, `display_policy`, `concurrent_login`, `password_change_remind`, `password_uppercase_length`, `password_uppercase_start`, `password_special_character`, `password_same_username`, `password_repeat`, `publish`, `record_status`, `status_user_id`, `soft_delete`, `branchid`, `departmentid`, `created_by`, `created`, `modified_by`, `modified`, `approved_by`, `prepared_by`, `system_table_id`, `company_id`) VALUES
('c958dee2-1cd9-4601-8c6e-4f6ce471e608', 1, 10, 3, 1, 1, 3, 1, 1, 1, 1, 3, 1, NULL, NULL, 0, '43bcb91e-d10b-4d9c-9d62-edc6e97b6261', 'da75b706-16d4-41f2-92f5-6f1a0b165057', 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', '2025-03-11 08:17:08', 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', '2025-04-16 00:45:58', NULL, NULL, NULL, 'update_comany_id');

-- --------------------------------------------------------

--
-- Table structure for table `pdf_templates`
--

CREATE TABLE `pdf_templates` (
  `id` varchar(36) NOT NULL,
  `sr_no` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `custom_table_id` varchar(36) NOT NULL,
  `template_type` int(11) DEFAULT NULL,
  `header` int(11) DEFAULT NULL,
  `outline` int(11) DEFAULT NULL,
  `dpi` int(11) DEFAULT NULL,
  `outline_depth` int(11) DEFAULT NULL,
  `header_spacing` int(11) DEFAULT NULL,
  `footer_left` varchar(55) DEFAULT NULL,
  `footer_center` varchar(55) DEFAULT NULL,
  `footer_right` varchar(55) DEFAULT NULL,
  `footer_font_size` int(11) DEFAULT NULL,
  `margin_bottom` int(11) DEFAULT NULL,
  `margin_left` int(11) DEFAULT NULL,
  `margin_right` int(11) DEFAULT NULL,
  `margin_top` int(11) DEFAULT NULL,
  `html_cleanup` int(11) DEFAULT NULL,
  `template` text NOT NULL,
  `child_table_fields` text,
  `publish` tinyint(1) DEFAULT '1' COMMENT '0=Un 1=Pub',
  `record_status` tinyint(1) DEFAULT '0' COMMENT '0=Un-locked, 1=Locked',
  `status_user_id` varchar(36) DEFAULT NULL,
  `created_by` varchar(36) NOT NULL,
  `created` datetime NOT NULL,
  `modified_by` varchar(36) NOT NULL,
  `approved_by` varchar(36) DEFAULT NULL,
  `prepared_by` varchar(36) DEFAULT NULL,
  `modified` datetime NOT NULL,
  `soft_delete` tinyint(1) NOT NULL DEFAULT '0',
  `branchid` varchar(36) DEFAULT NULL,
  `departmentid` varchar(36) DEFAULT NULL,
  `company_id` varchar(36) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `processes`
--

CREATE TABLE `processes` (
  `id` varchar(36) NOT NULL,
  `sr_no` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_key` varchar(50) DEFAULT NULL,
  `file_type` varchar(5) DEFAULT NULL,
  `version_keys` text,
  `qc_document_id` varchar(36) NOT NULL DEFAULT '4768007e-8971-48b0-b6f8-4a0fb50d2425',
  `custom_table_id` varchar(36) NOT NULL DEFAULT 'd5e155ee-2de3-414f-8796-b90a45579e72',
  `process_definition` text,
  `process_objective_and_metrics` text,
  `process_owners` text,
  `applicable_to_branches` text,
  `additional_responsibilities` text,
  `input_processes` text,
  `output_processes` text,
  `process_output` text,
  `risks_and_opportunities` text,
  `standards` text,
  `clauses` text,
  `schedule_id` varchar(36) DEFAULT NULL,
  `data_types` int(1) DEFAULT NULL COMMENT '0=''Document'',1=''Data'',2=''Both''',
  `add_records` int(1) DEFAULT '0',
  `process_status` int(1) DEFAULT NULL COMMENT '''0'' => ''Draft'',''1'' = ''Published/Issued'',''2'' = ''Approved'',''3'' = ''Under Revision'',''4'' = ''Archived'',''5'' = ''Awaiting Issue''',
  `publish` tinyint(1) DEFAULT '1' COMMENT '0=Un 1=Pub',
  `record_status` tinyint(1) DEFAULT '0' COMMENT '0=Un-locked, 1=Locked',
  `status_user_id` varchar(36) DEFAULT NULL,
  `created_by` varchar(36) NOT NULL,
  `created` datetime NOT NULL,
  `modified_by` varchar(36) NOT NULL,
  `approved_by` varchar(36) DEFAULT NULL,
  `prepared_by` varchar(36) DEFAULT NULL,
  `modified` datetime NOT NULL,
  `soft_delete` tinyint(1) NOT NULL DEFAULT '0',
  `branchid` varchar(36) DEFAULT NULL,
  `departmentid` varchar(36) DEFAULT NULL,
  `company_id` varchar(36) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `qc_documents`
--

CREATE TABLE `qc_documents` (
  `id` varchar(36) NOT NULL,
  `sr_no` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `title` varchar(250) NOT NULL,
  `file_key` varchar(50) DEFAULT NULL,
  `version` int(11) DEFAULT NULL,
  `versions` text,
  `version_keys` text,
  `file_status` int(1) DEFAULT '0' COMMENT '0=copied,1=saved',
  `last_saved` datetime DEFAULT NULL,
  `update_custom_table_document` int(1) DEFAULT '0' COMMENT '0=no,1=Yes',
  `file_type` varchar(5) DEFAULT NULL,
  `schedule_id` varchar(36) DEFAULT NULL,
  `data_update_type` int(1) DEFAULT '0',
  `data_type` int(1) NOT NULL DEFAULT '2',
  `data_file_type` int(1) DEFAULT '0',
  `add_records` int(1) DEFAULT '0',
  `qc_document_category_id` varchar(36) DEFAULT NULL,
  `clause_id` varchar(36) DEFAULT NULL,
  `standard_id` varchar(36) DEFAULT NULL,
  `additional_clauses` varchar(255) DEFAULT NULL,
  `document_number` varchar(100) NOT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `issue_number` varchar(2) DEFAULT '0',
  `date_of_next_issue` date DEFAULT NULL,
  `date_of_issue` date NOT NULL,
  `effective_from_date` date DEFAULT NULL,
  `revision_number` int(2) DEFAULT NULL,
  `date_created` date DEFAULT NULL,
  `update_version` int(1) DEFAULT '0',
  `date_of_review` date DEFAULT NULL,
  `revision_date` date DEFAULT NULL,
  `document_type` int(1) NOT NULL,
  `it_categories` int(1) DEFAULT NULL,
  `document_status` int(1) NOT NULL DEFAULT '0' COMMENT '0=draft 1=published 2=Under Revision 3=Archived',
  `issued_by` varchar(36) DEFAULT NULL,
  `issuing_authority_id` varchar(36) DEFAULT NULL,
  `archived` tinyint(1) DEFAULT '0',
  `allow_download` tinyint(4) DEFAULT NULL,
  `allow_print` tinyint(4) DEFAULT NULL,
  `change_history` text,
  `cr_status` int(1) DEFAULT NULL,
  `mark_for_cr_update` int(1) DEFAULT '0',
  `temp_date_of_issue` date DEFAULT NULL,
  `temp_effective_from_date` date DEFAULT NULL,
  `cr_id` varchar(36) DEFAULT NULL,
  `old_cr_id` varchar(36) DEFAULT NULL,
  `parent_id` varchar(36) DEFAULT NULL,
  `parent_document_id` varchar(36) DEFAULT NULL,
  `linked_documents` text,
  `user_id` text,
  `cover_page` tinyint(1) DEFAULT '0',
  `page_orientation` tinyint(1) DEFAULT '0',
  `pdf_footer_id` varchar(36) DEFAULT NULL,
  `branches` text,
  `departments` text,
  `designations` text,
  `and_or_condition` tinyint(1) DEFAULT '0' COMMENT '0=And;1=OR',
  `editors` text,
  `system_table_id` varchar(36) DEFAULT NULL,
  `user_session_id` varchar(36) DEFAULT NULL,
  `publish` tinyint(1) DEFAULT '0' COMMENT '0=Un 1=Pub',
  `record_status` tinyint(1) DEFAULT '0' COMMENT '0=Un-locked, 1=Locked',
  `soft_delete` tinyint(1) DEFAULT '0' COMMENT '1=deleted',
  `branchid` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `departmentid` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `created_by` varchar(36) DEFAULT NULL COMMENT 'system defined automatically add',
  `created` datetime NOT NULL COMMENT 'system defined automatically add',
  `modified_by` varchar(36) DEFAULT NULL COMMENT 'system defined automatically add',
  `approved_by` varchar(36) DEFAULT NULL,
  `prepared_by` varchar(36) DEFAULT NULL,
  `modified` datetime NOT NULL COMMENT 'system defined automatically add',
  `company_id` varchar(36) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `qc_documents`
--

INSERT INTO `qc_documents` (`id`, `sr_no`, `name`, `title`, `file_key`, `version`, `versions`, `version_keys`, `file_status`, `last_saved`, `update_custom_table_document`, `file_type`, `schedule_id`, `data_update_type`, `data_type`, `data_file_type`, `add_records`, `qc_document_category_id`, `clause_id`, `standard_id`, `additional_clauses`, `document_number`, `reference_number`, `issue_number`, `date_of_next_issue`, `date_of_issue`, `effective_from_date`, `revision_number`, `date_created`, `update_version`, `date_of_review`, `revision_date`, `document_type`, `it_categories`, `document_status`, `issued_by`, `issuing_authority_id`, `archived`, `allow_download`, `allow_print`, `change_history`, `cr_status`, `mark_for_cr_update`, `temp_date_of_issue`, `temp_effective_from_date`, `cr_id`, `old_cr_id`, `parent_id`, `parent_document_id`, `linked_documents`, `user_id`, `cover_page`, `page_orientation`, `pdf_footer_id`, `branches`, `departments`, `designations`, `and_or_condition`, `editors`, `system_table_id`, `user_session_id`, `publish`, `record_status`, `soft_delete`, `branchid`, `departmentid`, `created_by`, `created`, `modified_by`, `approved_by`, `prepared_by`, `modified`, `company_id`) VALUES
('9b8144a1-6393-4955-bf86-b508fac030ba', 1, 'QMS Manual', 'qms_manual', '4118439914', NULL, NULL, NULL, 0, NULL, 0, 'docx', '56d1564b-0acc-48f6-9beb-03a7db1e6cf9', 0, 2, 0, 1, '584dbb5d-f880-44a3-8b0d-2e7ec20b8995', '33457b87-110e-4bba-bf46-24f34952d44b', '58511238-fba8-4db9-aad0-833fc20b8995', NULL, '001', NULL, '0', NULL, '2026-01-11', '2026-01-11', 0, '2026-01-11', 0, NULL, NULL, 6, 0, 1, '017838bb-2f06-4b2c-84c0-f171c6b3a756', '017838bb-2f06-4b2c-84c0-f171c6b3a756', 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, '-1', NULL, '[\"e6c97faa-f602-4f37-ae2d-7c95c11e1817\",\"a3d53ee2-adcd-4433-9363-87aed5be038c\",\"c185cfd1-e92c-4fcf-8f5d-9365a18ab81a\",\"6e98f2d1-879d-43a2-96b5-1e153a39ece6\",\"5e665aa1-4a3c-4baa-b6cc-09bfe007f8b1\",\"b3bac56f-2beb-4e94-92b4-c06bef2914f9\"]', 0, 0, NULL, '[\"43bcb91e-d10b-4d9c-9d62-edc6e97b6261\",\"042e9056-9f54-4ac2-9f31-57a2d9b5283a\",\"532eced0-adf3-4c79-b6a6-aab15ce1cb41\"]', '[\"da75b706-16d4-41f2-92f5-6f1a0b165057\",\"23827001-a8af-45b8-8a15-4cdc662b4591\",\"1c8bce1a-4817-41f9-b0ce-99bc368e2b81\",\"e72ddb09-4ea1-437a-8630-3d75888a1a57\",\"497abfe5-166d-4fc2-bee0-c7d5f045db93\",\"7ddec477-95ab-4773-85ef-0ad65e54e124\",\"9ff94383-bb91-48ee-aa64-0b2a2bf4d7ed\"]', '[\"4ffc0c15-be0f-4a07-a87d-b8496e932df9\",\"2cac2cc1-3bf7-44dd-b85b-c358b6f1a795\",\"25c669d8-34e7-4b6e-b378-421ac78c1794\",\"e56d3a44-fa03-4119-aeb8-995943a5c1de\",\"47ba35eb-ed81-41cc-8d3b-50e7250b6cd2\",\"946fe9c5-d7bd-4666-80db-2e13f255d47e\",\"b33534d0-7cec-4e65-8276-6d00cac66612\",\"6914b814-3d5f-46a0-84d8-76e1252724c4\"]', 0, '[\"e6c97faa-f602-4f37-ae2d-7c95c11e1817\",\"a3d53ee2-adcd-4433-9363-87aed5be038c\",\"c185cfd1-e92c-4fcf-8f5d-9365a18ab81a\",\"6e98f2d1-879d-43a2-96b5-1e153a39ece6\",\"5e665aa1-4a3c-4baa-b6cc-09bfe007f8b1\",\"b3bac56f-2beb-4e94-92b4-c06bef2914f9\"]', NULL, NULL, 1, 0, 0, '43bcb91e-d10b-4d9c-9d62-edc6e97b6261', 'da75b706-16d4-41f2-92f5-6f1a0b165057', 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', '2026-01-11 20:38:19', 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', '017838bb-2f06-4b2c-84c0-f171c6b3a756', '017838bb-2f06-4b2c-84c0-f171c6b3a756', '2026-01-11 20:38:19', 'update_comany_id'),
('c29a4cae-ee08-4dc8-9e30-ba6bce0005b4', 2, 'Audit Schedule', 'audit_schedule', '4118439914', NULL, NULL, NULL, 0, NULL, 0, 'docx', '52487033-b1a8-436f-b0a9-53a7q6c3268c', 2, 0, 0, 1, '584dbb5d-f880-44a3-8b0d-2e7ec20b8995', '33457b87-110e-4bba-bf46-24f34952d44b', '58511238-fba8-4db9-aad0-833fc20b8995', 'null', '002', NULL, '0', NULL, '2026-01-11', '2026-01-11', 0, '2026-01-11', 0, NULL, NULL, 6, 0, 1, '017838bb-2f06-4b2c-84c0-f171c6b3a756', '017838bb-2f06-4b2c-84c0-f171c6b3a756', 0, 0, 0, '', NULL, 0, NULL, NULL, '', NULL, '', '-1', NULL, '[\"e6c97faa-f602-4f37-ae2d-7c95c11e1817\",\"a3d53ee2-adcd-4433-9363-87aed5be038c\",\"c185cfd1-e92c-4fcf-8f5d-9365a18ab81a\",\"6e98f2d1-879d-43a2-96b5-1e153a39ece6\",\"5e665aa1-4a3c-4baa-b6cc-09bfe007f8b1\",\"b3bac56f-2beb-4e94-92b4-c06bef2914f9\"]', 0, 0, NULL, '[\"43bcb91e-d10b-4d9c-9d62-edc6e97b6261\",\"042e9056-9f54-4ac2-9f31-57a2d9b5283a\",\"532eced0-adf3-4c79-b6a6-aab15ce1cb41\"]', '[\"da75b706-16d4-41f2-92f5-6f1a0b165057\",\"23827001-a8af-45b8-8a15-4cdc662b4591\",\"1c8bce1a-4817-41f9-b0ce-99bc368e2b81\",\"e72ddb09-4ea1-437a-8630-3d75888a1a57\",\"497abfe5-166d-4fc2-bee0-c7d5f045db93\",\"7ddec477-95ab-4773-85ef-0ad65e54e124\",\"9ff94383-bb91-48ee-aa64-0b2a2bf4d7ed\"]', '[\"4ffc0c15-be0f-4a07-a87d-b8496e932df9\",\"2cac2cc1-3bf7-44dd-b85b-c358b6f1a795\",\"25c669d8-34e7-4b6e-b378-421ac78c1794\",\"e56d3a44-fa03-4119-aeb8-995943a5c1de\",\"47ba35eb-ed81-41cc-8d3b-50e7250b6cd2\",\"946fe9c5-d7bd-4666-80db-2e13f255d47e\",\"b33534d0-7cec-4e65-8276-6d00cac66612\",\"6914b814-3d5f-46a0-84d8-76e1252724c4\"]', 0, '[\"e6c97faa-f602-4f37-ae2d-7c95c11e1817\",\"a3d53ee2-adcd-4433-9363-87aed5be038c\",\"c185cfd1-e92c-4fcf-8f5d-9365a18ab81a\",\"6e98f2d1-879d-43a2-96b5-1e153a39ece6\",\"5e665aa1-4a3c-4baa-b6cc-09bfe007f8b1\",\"b3bac56f-2beb-4e94-92b4-c06bef2914f9\"]', NULL, '', 1, 0, 0, '43bcb91e-d10b-4d9c-9d62-edc6e97b6261', 'da75b706-16d4-41f2-92f5-6f1a0b165057', 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', '2026-01-11 20:38:19', 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', '017838bb-2f06-4b2c-84c0-f171c6b3a756', '017838bb-2f06-4b2c-84c0-f171c6b3a756', '2026-01-11 20:52:52', 'update_comany_id'),
('cdf45c35-4a25-4cc9-bec7-4692f40d27af', 3, 'Audit Checklist', 'audit_checklist', '4118439914', NULL, NULL, NULL, 0, NULL, 0, 'docx', '56d1564b-0acc-48f6-9beb-03a7db1e6cf9', 2, 0, 0, 1, '584dbb5d-f880-44a3-8b0d-2e7ec20b8995', '33457b87-110e-4bba-bf46-24f34952d44b', '58511238-fba8-4db9-aad0-833fc20b8995', 'null', '003', NULL, '0', NULL, '2026-01-11', '2026-01-11', 0, '2026-01-11', 0, NULL, NULL, 6, 0, 1, '017838bb-2f06-4b2c-84c0-f171c6b3a756', '017838bb-2f06-4b2c-84c0-f171c6b3a756', 0, 0, 0, '', NULL, 0, NULL, NULL, '', NULL, '', 'c29a4cae-ee08-4dc8-9e30-ba6bce0005b4', NULL, '[\"e6c97faa-f602-4f37-ae2d-7c95c11e1817\",\"a3d53ee2-adcd-4433-9363-87aed5be038c\",\"c185cfd1-e92c-4fcf-8f5d-9365a18ab81a\",\"6e98f2d1-879d-43a2-96b5-1e153a39ece6\",\"5e665aa1-4a3c-4baa-b6cc-09bfe007f8b1\",\"b3bac56f-2beb-4e94-92b4-c06bef2914f9\"]', 0, 0, NULL, '[\"43bcb91e-d10b-4d9c-9d62-edc6e97b6261\",\"042e9056-9f54-4ac2-9f31-57a2d9b5283a\",\"532eced0-adf3-4c79-b6a6-aab15ce1cb41\"]', '[\"da75b706-16d4-41f2-92f5-6f1a0b165057\",\"23827001-a8af-45b8-8a15-4cdc662b4591\",\"1c8bce1a-4817-41f9-b0ce-99bc368e2b81\",\"e72ddb09-4ea1-437a-8630-3d75888a1a57\",\"497abfe5-166d-4fc2-bee0-c7d5f045db93\",\"7ddec477-95ab-4773-85ef-0ad65e54e124\",\"9ff94383-bb91-48ee-aa64-0b2a2bf4d7ed\"]', '[\"4ffc0c15-be0f-4a07-a87d-b8496e932df9\",\"2cac2cc1-3bf7-44dd-b85b-c358b6f1a795\",\"25c669d8-34e7-4b6e-b378-421ac78c1794\",\"e56d3a44-fa03-4119-aeb8-995943a5c1de\",\"47ba35eb-ed81-41cc-8d3b-50e7250b6cd2\",\"946fe9c5-d7bd-4666-80db-2e13f255d47e\",\"b33534d0-7cec-4e65-8276-6d00cac66612\",\"6914b814-3d5f-46a0-84d8-76e1252724c4\"]', 0, '[\"e6c97faa-f602-4f37-ae2d-7c95c11e1817\",\"a3d53ee2-adcd-4433-9363-87aed5be038c\",\"c185cfd1-e92c-4fcf-8f5d-9365a18ab81a\",\"6e98f2d1-879d-43a2-96b5-1e153a39ece6\",\"5e665aa1-4a3c-4baa-b6cc-09bfe007f8b1\",\"b3bac56f-2beb-4e94-92b4-c06bef2914f9\"]', NULL, '', 1, 0, 0, '43bcb91e-d10b-4d9c-9d62-edc6e97b6261', 'da75b706-16d4-41f2-92f5-6f1a0b165057', 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', '2026-01-11 20:38:19', 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', '017838bb-2f06-4b2c-84c0-f171c6b3a756', '017838bb-2f06-4b2c-84c0-f171c6b3a756', '2026-01-11 20:53:31', 'update_comany_id'),
('5abc3b5b-d7e5-4252-8fe2-d21200c26556', 4, 'Audit Findings', 'audit_findings', '4118439914', NULL, NULL, NULL, 0, NULL, 0, 'docx', '56d1564b-0acc-48f6-9beb-03a7db1e6cf9', 2, 0, 0, 1, '584dbb5d-f880-44a3-8b0d-2e7ec20b8995', '33457b87-110e-4bba-bf46-24f34952d44b', '58511238-fba8-4db9-aad0-833fc20b8995', 'null', '004', NULL, '0', NULL, '2026-01-11', '2026-01-11', 0, '2026-01-11', 0, NULL, NULL, 6, 0, 1, '017838bb-2f06-4b2c-84c0-f171c6b3a756', '017838bb-2f06-4b2c-84c0-f171c6b3a756', 0, 0, 0, '', NULL, 0, NULL, NULL, '', NULL, '', 'c29a4cae-ee08-4dc8-9e30-ba6bce0005b4', NULL, '[\"e6c97faa-f602-4f37-ae2d-7c95c11e1817\",\"a3d53ee2-adcd-4433-9363-87aed5be038c\",\"c185cfd1-e92c-4fcf-8f5d-9365a18ab81a\",\"6e98f2d1-879d-43a2-96b5-1e153a39ece6\",\"5e665aa1-4a3c-4baa-b6cc-09bfe007f8b1\",\"b3bac56f-2beb-4e94-92b4-c06bef2914f9\"]', 0, 0, NULL, '[\"43bcb91e-d10b-4d9c-9d62-edc6e97b6261\",\"042e9056-9f54-4ac2-9f31-57a2d9b5283a\",\"532eced0-adf3-4c79-b6a6-aab15ce1cb41\"]', '[\"da75b706-16d4-41f2-92f5-6f1a0b165057\",\"23827001-a8af-45b8-8a15-4cdc662b4591\",\"1c8bce1a-4817-41f9-b0ce-99bc368e2b81\",\"e72ddb09-4ea1-437a-8630-3d75888a1a57\",\"497abfe5-166d-4fc2-bee0-c7d5f045db93\",\"7ddec477-95ab-4773-85ef-0ad65e54e124\",\"9ff94383-bb91-48ee-aa64-0b2a2bf4d7ed\"]', '[\"4ffc0c15-be0f-4a07-a87d-b8496e932df9\",\"2cac2cc1-3bf7-44dd-b85b-c358b6f1a795\",\"25c669d8-34e7-4b6e-b378-421ac78c1794\",\"e56d3a44-fa03-4119-aeb8-995943a5c1de\",\"47ba35eb-ed81-41cc-8d3b-50e7250b6cd2\",\"946fe9c5-d7bd-4666-80db-2e13f255d47e\",\"b33534d0-7cec-4e65-8276-6d00cac66612\",\"6914b814-3d5f-46a0-84d8-76e1252724c4\"]', 0, '[\"e6c97faa-f602-4f37-ae2d-7c95c11e1817\",\"a3d53ee2-adcd-4433-9363-87aed5be038c\",\"c185cfd1-e92c-4fcf-8f5d-9365a18ab81a\",\"6e98f2d1-879d-43a2-96b5-1e153a39ece6\",\"5e665aa1-4a3c-4baa-b6cc-09bfe007f8b1\",\"b3bac56f-2beb-4e94-92b4-c06bef2914f9\"]', NULL, '', 1, 0, 0, '43bcb91e-d10b-4d9c-9d62-edc6e97b6261', 'da75b706-16d4-41f2-92f5-6f1a0b165057', 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', '2026-01-11 20:38:19', 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', '017838bb-2f06-4b2c-84c0-f171c6b3a756', '017838bb-2f06-4b2c-84c0-f171c6b3a756', '2026-01-11 20:53:16', 'update_comany_id'),
('ea17ca9b-c5ec-4f0d-ad7c-14f534b5f32d', 5, 'MRM', 'mrm', '4118439914', NULL, NULL, NULL, 0, NULL, 0, 'docx', '52487027-260c-4196-8062-543bn6c3268c', 2, 0, 0, 1, '584dbb5d-f880-44a3-8b0d-2e7ec20b8995', '33457b87-110e-4bba-bf46-24f34952d44b', '58511238-fba8-4db9-aad0-833fc20b8995', NULL, '005', NULL, '0', NULL, '2026-01-11', '2026-01-11', 0, '2026-01-11', 0, NULL, NULL, 6, 0, 1, '017838bb-2f06-4b2c-84c0-f171c6b3a756', '017838bb-2f06-4b2c-84c0-f171c6b3a756', 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, '-1', NULL, '[\"e6c97faa-f602-4f37-ae2d-7c95c11e1817\",\"a3d53ee2-adcd-4433-9363-87aed5be038c\",\"c185cfd1-e92c-4fcf-8f5d-9365a18ab81a\",\"6e98f2d1-879d-43a2-96b5-1e153a39ece6\",\"5e665aa1-4a3c-4baa-b6cc-09bfe007f8b1\",\"b3bac56f-2beb-4e94-92b4-c06bef2914f9\"]', 0, 0, NULL, '[\"43bcb91e-d10b-4d9c-9d62-edc6e97b6261\",\"042e9056-9f54-4ac2-9f31-57a2d9b5283a\",\"532eced0-adf3-4c79-b6a6-aab15ce1cb41\"]', '[\"da75b706-16d4-41f2-92f5-6f1a0b165057\",\"23827001-a8af-45b8-8a15-4cdc662b4591\",\"1c8bce1a-4817-41f9-b0ce-99bc368e2b81\",\"e72ddb09-4ea1-437a-8630-3d75888a1a57\",\"497abfe5-166d-4fc2-bee0-c7d5f045db93\",\"7ddec477-95ab-4773-85ef-0ad65e54e124\",\"9ff94383-bb91-48ee-aa64-0b2a2bf4d7ed\"]', '[\"4ffc0c15-be0f-4a07-a87d-b8496e932df9\",\"2cac2cc1-3bf7-44dd-b85b-c358b6f1a795\",\"25c669d8-34e7-4b6e-b378-421ac78c1794\",\"e56d3a44-fa03-4119-aeb8-995943a5c1de\",\"47ba35eb-ed81-41cc-8d3b-50e7250b6cd2\",\"946fe9c5-d7bd-4666-80db-2e13f255d47e\",\"b33534d0-7cec-4e65-8276-6d00cac66612\",\"6914b814-3d5f-46a0-84d8-76e1252724c4\"]', 0, '[\"e6c97faa-f602-4f37-ae2d-7c95c11e1817\",\"a3d53ee2-adcd-4433-9363-87aed5be038c\",\"c185cfd1-e92c-4fcf-8f5d-9365a18ab81a\",\"6e98f2d1-879d-43a2-96b5-1e153a39ece6\",\"5e665aa1-4a3c-4baa-b6cc-09bfe007f8b1\",\"b3bac56f-2beb-4e94-92b4-c06bef2914f9\"]', NULL, NULL, 1, 0, 0, '43bcb91e-d10b-4d9c-9d62-edc6e97b6261', 'da75b706-16d4-41f2-92f5-6f1a0b165057', 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', '2026-01-11 20:38:19', 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', '017838bb-2f06-4b2c-84c0-f171c6b3a756', '017838bb-2f06-4b2c-84c0-f171c6b3a756', '2026-01-11 20:38:19', 'update_comany_id'),
('06d28e70-8ed6-424d-8a8c-deb73f5f5510', 6, 'Customer Details', 'customer_details', '4118439914', NULL, NULL, NULL, 0, NULL, 0, 'docx', '56d1564b-0acc-48f6-9beb-03a7db1e6cf9', 0, 1, 0, 1, '584dbb5d-f880-44a3-8b0d-2e7ec20b8995', '33457b87-110e-4bba-bf46-24f34952d44b', '58511238-fba8-4db9-aad0-833fc20b8995', NULL, '006', NULL, '0', NULL, '2026-01-11', '2026-01-11', 0, '2026-01-11', 0, NULL, NULL, 6, 0, 1, '017838bb-2f06-4b2c-84c0-f171c6b3a756', '017838bb-2f06-4b2c-84c0-f171c6b3a756', 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, '-1', NULL, '[\"e6c97faa-f602-4f37-ae2d-7c95c11e1817\",\"a3d53ee2-adcd-4433-9363-87aed5be038c\",\"c185cfd1-e92c-4fcf-8f5d-9365a18ab81a\",\"6e98f2d1-879d-43a2-96b5-1e153a39ece6\",\"5e665aa1-4a3c-4baa-b6cc-09bfe007f8b1\",\"b3bac56f-2beb-4e94-92b4-c06bef2914f9\"]', 0, 0, NULL, '[\"43bcb91e-d10b-4d9c-9d62-edc6e97b6261\",\"042e9056-9f54-4ac2-9f31-57a2d9b5283a\",\"532eced0-adf3-4c79-b6a6-aab15ce1cb41\"]', '[\"da75b706-16d4-41f2-92f5-6f1a0b165057\",\"23827001-a8af-45b8-8a15-4cdc662b4591\",\"1c8bce1a-4817-41f9-b0ce-99bc368e2b81\",\"e72ddb09-4ea1-437a-8630-3d75888a1a57\",\"497abfe5-166d-4fc2-bee0-c7d5f045db93\",\"7ddec477-95ab-4773-85ef-0ad65e54e124\",\"9ff94383-bb91-48ee-aa64-0b2a2bf4d7ed\"]', '[\"4ffc0c15-be0f-4a07-a87d-b8496e932df9\",\"2cac2cc1-3bf7-44dd-b85b-c358b6f1a795\",\"25c669d8-34e7-4b6e-b378-421ac78c1794\",\"e56d3a44-fa03-4119-aeb8-995943a5c1de\",\"47ba35eb-ed81-41cc-8d3b-50e7250b6cd2\",\"946fe9c5-d7bd-4666-80db-2e13f255d47e\",\"b33534d0-7cec-4e65-8276-6d00cac66612\",\"6914b814-3d5f-46a0-84d8-76e1252724c4\"]', 0, '[\"e6c97faa-f602-4f37-ae2d-7c95c11e1817\",\"a3d53ee2-adcd-4433-9363-87aed5be038c\",\"c185cfd1-e92c-4fcf-8f5d-9365a18ab81a\",\"6e98f2d1-879d-43a2-96b5-1e153a39ece6\",\"5e665aa1-4a3c-4baa-b6cc-09bfe007f8b1\",\"b3bac56f-2beb-4e94-92b4-c06bef2914f9\"]', NULL, NULL, 1, 0, 0, '43bcb91e-d10b-4d9c-9d62-edc6e97b6261', 'da75b706-16d4-41f2-92f5-6f1a0b165057', 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', '2026-01-11 20:38:19', 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', '017838bb-2f06-4b2c-84c0-f171c6b3a756', '017838bb-2f06-4b2c-84c0-f171c6b3a756', '2026-01-11 20:38:19', 'update_comany_id'),
('888fb842-bc2d-41c4-b17e-7026dd7643a5', 7, 'Customer Complaints', 'customer_complaints', '4118439914', NULL, NULL, NULL, 0, NULL, 0, 'docx', '56d1564b-0acc-48f6-9beb-03a7db1e6cf9', 0, 1, 0, 1, '584dbb5d-f880-44a3-8b0d-2e7ec20b8995', '33457b87-110e-4bba-bf46-24f34952d44b', '58511238-fba8-4db9-aad0-833fc20b8995', NULL, '007', NULL, '0', NULL, '2026-01-11', '2026-01-11', 0, '2026-01-11', 0, NULL, NULL, 6, 0, 1, '017838bb-2f06-4b2c-84c0-f171c6b3a756', '017838bb-2f06-4b2c-84c0-f171c6b3a756', 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, '-1', NULL, '[\"e6c97faa-f602-4f37-ae2d-7c95c11e1817\",\"a3d53ee2-adcd-4433-9363-87aed5be038c\",\"c185cfd1-e92c-4fcf-8f5d-9365a18ab81a\",\"6e98f2d1-879d-43a2-96b5-1e153a39ece6\",\"5e665aa1-4a3c-4baa-b6cc-09bfe007f8b1\",\"b3bac56f-2beb-4e94-92b4-c06bef2914f9\"]', 0, 0, NULL, '[\"43bcb91e-d10b-4d9c-9d62-edc6e97b6261\",\"042e9056-9f54-4ac2-9f31-57a2d9b5283a\",\"532eced0-adf3-4c79-b6a6-aab15ce1cb41\"]', '[\"da75b706-16d4-41f2-92f5-6f1a0b165057\",\"23827001-a8af-45b8-8a15-4cdc662b4591\",\"1c8bce1a-4817-41f9-b0ce-99bc368e2b81\",\"e72ddb09-4ea1-437a-8630-3d75888a1a57\",\"497abfe5-166d-4fc2-bee0-c7d5f045db93\",\"7ddec477-95ab-4773-85ef-0ad65e54e124\",\"9ff94383-bb91-48ee-aa64-0b2a2bf4d7ed\"]', '[\"4ffc0c15-be0f-4a07-a87d-b8496e932df9\",\"2cac2cc1-3bf7-44dd-b85b-c358b6f1a795\",\"25c669d8-34e7-4b6e-b378-421ac78c1794\",\"e56d3a44-fa03-4119-aeb8-995943a5c1de\",\"47ba35eb-ed81-41cc-8d3b-50e7250b6cd2\",\"946fe9c5-d7bd-4666-80db-2e13f255d47e\",\"b33534d0-7cec-4e65-8276-6d00cac66612\",\"6914b814-3d5f-46a0-84d8-76e1252724c4\"]', 0, '[\"e6c97faa-f602-4f37-ae2d-7c95c11e1817\",\"a3d53ee2-adcd-4433-9363-87aed5be038c\",\"c185cfd1-e92c-4fcf-8f5d-9365a18ab81a\",\"6e98f2d1-879d-43a2-96b5-1e153a39ece6\",\"5e665aa1-4a3c-4baa-b6cc-09bfe007f8b1\",\"b3bac56f-2beb-4e94-92b4-c06bef2914f9\"]', NULL, NULL, 1, 0, 0, '43bcb91e-d10b-4d9c-9d62-edc6e97b6261', 'da75b706-16d4-41f2-92f5-6f1a0b165057', 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', '2026-01-11 20:38:19', 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', '017838bb-2f06-4b2c-84c0-f171c6b3a756', '017838bb-2f06-4b2c-84c0-f171c6b3a756', '2026-01-11 20:38:19', 'update_comany_id'),
('e53215e1-ccea-4017-8fee-bd56f4b9975d', 8, 'Supplier Details', 'supplier_details', '4118439914', NULL, NULL, NULL, 0, NULL, 0, 'docx', '56d1564b-0acc-48f6-9beb-03a7db1e6cf9', 0, 1, 0, 1, '584dbb5d-f880-44a3-8b0d-2e7ec20b8995', '33457b87-110e-4bba-bf46-24f34952d44b', '58511238-fba8-4db9-aad0-833fc20b8995', NULL, '008', NULL, '0', NULL, '2026-01-11', '2026-01-11', 0, '2026-01-11', 0, NULL, NULL, 6, 0, 1, '017838bb-2f06-4b2c-84c0-f171c6b3a756', '017838bb-2f06-4b2c-84c0-f171c6b3a756', 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, '-1', NULL, '[\"e6c97faa-f602-4f37-ae2d-7c95c11e1817\",\"a3d53ee2-adcd-4433-9363-87aed5be038c\",\"c185cfd1-e92c-4fcf-8f5d-9365a18ab81a\",\"6e98f2d1-879d-43a2-96b5-1e153a39ece6\",\"5e665aa1-4a3c-4baa-b6cc-09bfe007f8b1\",\"b3bac56f-2beb-4e94-92b4-c06bef2914f9\"]', 0, 0, NULL, '[\"43bcb91e-d10b-4d9c-9d62-edc6e97b6261\",\"042e9056-9f54-4ac2-9f31-57a2d9b5283a\",\"532eced0-adf3-4c79-b6a6-aab15ce1cb41\"]', '[\"da75b706-16d4-41f2-92f5-6f1a0b165057\",\"23827001-a8af-45b8-8a15-4cdc662b4591\",\"1c8bce1a-4817-41f9-b0ce-99bc368e2b81\",\"e72ddb09-4ea1-437a-8630-3d75888a1a57\",\"497abfe5-166d-4fc2-bee0-c7d5f045db93\",\"7ddec477-95ab-4773-85ef-0ad65e54e124\",\"9ff94383-bb91-48ee-aa64-0b2a2bf4d7ed\"]', '[\"4ffc0c15-be0f-4a07-a87d-b8496e932df9\",\"2cac2cc1-3bf7-44dd-b85b-c358b6f1a795\",\"25c669d8-34e7-4b6e-b378-421ac78c1794\",\"e56d3a44-fa03-4119-aeb8-995943a5c1de\",\"47ba35eb-ed81-41cc-8d3b-50e7250b6cd2\",\"946fe9c5-d7bd-4666-80db-2e13f255d47e\",\"b33534d0-7cec-4e65-8276-6d00cac66612\",\"6914b814-3d5f-46a0-84d8-76e1252724c4\"]', 0, '[\"e6c97faa-f602-4f37-ae2d-7c95c11e1817\",\"a3d53ee2-adcd-4433-9363-87aed5be038c\",\"c185cfd1-e92c-4fcf-8f5d-9365a18ab81a\",\"6e98f2d1-879d-43a2-96b5-1e153a39ece6\",\"5e665aa1-4a3c-4baa-b6cc-09bfe007f8b1\",\"b3bac56f-2beb-4e94-92b4-c06bef2914f9\"]', NULL, NULL, 1, 0, 0, '43bcb91e-d10b-4d9c-9d62-edc6e97b6261', 'da75b706-16d4-41f2-92f5-6f1a0b165057', 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', '2026-01-11 20:38:19', 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', '017838bb-2f06-4b2c-84c0-f171c6b3a756', '017838bb-2f06-4b2c-84c0-f171c6b3a756', '2026-01-11 20:38:19', 'update_comany_id'),
('5eea859b-aab2-4c26-8af3-abd16bbd02df', 9, 'Device/ Equipment', 'device_equipment', '4118439914', NULL, NULL, NULL, 0, NULL, 0, 'docx', '56d1564b-0acc-48f6-9beb-03a7db1e6cf9', 0, 1, 0, 1, '584dbb5d-f880-44a3-8b0d-2e7ec20b8995', '33457b87-110e-4bba-bf46-24f34952d44b', '58511238-fba8-4db9-aad0-833fc20b8995', NULL, '009', NULL, '0', NULL, '2026-01-11', '2026-01-11', 0, '2026-01-11', 0, NULL, NULL, 6, 0, 1, '017838bb-2f06-4b2c-84c0-f171c6b3a756', '017838bb-2f06-4b2c-84c0-f171c6b3a756', 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, '-1', NULL, '[\"e6c97faa-f602-4f37-ae2d-7c95c11e1817\",\"a3d53ee2-adcd-4433-9363-87aed5be038c\",\"c185cfd1-e92c-4fcf-8f5d-9365a18ab81a\",\"6e98f2d1-879d-43a2-96b5-1e153a39ece6\",\"5e665aa1-4a3c-4baa-b6cc-09bfe007f8b1\",\"b3bac56f-2beb-4e94-92b4-c06bef2914f9\"]', 0, 0, NULL, '[\"43bcb91e-d10b-4d9c-9d62-edc6e97b6261\",\"042e9056-9f54-4ac2-9f31-57a2d9b5283a\",\"532eced0-adf3-4c79-b6a6-aab15ce1cb41\"]', '[\"da75b706-16d4-41f2-92f5-6f1a0b165057\",\"23827001-a8af-45b8-8a15-4cdc662b4591\",\"1c8bce1a-4817-41f9-b0ce-99bc368e2b81\",\"e72ddb09-4ea1-437a-8630-3d75888a1a57\",\"497abfe5-166d-4fc2-bee0-c7d5f045db93\",\"7ddec477-95ab-4773-85ef-0ad65e54e124\",\"9ff94383-bb91-48ee-aa64-0b2a2bf4d7ed\"]', '[\"4ffc0c15-be0f-4a07-a87d-b8496e932df9\",\"2cac2cc1-3bf7-44dd-b85b-c358b6f1a795\",\"25c669d8-34e7-4b6e-b378-421ac78c1794\",\"e56d3a44-fa03-4119-aeb8-995943a5c1de\",\"47ba35eb-ed81-41cc-8d3b-50e7250b6cd2\",\"946fe9c5-d7bd-4666-80db-2e13f255d47e\",\"b33534d0-7cec-4e65-8276-6d00cac66612\",\"6914b814-3d5f-46a0-84d8-76e1252724c4\"]', 0, '[\"e6c97faa-f602-4f37-ae2d-7c95c11e1817\",\"a3d53ee2-adcd-4433-9363-87aed5be038c\",\"c185cfd1-e92c-4fcf-8f5d-9365a18ab81a\",\"6e98f2d1-879d-43a2-96b5-1e153a39ece6\",\"5e665aa1-4a3c-4baa-b6cc-09bfe007f8b1\",\"b3bac56f-2beb-4e94-92b4-c06bef2914f9\"]', NULL, NULL, 1, 0, 0, '43bcb91e-d10b-4d9c-9d62-edc6e97b6261', 'da75b706-16d4-41f2-92f5-6f1a0b165057', 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', '2026-01-11 20:38:19', 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', '017838bb-2f06-4b2c-84c0-f171c6b3a756', '017838bb-2f06-4b2c-84c0-f171c6b3a756', '2026-01-11 20:38:19', 'update_comany_id'),
('a9d9fd99-157e-4028-a223-0d72b6d241c8', 10, 'Calibration', 'calibration', '4118439914', NULL, NULL, NULL, 0, NULL, 0, 'docx', '52487027-260c-4196-8062-543bn6c3268c', 1, 0, 0, 1, '584dbb5d-f880-44a3-8b0d-2e7ec20b8995', '33457b87-110e-4bba-bf46-24f34952d44b', '58511238-fba8-4db9-aad0-833fc20b8995', NULL, '010', NULL, '0', NULL, '2026-01-11', '2026-01-11', 0, '2026-01-11', 0, NULL, NULL, 6, 0, 1, '017838bb-2f06-4b2c-84c0-f171c6b3a756', '017838bb-2f06-4b2c-84c0-f171c6b3a756', 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, '-1', NULL, '[\"e6c97faa-f602-4f37-ae2d-7c95c11e1817\",\"a3d53ee2-adcd-4433-9363-87aed5be038c\",\"c185cfd1-e92c-4fcf-8f5d-9365a18ab81a\",\"6e98f2d1-879d-43a2-96b5-1e153a39ece6\",\"5e665aa1-4a3c-4baa-b6cc-09bfe007f8b1\",\"b3bac56f-2beb-4e94-92b4-c06bef2914f9\"]', 0, 0, NULL, '[\"43bcb91e-d10b-4d9c-9d62-edc6e97b6261\",\"042e9056-9f54-4ac2-9f31-57a2d9b5283a\",\"532eced0-adf3-4c79-b6a6-aab15ce1cb41\"]', '[\"da75b706-16d4-41f2-92f5-6f1a0b165057\",\"23827001-a8af-45b8-8a15-4cdc662b4591\",\"1c8bce1a-4817-41f9-b0ce-99bc368e2b81\",\"e72ddb09-4ea1-437a-8630-3d75888a1a57\",\"497abfe5-166d-4fc2-bee0-c7d5f045db93\",\"7ddec477-95ab-4773-85ef-0ad65e54e124\",\"9ff94383-bb91-48ee-aa64-0b2a2bf4d7ed\"]', '[\"4ffc0c15-be0f-4a07-a87d-b8496e932df9\",\"2cac2cc1-3bf7-44dd-b85b-c358b6f1a795\",\"25c669d8-34e7-4b6e-b378-421ac78c1794\",\"e56d3a44-fa03-4119-aeb8-995943a5c1de\",\"47ba35eb-ed81-41cc-8d3b-50e7250b6cd2\",\"946fe9c5-d7bd-4666-80db-2e13f255d47e\",\"b33534d0-7cec-4e65-8276-6d00cac66612\",\"6914b814-3d5f-46a0-84d8-76e1252724c4\"]', 0, '[\"e6c97faa-f602-4f37-ae2d-7c95c11e1817\",\"a3d53ee2-adcd-4433-9363-87aed5be038c\",\"c185cfd1-e92c-4fcf-8f5d-9365a18ab81a\",\"6e98f2d1-879d-43a2-96b5-1e153a39ece6\",\"5e665aa1-4a3c-4baa-b6cc-09bfe007f8b1\",\"b3bac56f-2beb-4e94-92b4-c06bef2914f9\"]', NULL, NULL, 1, 0, 0, '43bcb91e-d10b-4d9c-9d62-edc6e97b6261', 'da75b706-16d4-41f2-92f5-6f1a0b165057', 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', '2026-01-11 20:38:19', 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', '017838bb-2f06-4b2c-84c0-f171c6b3a756', '017838bb-2f06-4b2c-84c0-f171c6b3a756', '2026-01-11 20:38:19', 'update_comany_id');

-- --------------------------------------------------------

--
-- Table structure for table `qc_document_categories`
--

CREATE TABLE `qc_document_categories` (
  `id` varchar(36) NOT NULL,
  `sr_no` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `short_name` varchar(10) DEFAULT NULL,
  `standard_id` varchar(36) DEFAULT NULL,
  `parent_id` varchar(36) DEFAULT '-1',
  `publish` tinyint(1) DEFAULT '0' COMMENT '0=Un 1=Pub',
  `record_status` tinyint(1) DEFAULT '0' COMMENT '0=Un-locked, 1=Locked',
  `status_user_id` varchar(36) DEFAULT NULL,
  `soft_delete` tinyint(1) DEFAULT '0' COMMENT '1=deleted',
  `branchid` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `departmentid` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `created_by` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `created` datetime NOT NULL COMMENT 'system defined automatically add',
  `modified_by` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `approved_by` varchar(36) DEFAULT NULL,
  `prepared_by` varchar(36) DEFAULT NULL,
  `modified` datetime NOT NULL COMMENT 'system defined automatically add',
  `system_table_id` varchar(36) DEFAULT '0',
  `company_id` varchar(36) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `qc_document_categories`
--

INSERT INTO `qc_document_categories` (`id`, `sr_no`, `name`, `short_name`, `standard_id`, `parent_id`, `publish`, `record_status`, `status_user_id`, `soft_delete`, `branchid`, `departmentid`, `created_by`, `created`, `modified_by`, `approved_by`, `prepared_by`, `modified`, `system_table_id`, `company_id`) VALUES
('584dba0b-26f4-40e3-840d-68a8c20b8995', 1, 'Level 1 - The Quality Manual', NULL, '58511238-fba8-4db9-aad0-833fc20b8995', NULL, 1, 0, NULL, 0, '56044715-c550-48f7-9b3e-03e1db1e6cf9', '523a0abb-21e0-4b44-a219-6142c6c32681', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2016-12-12 02:11:47', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2017-04-23 13:58:21', NULL, 'update_comany_id'),
('584dba22-189c-40fa-8ed4-0259c20b8995', 2, 'Level 2: Quality Manual - approach and responsibility', NULL, '58511238-fba8-4db9-aad0-833fc20b8995', NULL, 1, 0, NULL, 0, '56044715-c550-48f7-9b3e-03e1db1e6cf9', '523a0abb-21e0-4b44-a219-6142c6c32681', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2016-12-12 02:12:10', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2017-04-23 13:58:30', NULL, 'update_comany_id'),
('584dba30-95e4-4bd2-bc3e-6897c20b8995', 3, 'Level 3: Procedures - methods (Who, What, Where and When)', NULL, '58511238-fba8-4db9-aad0-833fc20b8995', NULL, 1, 0, NULL, 0, '56044715-c550-48f7-9b3e-03e1db1e6cf9', '523a0abb-21e0-4b44-a219-6142c6c32681', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2016-12-12 02:12:24', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2017-04-23 13:58:40', NULL, 'update_comany_id'),
('584dbb51-3640-4357-b0a6-2e58c20b8995', 4, 'Level 4: Work Instructions - description of processes (How)', NULL, '58511238-fba8-4db9-aad0-833fc20b8995', NULL, 1, 0, NULL, 0, '56044715-c550-48f7-9b3e-03e1db1e6cf9', '523a0abb-21e0-4b44-a219-6142c6c32681', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2016-12-12 02:17:13', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2017-04-23 13:58:48', NULL, 'update_comany_id'),
('584dbb5d-f880-44a3-8b0d-2e7ec20b8995', 5, 'Level 5: Forms, Data and Records - evidence of conformance', 'F', '58511238-fba8-4db9-aad0-833fc20b8995', '-1', 1, 0, NULL, 0, '56044726-2adc-4ab0-a1a1-03e4db1e6cf9', '5b1fb6ec-2488-445f-bf26-4fe70a8e0005', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2016-12-12 02:17:25', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2021-10-03 15:43:37', NULL, 'update_comany_id');

-- --------------------------------------------------------

--
-- Table structure for table `records`
--

CREATE TABLE `records` (
  `id` varchar(36) NOT NULL,
  `sr_no` int(11) NOT NULL,
  `title` varchar(255) NOT NULL DEFAULT 'Record Title',
  `qc_document_id` varchar(36) NOT NULL,
  `file_key` varchar(50) DEFAULT NULL,
  `file_type` varchar(5) DEFAULT NULL,
  `schedule_id` varchar(36) DEFAULT NULL,
  `date_time` datetime DEFAULT NULL,
  `comments` text,
  `publish` tinyint(1) DEFAULT '0' COMMENT '0=Un 1=Pub',
  `record_status` tinyint(1) DEFAULT '0' COMMENT '0=Un-locked, 1=Locked',
  `status_user_id` varchar(36) DEFAULT NULL,
  `soft_delete` tinyint(1) DEFAULT '0' COMMENT '1=deleted',
  `branchid` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `departmentid` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `created_by` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `created` datetime NOT NULL COMMENT 'system defined automatically add',
  `modified_by` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `approved_by` varchar(36) DEFAULT NULL,
  `prepared_by` varchar(36) DEFAULT NULL,
  `modified` datetime NOT NULL COMMENT 'system defined automatically add',
  `system_table_id` varchar(36) DEFAULT '0',
  `company_id` varchar(36) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `record_locks`
--

CREATE TABLE `record_locks` (
  `id` varchar(36) NOT NULL,
  `sr_no` int(11) NOT NULL,
  `table_id` varchar(36) NOT NULL,
  `lock_table_id` varchar(255) NOT NULL,
  `table_field` varchar(250) NOT NULL,
  `condition` varchar(10) NOT NULL,
  `csvoption` int(1) DEFAULT '0',
  `action` varchar(10) NOT NULL,
  `message` text,
  `publish` tinyint(1) DEFAULT '0' COMMENT '0=Un 1=Pub',
  `record_status` tinyint(1) DEFAULT '0' COMMENT '0=Un-locked, 1=Locked',
  `status_user_id` varchar(36) DEFAULT NULL,
  `soft_delete` tinyint(1) DEFAULT '0' COMMENT '1=deleted',
  `branchid` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `departmentid` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `created_by` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `created` datetime NOT NULL COMMENT 'system defined automatically add',
  `modified_by` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `approved_by` varchar(36) DEFAULT NULL,
  `prepared_by` varchar(36) DEFAULT NULL,
  `modified` datetime NOT NULL COMMENT 'system defined automatically add',
  `system_table_id` varchar(36) DEFAULT '0',
  `company_id` varchar(36) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `schedules`
--

CREATE TABLE `schedules` (
  `id` varchar(36) NOT NULL,
  `sr_no` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `publish` tinyint(1) DEFAULT '0' COMMENT '0=Un 1=Pub',
  `record_status` tinyint(1) DEFAULT '0' COMMENT '0=Un-locked, 1=Locked',
  `status_user_id` varchar(36) DEFAULT NULL,
  `soft_delete` tinyint(1) DEFAULT '0' COMMENT '1=deleted',
  `branchid` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `departmentid` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `created_by` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `created` datetime NOT NULL COMMENT 'system defined automatically add',
  `modified_by` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `approved_by` varchar(36) DEFAULT NULL,
  `prepared_by` varchar(36) DEFAULT NULL,
  `modified` datetime NOT NULL COMMENT 'system defined automatically add',
  `system_table_id` varchar(36) DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `schedules`
--

INSERT INTO `schedules` (`id`, `sr_no`, `name`, `publish`, `record_status`, `status_user_id`, `soft_delete`, `branchid`, `departmentid`, `created_by`, `created`, `modified_by`, `approved_by`, `prepared_by`, `modified`, `system_table_id`) VALUES
('52487014-1448-45ae-82c3-4f1fc6c3268c', 1, 'Daily', 1, 0, NULL, 0, '522e4490-ff60-4990-8ca2-8a04c6c3268c', '522ee01c-4c78-437c-bffb-0311c6c3268c', '54e5d056-199b-11e3-9f46-c709d410d2ec', '2015-09-24 18:52:48', '54e5d056-199b-11e3-9f46-c709d410d2ec', NULL, NULL, '2015-09-24 18:52:48', '522e4411-7e44-4c41-9c1a-84a2c6c3268c'),
('5248701d-1390-4782-9990-4f1fc6c3268c', 2, 'Weekly', 1, 0, NULL, 0, '522e4490-ff60-4990-8ca2-8a04c6c3268c', '522ee01c-4c78-437c-bffb-0311c6c3268c', '54e5d056-199b-11e3-9f46-c709d410d2ec', '2015-09-24 18:52:48', '54e5d056-199b-11e3-9f46-c709d410d2ec', NULL, NULL, '2015-09-24 18:52:48', '522e4411-7e44-4c41-9c1a-84a2c6c3268c'),
('52487027-260c-4196-8062-543bn6c3268c', 4, 'Monthly', 1, 0, NULL, 0, '522e4490-ff60-4990-8ca2-8a04c6c3268c', '522ee01c-4c78-437c-bffb-0311c6c3268c', '54e5d056-199b-11e3-9f46-c709d410d2ec', '2015-09-24 18:52:48', '530c2de0-b334-4661-a55c-383db6329416', NULL, NULL, '2015-09-24 18:52:48', '5297b2e7-d99c-464b-952b-2d8f0a000005'),
('52487033-b1a8-436f-b0a9-53a7q6c3268c', 5, 'Quarterly', 1, 0, NULL, 0, '522e4490-ff60-4990-8ca2-8a04c6c3268c', '522ee01c-4c78-437c-bffb-0311c6c3268c', '54e5d056-199b-11e3-9f46-c709d410d2ec', '2015-09-24 18:52:48', '54e5d056-199b-11e3-9f46-c709d410d2ec', NULL, NULL, '2015-09-24 18:52:48', '522e4411-7e44-4c41-9c1a-84a2c6c3268c'),
('530df9f4-fff8-454e-aa24-71f5b6329416', 7, 'Yearly', 1, 0, NULL, 0, '530c2ddf-4668-4a02-96ba-383db6329416', '522e3da7-8e48-4be2-ab9d-8545c6c3268c', '530c2de0-b334-4661-a55c-383db6329416', '2015-09-24 18:52:48', '530c2de0-b334-4661-a55c-383db6329416', NULL, NULL, '2015-09-24 18:52:48', '5297b2e7-d99c-464b-952b-2d8f0a000005'),
('56d15631-8f34-40bb-a577-03a2db1e6cf9', 8, 'Half-Yearly', 1, 0, NULL, 0, '56044715-c550-48f7-9b3e-03e1db1e6cf9', '523a0abb-21e0-4b44-a219-6142c6c32681', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2016-02-27 13:24:25', '56044715-819c-4246-8ec3-03e1db1e6cf9', '56044715-6bb8-49bd-85f2-03e1db1e6cf9', '56044715-6bb8-49bd-85f2-03e1db1e6cf9', '2016-02-27 13:24:25', '5297b2e7-d99c-464b-952b-2d8f0a000005'),
('56d1564b-0acc-48f6-9beb-03a7db1e6cf9', 9, 'None', 1, 0, NULL, 0, '56044715-c550-48f7-9b3e-03e1db1e6cf9', '523a0abb-21e0-4b44-a219-6142c6c32681', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2016-02-27 13:24:51', '56044715-819c-4246-8ec3-03e1db1e6cf9', '56044715-6bb8-49bd-85f2-03e1db1e6cf9', '56044715-6bb8-49bd-85f2-03e1db1e6cf9', '2016-02-27 13:24:51', '5297b2e7-d99c-464b-952b-2d8f0a000005');

-- --------------------------------------------------------

--
-- Table structure for table `standards`
--

CREATE TABLE `standards` (
  `id` varchar(36) NOT NULL,
  `sr_no` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `short_name` varchar(10) DEFAULT NULL,
  `details` text,
  `publish` tinyint(1) DEFAULT '0' COMMENT '0=Un 1=Pub',
  `record_status` tinyint(1) DEFAULT '0' COMMENT '0=Un-locked, 1=Locked',
  `status_user_id` varchar(36) DEFAULT NULL,
  `soft_delete` tinyint(1) DEFAULT '0' COMMENT '1=deleted',
  `branchid` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `departmentid` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `created_by` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `created` datetime NOT NULL COMMENT 'system defined automatically add',
  `modified_by` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `approved_by` varchar(36) DEFAULT NULL,
  `prepared_by` varchar(36) DEFAULT NULL,
  `modified` datetime NOT NULL COMMENT 'system defined automatically add',
  `system_table_id` varchar(36) DEFAULT '0',
  `division_id` varchar(36) DEFAULT '0',
  `company_id` varchar(36) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `standards`
--

INSERT INTO `standards` (`id`, `sr_no`, `name`, `details`, `publish`, `record_status`, `status_user_id`, `soft_delete`, `branchid`, `departmentid`, `created_by`, `created`, `modified_by`, `approved_by`, `prepared_by`, `modified`, `system_table_id`, `division_id`, `company_id`) VALUES
('58511238-fba8-4db9-aad0-833fc20b8995', 1, '2015', 'ISO 2008-2015', 1, 0, NULL, 0, '56044726-2adc-4ab0-a1a1-03e4db1e6cf9', '5b1fb6ec-2488-445f-bf26-4fe70a8e0005', '56044715-819c-4246-8ec3-03e1db1e6cf9', '2021-10-03 14:46:06', '56044715-819c-4246-8ec3-03e1db1e6cf9', NULL, NULL, '2021-10-03 15:32:22', NULL, '0', 'update_comany_id');

-- --------------------------------------------------------

--
-- Table structure for table `system_tables`
--

CREATE TABLE `system_tables` (
  `id` varchar(36) NOT NULL,
  `sr_no` int(11) NOT NULL,
  `name` varchar(250) NOT NULL,
  `system_name` varchar(250) NOT NULL,
  `iso_section` tinytext,
  `evidence_required` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0=no 1=yes',
  `approvals_required` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0=no 1=yes',
  `reports` tinyint(1) DEFAULT NULL,
  `publish` tinyint(1) DEFAULT '0' COMMENT '0=Un 1=Pub',
  `record_status` tinyint(1) DEFAULT '0' COMMENT '0=Un-locked, 1=Locked',
  `status_user_id` varchar(36) DEFAULT NULL,
  `soft_delete` tinyint(1) DEFAULT '0' COMMENT '1=deleted',
  `branchid` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `departmentid` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `created_by` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `created` datetime NOT NULL COMMENT 'system defined automatically add',
  `modified_by` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `approved_by` varchar(36) DEFAULT NULL,
  `prepared_by` varchar(36) DEFAULT NULL,
  `modified` datetime NOT NULL COMMENT 'system defined automatically add',
  `division_id` varchar(36) DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_audit_catetory_v0s`
--

CREATE TABLE `tbl_audit_catetory_v0s` (
  `id` varchar(36) NOT NULL,
  `sr_no` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `custom_table_id` varchar(36) DEFAULT NULL,
  `publish` tinyint(1) DEFAULT '1' COMMENT '0=Un 1=Pub',
  `record_status` tinyint(1) DEFAULT '0' COMMENT '0=Un-locked, 1=Locked',
  `status_user_id` varchar(36) DEFAULT NULL,
  `created_by` varchar(36) NOT NULL,
  `created` datetime NOT NULL,
  `modified_by` varchar(36) NOT NULL,
  `approved_by` varchar(36) DEFAULT NULL,
  `prepared_by` varchar(36) DEFAULT NULL,
  `modified` datetime NOT NULL,
  `soft_delete` tinyint(1) NOT NULL DEFAULT '0',
  `branchid` varchar(36) DEFAULT NULL,
  `departmentid` varchar(36) DEFAULT NULL,
  `company_id` varchar(36) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `tbl_audit_catetory_v0s`
--

INSERT INTO `tbl_audit_catetory_v0s` (`id`, `sr_no`, `name`, `custom_table_id`, `publish`, `record_status`, `status_user_id`, `created_by`, `created`, `modified_by`, `approved_by`, `prepared_by`, `modified`, `soft_delete`, `branchid`, `departmentid`, `company_id`) VALUES
('1ae3e1dc-db60-4fe0-85c4-674bf42c7e8a', 1, 'General Audit', NULL, 1, 0, NULL, 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', '2026-01-11 21:12:20', 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', NULL, '017838bb-2f06-4b2c-84c0-f171c6b3a756', '2026-01-11 21:12:20', 0, '43bcb91e-d10b-4d9c-9d62-edc6e97b6261', 'da75b706-16d4-41f2-92f5-6f1a0b165057', 'update_comany_id'),
('f288a4b6-4fff-4b69-a682-8aa2074afcb5', 2, 'Process Audit', NULL, 1, 0, NULL, 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', '2026-01-11 21:12:39', 'e6c97faa-f602-4f37-ae2d-7c95c11e1817', NULL, '017838bb-2f06-4b2c-84c0-f171c6b3a756', '2026-01-11 21:12:39', 0, '43bcb91e-d10b-4d9c-9d62-edc6e97b6261', 'da75b706-16d4-41f2-92f5-6f1a0b165057', 'update_comany_id');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_audit_checklist_0_v0s`
--

CREATE TABLE `tbl_audit_checklist_0_v0s` (
  `id` varchar(36) NOT NULL,
  `sr_no` int(11) NOT NULL,
  `comments` text,
  `added_by` varchar(36) DEFAULT NULL,
  `date_added` date DEFAULT NULL,
  `checklist_title` varchar(255) NOT NULL,
  `qc_document_id` varchar(36) NOT NULL DEFAULT 'cdf45c35-4a25-4cc9-bec7-4692f40d27af',
  `custom_table_id` varchar(36) NOT NULL DEFAULT 'b2636904-8dff-4356-a06a-4887341c8def',
  `file_id` varchar(36) DEFAULT NULL,
  `file_key` varchar(50) DEFAULT NULL,
  `parent_id` varchar(36) DEFAULT NULL,
  `additional_files` text,
  `publish` tinyint(1) DEFAULT '1' COMMENT '0=Un 1=Pub',
  `record_status` tinyint(1) DEFAULT '0' COMMENT '0=Un-locked, 1=Locked',
  `status_user_id` varchar(36) DEFAULT NULL,
  `created_by` varchar(36) NOT NULL,
  `created` datetime NOT NULL,
  `modified_by` varchar(36) NOT NULL,
  `approved_by` varchar(36) DEFAULT NULL,
  `prepared_by` varchar(36) DEFAULT NULL,
  `modified` datetime NOT NULL,
  `soft_delete` tinyint(1) NOT NULL DEFAULT '0',
  `branchid` varchar(36) DEFAULT NULL,
  `departmentid` varchar(36) DEFAULT NULL,
  `company_id` varchar(36) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_audit_findings_0_v0s`
--

CREATE TABLE `tbl_audit_findings_0_v0s` (
  `id` varchar(36) NOT NULL,
  `sr_no` int(11) NOT NULL,
  `response_from_auditee` text,
  `findings` text,
  `current_status` int(1) DEFAULT NULL,
  `finding_type` int(1) DEFAULT NULL,
  `auditee` varchar(36) DEFAULT NULL,
  `auditor` varchar(36) DEFAULT NULL,
  `audit_end_date` date DEFAULT NULL,
  `audit_start_date` datetime DEFAULT NULL,
  `finding_number` varchar(255) NOT NULL,
  `qc_document_id` varchar(36) NOT NULL DEFAULT '5abc3b5b-d7e5-4252-8fe2-d21200c26556',
  `custom_table_id` varchar(36) NOT NULL DEFAULT '83c439cf-e6ab-42de-b1c8-a941a4ba18f7',
  `file_id` varchar(36) DEFAULT NULL,
  `file_key` varchar(50) DEFAULT NULL,
  `parent_id` varchar(36) DEFAULT NULL,
  `additional_files` text,
  `publish` tinyint(1) DEFAULT '1' COMMENT '0=Un 1=Pub',
  `record_status` tinyint(1) DEFAULT '0' COMMENT '0=Un-locked, 1=Locked',
  `status_user_id` varchar(36) DEFAULT NULL,
  `created_by` varchar(36) NOT NULL,
  `created` datetime NOT NULL,
  `modified_by` varchar(36) NOT NULL,
  `approved_by` varchar(36) DEFAULT NULL,
  `prepared_by` varchar(36) DEFAULT NULL,
  `modified` datetime NOT NULL,
  `soft_delete` tinyint(1) NOT NULL DEFAULT '0',
  `branchid` varchar(36) DEFAULT NULL,
  `departmentid` varchar(36) DEFAULT NULL,
  `company_id` varchar(36) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_audit_schedule_0_v0s`
--

CREATE TABLE `tbl_audit_schedule_0_v0s` (
  `id` varchar(36) NOT NULL,
  `sr_no` int(11) NOT NULL,
  `notes` text,
  `current_status` int(1) DEFAULT NULL,
  `departments_to_be_audited` text,
  `audit_locations` text,
  `scheduled_end_date` date DEFAULT NULL,
  `schedule_start_date` date DEFAULT NULL,
  `audit_category` varchar(36) DEFAULT NULL,
  `standard` varchar(36) DEFAULT NULL,
  `audit_number` varchar(255) NOT NULL,
  `qc_document_id` varchar(36) NOT NULL DEFAULT 'c29a4cae-ee08-4dc8-9e30-ba6bce0005b4',
  `custom_table_id` varchar(36) NOT NULL DEFAULT '28eb2068-cf9d-4dce-8ea6-2347e14b3d60',
  `file_id` varchar(36) DEFAULT NULL,
  `file_key` varchar(50) DEFAULT NULL,
  `parent_id` varchar(36) DEFAULT NULL,
  `additional_files` text,
  `publish` tinyint(1) DEFAULT '1' COMMENT '0=Un 1=Pub',
  `record_status` tinyint(1) DEFAULT '0' COMMENT '0=Un-locked, 1=Locked',
  `status_user_id` varchar(36) DEFAULT NULL,
  `created_by` varchar(36) NOT NULL,
  `created` datetime NOT NULL,
  `modified_by` varchar(36) NOT NULL,
  `approved_by` varchar(36) DEFAULT NULL,
  `prepared_by` varchar(36) DEFAULT NULL,
  `modified` datetime NOT NULL,
  `soft_delete` tinyint(1) NOT NULL DEFAULT '0',
  `branchid` varchar(36) DEFAULT NULL,
  `departmentid` varchar(36) DEFAULT NULL,
  `company_id` varchar(36) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_calibration_0_v0s`
--

CREATE TABLE `tbl_calibration_0_v0s` (
  `id` varchar(36) NOT NULL,
  `sr_no` int(11) NOT NULL,
  `calibration_performed_by` varchar(36) DEFAULT NULL,
  `next_calibration_date` date DEFAULT NULL,
  `previous_calibration_date` date DEFAULT NULL,
  `calibration_frequency` varchar(36) DEFAULT NULL,
  `tool_location` varchar(36) DEFAULT NULL,
  `tool_details` varchar(255) DEFAULT NULL,
  `equipement` varchar(255) DEFAULT NULL,
  `number` varchar(255) NOT NULL,
  `qc_document_id` varchar(36) NOT NULL DEFAULT 'a9d9fd99-157e-4028-a223-0d72b6d241c8',
  `custom_table_id` varchar(36) NOT NULL DEFAULT 'e60b9ebd-6a04-4caa-975d-c7882a65a704',
  `file_id` varchar(36) DEFAULT NULL,
  `file_key` varchar(50) DEFAULT NULL,
  `parent_id` varchar(36) DEFAULT NULL,
  `additional_files` text,
  `publish` tinyint(1) DEFAULT '1' COMMENT '0=Un 1=Pub',
  `record_status` tinyint(1) DEFAULT '0' COMMENT '0=Un-locked, 1=Locked',
  `status_user_id` varchar(36) DEFAULT NULL,
  `created_by` varchar(36) NOT NULL,
  `created` datetime NOT NULL,
  `modified_by` varchar(36) NOT NULL,
  `approved_by` varchar(36) DEFAULT NULL,
  `prepared_by` varchar(36) DEFAULT NULL,
  `modified` datetime NOT NULL,
  `soft_delete` tinyint(1) NOT NULL DEFAULT '0',
  `branchid` varchar(36) DEFAULT NULL,
  `departmentid` varchar(36) DEFAULT NULL,
  `company_id` varchar(36) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_customer_complaints_0_v0s`
--

CREATE TABLE `tbl_customer_complaints_0_v0s` (
  `id` varchar(36) NOT NULL,
  `sr_no` int(11) NOT NULL,
  `resolution_details` text,
  `current_status` int(1) DEFAULT NULL,
  `assigned_to` varchar(36) DEFAULT NULL,
  `closure_date` date DEFAULT NULL,
  `target_date` date DEFAULT NULL,
  `date_received` date DEFAULT NULL,
  `complaint_details` text,
  `customer` varchar(36) DEFAULT NULL,
  `customer_number` varchar(255) NOT NULL,
  `qc_document_id` varchar(36) NOT NULL DEFAULT '888fb842-bc2d-41c4-b17e-7026dd7643a5',
  `custom_table_id` varchar(36) NOT NULL DEFAULT '93b76a14-a8be-43b2-8fd0-eda477388006',
  `file_id` varchar(36) DEFAULT NULL,
  `file_key` varchar(50) DEFAULT NULL,
  `parent_id` varchar(36) DEFAULT NULL,
  `additional_files` text,
  `publish` tinyint(1) DEFAULT '1' COMMENT '0=Un 1=Pub',
  `record_status` tinyint(1) DEFAULT '0' COMMENT '0=Un-locked, 1=Locked',
  `status_user_id` varchar(36) DEFAULT NULL,
  `created_by` varchar(36) NOT NULL,
  `created` datetime NOT NULL,
  `modified_by` varchar(36) NOT NULL,
  `approved_by` varchar(36) DEFAULT NULL,
  `prepared_by` varchar(36) DEFAULT NULL,
  `modified` datetime NOT NULL,
  `soft_delete` tinyint(1) NOT NULL DEFAULT '0',
  `branchid` varchar(36) DEFAULT NULL,
  `departmentid` varchar(36) DEFAULT NULL,
  `company_id` varchar(36) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_customer_details_0_v0s`
--

CREATE TABLE `tbl_customer_details_0_v0s` (
  `id` varchar(36) NOT NULL,
  `sr_no` int(11) NOT NULL,
  `customer_type` int(1) DEFAULT NULL,
  `customer_status` int(1) DEFAULT NULL,
  `head_office_address` text,
  `fax` varchar(55) DEFAULT NULL,
  `phone` varchar(55) DEFAULT NULL,
  `official_email` varchar(255) DEFAULT NULL,
  `customer_details` text,
  `customer_name` varchar(255) NOT NULL,
  `qc_document_id` varchar(36) NOT NULL DEFAULT '06d28e70-8ed6-424d-8a8c-deb73f5f5510',
  `custom_table_id` varchar(36) NOT NULL DEFAULT '06ab4495-44dc-48b3-86da-61f67f492cb7',
  `file_id` varchar(36) DEFAULT NULL,
  `file_key` varchar(50) DEFAULT NULL,
  `parent_id` varchar(36) DEFAULT NULL,
  `additional_files` text,
  `publish` tinyint(1) DEFAULT '1' COMMENT '0=Un 1=Pub',
  `record_status` tinyint(1) DEFAULT '0' COMMENT '0=Un-locked, 1=Locked',
  `status_user_id` varchar(36) DEFAULT NULL,
  `created_by` varchar(36) NOT NULL,
  `created` datetime NOT NULL,
  `modified_by` varchar(36) NOT NULL,
  `approved_by` varchar(36) DEFAULT NULL,
  `prepared_by` varchar(36) DEFAULT NULL,
  `modified` datetime NOT NULL,
  `soft_delete` tinyint(1) NOT NULL DEFAULT '0',
  `branchid` varchar(36) DEFAULT NULL,
  `departmentid` varchar(36) DEFAULT NULL,
  `company_id` varchar(36) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_device_equipment_0_v0s`
--

CREATE TABLE `tbl_device_equipment_0_v0s` (
  `id` varchar(36) NOT NULL,
  `sr_no` int(11) NOT NULL,
  `maintenance_schedule` varchar(36) DEFAULT NULL,
  `manufacturer_part_number` varchar(255) DEFAULT NULL,
  `equipment_details` text,
  `in_service_since` date DEFAULT NULL,
  `location` varchar(36) DEFAULT NULL,
  `equipment_name` varchar(255) DEFAULT NULL,
  `number` varchar(255) NOT NULL,
  `qc_document_id` varchar(36) NOT NULL DEFAULT '5eea859b-aab2-4c26-8af3-abd16bbd02df',
  `custom_table_id` varchar(36) NOT NULL DEFAULT 'b5eaa2ea-e624-4028-887c-c55d3dc1cece',
  `file_id` varchar(36) DEFAULT NULL,
  `file_key` varchar(50) DEFAULT NULL,
  `parent_id` varchar(36) DEFAULT NULL,
  `additional_files` text,
  `publish` tinyint(1) DEFAULT '1' COMMENT '0=Un 1=Pub',
  `record_status` tinyint(1) DEFAULT '0' COMMENT '0=Un-locked, 1=Locked',
  `status_user_id` varchar(36) DEFAULT NULL,
  `created_by` varchar(36) NOT NULL,
  `created` datetime NOT NULL,
  `modified_by` varchar(36) NOT NULL,
  `approved_by` varchar(36) DEFAULT NULL,
  `prepared_by` varchar(36) DEFAULT NULL,
  `modified` datetime NOT NULL,
  `soft_delete` tinyint(1) NOT NULL DEFAULT '0',
  `branchid` varchar(36) DEFAULT NULL,
  `departmentid` varchar(36) DEFAULT NULL,
  `company_id` varchar(36) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_mrm_0_v0s`
--

CREATE TABLE `tbl_mrm_0_v0s` (
  `id` varchar(36) NOT NULL,
  `sr_no` int(11) NOT NULL,
  `meeting_details` text,
  `attainted_by` text,
  `actual_meeting_date_time` datetime DEFAULT NULL,
  `meeting_status` int(1) DEFAULT NULL,
  `invitees` text,
  `proposed_by` varchar(36) DEFAULT NULL,
  `scheduled_date_time` datetime DEFAULT NULL,
  `meeting_number` varchar(255) NOT NULL,
  `qc_document_id` varchar(36) NOT NULL DEFAULT 'ea17ca9b-c5ec-4f0d-ad7c-14f534b5f32d',
  `custom_table_id` varchar(36) NOT NULL DEFAULT 'd9385db9-54d3-4569-b625-7507181d00d5',
  `file_id` varchar(36) DEFAULT NULL,
  `file_key` varchar(50) DEFAULT NULL,
  `parent_id` varchar(36) DEFAULT NULL,
  `additional_files` text,
  `publish` tinyint(1) DEFAULT '1' COMMENT '0=Un 1=Pub',
  `record_status` tinyint(1) DEFAULT '0' COMMENT '0=Un-locked, 1=Locked',
  `status_user_id` varchar(36) DEFAULT NULL,
  `created_by` varchar(36) NOT NULL,
  `created` datetime NOT NULL,
  `modified_by` varchar(36) NOT NULL,
  `approved_by` varchar(36) DEFAULT NULL,
  `prepared_by` varchar(36) DEFAULT NULL,
  `modified` datetime NOT NULL,
  `soft_delete` tinyint(1) NOT NULL DEFAULT '0',
  `branchid` varchar(36) DEFAULT NULL,
  `departmentid` varchar(36) DEFAULT NULL,
  `company_id` varchar(36) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_supplier_details_0_v0s`
--

CREATE TABLE `tbl_supplier_details_0_v0s` (
  `id` varchar(36) NOT NULL,
  `sr_no` int(11) NOT NULL,
  `supplier_company_type` int(1) DEFAULT NULL,
  `supplier_since` date DEFAULT NULL,
  `supplier_email` varchar(255) DEFAULT NULL,
  `supplier_phone` varchar(55) DEFAULT NULL,
  `supplier_address` text,
  `name` varchar(255) DEFAULT NULL,
  `supplier_number` varchar(255) NOT NULL,
  `qc_document_id` varchar(36) NOT NULL DEFAULT 'e53215e1-ccea-4017-8fee-bd56f4b9975d',
  `custom_table_id` varchar(36) NOT NULL DEFAULT '395bcbab-a29b-4f92-9dce-e80c5a8ddb96',
  `file_id` varchar(36) DEFAULT NULL,
  `file_key` varchar(50) DEFAULT NULL,
  `parent_id` varchar(36) DEFAULT NULL,
  `additional_files` text,
  `publish` tinyint(1) DEFAULT '1' COMMENT '0=Un 1=Pub',
  `record_status` tinyint(1) DEFAULT '0' COMMENT '0=Un-locked, 1=Locked',
  `status_user_id` varchar(36) DEFAULT NULL,
  `created_by` varchar(36) NOT NULL,
  `created` datetime NOT NULL,
  `modified_by` varchar(36) NOT NULL,
  `approved_by` varchar(36) DEFAULT NULL,
  `prepared_by` varchar(36) DEFAULT NULL,
  `modified` datetime NOT NULL,
  `soft_delete` tinyint(1) NOT NULL DEFAULT '0',
  `branchid` varchar(36) DEFAULT NULL,
  `departmentid` varchar(36) DEFAULT NULL,
  `company_id` varchar(36) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `usage_details`
--

CREATE TABLE `usage_details` (
  `id` varchar(36) NOT NULL,
  `sr_no` int(11) NOT NULL,
  `date` date NOT NULL,
  `path` text NOT NULL,
  `file_name` text NOT NULL,
  `last_access` datetime DEFAULT NULL,
  `file_size` text NOT NULL,
  `billed` float(11,2) NOT NULL,
  `db_size` int(11) NOT NULL,
  `db_billed` float(11,2) NOT NULL,
  `publish` tinyint(1) DEFAULT '0' COMMENT '0=Un 1=Pub',
  `record_status` tinyint(1) DEFAULT '0' COMMENT '0=Un-locked, 1=Locked',
  `status_user_id` varchar(36) DEFAULT NULL,
  `created_by` varchar(36) NOT NULL,
  `created` datetime NOT NULL,
  `modified_by` varchar(36) NOT NULL,
  `approved_by` varchar(36) DEFAULT NULL,
  `prepared_by` varchar(36) DEFAULT NULL,
  `modified` datetime NOT NULL,
  `soft_delete` tinyint(1) NOT NULL DEFAULT '0',
  `branchid` varchar(36) DEFAULT NULL,
  `departmentid` varchar(36) DEFAULT NULL,
  `company_id` varchar(36) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` varchar(36) NOT NULL,
  `sr_no` int(11) NOT NULL,
  `employee_id` varchar(36) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `username` varchar(240) NOT NULL,
  `password` varchar(32) NOT NULL,
  `is_mr` tinyint(1) DEFAULT '0',
  `is_mt` tinyint(1) DEFAULT NULL,
  `is_view_all` tinyint(1) DEFAULT '0',
  `is_approver` tinyint(1) DEFAULT '0',
  `is_creator` tinyint(1) DEFAULT '1',
  `is_publisher` tinyint(1) DEFAULT '1',
  `status` int(1) DEFAULT '0',
  `department_id` varchar(36) NOT NULL,
  `branch_id` varchar(36) NOT NULL,
  `language_id` varchar(36) DEFAULT '1',
  `login_status` int(1) DEFAULT '0',
  `last_login` datetime DEFAULT NULL,
  `allow_multiple_login` tinyint(1) DEFAULT '0' COMMENT '0= Not allow, 1=Allow',
  `limit_login_attempt` tinyint(1) DEFAULT '1' COMMENT '0= No limit, 1= limit upto 3 attempt',
  `last_activity` datetime DEFAULT NULL,
  `user_access` text,
  `assigned_branches` text,
  `copy_acl_from` varchar(36) DEFAULT NULL,
  `benchmark` int(5) NOT NULL DEFAULT '0',
  `publish` tinyint(1) DEFAULT '0' COMMENT '0=Un 1=Pub',
  `record_status` tinyint(1) DEFAULT '0' COMMENT '0=Un-locked, 1=Locked',
  `status_user_id` varchar(36) DEFAULT NULL,
  `soft_delete` tinyint(1) DEFAULT '0' COMMENT '1=deleted',
  `branchid` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `departmentid` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `password_token` varchar(225) DEFAULT NULL,
  `email_token_expires` datetime DEFAULT NULL,
  `pwd_last_modified` datetime DEFAULT NULL,
  `agree` tinyint(1) DEFAULT NULL,
  `division_id` varchar(36) DEFAULT '0',
  `company_id` varchar(36) DEFAULT NULL,
  `created_by` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `created` datetime NOT NULL COMMENT 'system defined automatically add',
  `modified_by` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `approved_by` varchar(36) DEFAULT NULL,
  `prepared_by` varchar(36) DEFAULT NULL,
  `modified` datetime NOT NULL COMMENT 'system defined automatically add',
  `system_table_id` varchar(36) DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `user_access_controls`
--

CREATE TABLE `user_access_controls` (
  `id` varchar(36) NOT NULL,
  `sr_no` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `users` text,
  `user_access` text NOT NULL,
  `publish` tinyint(1) DEFAULT '0' COMMENT '0=Un 1=Pub',
  `record_status` tinyint(1) DEFAULT '0' COMMENT '0=Un-locked, 1=Locked',
  `status_user_id` varchar(36) DEFAULT NULL,
  `soft_delete` tinyint(1) DEFAULT '0' COMMENT '1=deleted',
  `branchid` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `departmentid` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `created_by` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `created` datetime NOT NULL COMMENT 'system defined automatically add',
  `modified_by` varchar(36) NOT NULL COMMENT 'system defined automatically add',
  `approved_by` varchar(36) DEFAULT NULL,
  `prepared_by` varchar(36) DEFAULT NULL,
  `modified` datetime NOT NULL COMMENT 'system defined automatically add',
  `system_table_id` varchar(36) DEFAULT '0',
  `division_id` varchar(36) DEFAULT '0',
  `company_id` varchar(36) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` varchar(36) NOT NULL,
  `sr_no` int(11) NOT NULL,
  `ip_address` varchar(18) DEFAULT NULL,
  `start_time` datetime DEFAULT CURRENT_TIMESTAMP,
  `end_time` datetime DEFAULT CURRENT_TIMESTAMP,
  `user_id` varchar(36) DEFAULT NULL,
  `employee_id` varchar(36) DEFAULT NULL,
  `company_id` varchar(36) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `user_sessions`
--
--
-- Indexes for dumped tables
--

--
-- Indexes for table `approvals`
--
ALTER TABLE `approvals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sr_no` (`sr_no`);

--
-- Indexes for table `approval_comments`
--
ALTER TABLE `approval_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sr_no` (`sr_no`);

--
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sr_no` (`sr_no`);

--
-- Indexes for table `chd_mrm0_child_1_v1s`
--
ALTER TABLE `chd_mrm0_child_1_v1s`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sr_no` (`sr_no`);

--
-- Indexes for table `clauses`
--
ALTER TABLE `clauses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sr_no` (`sr_no`);

--
-- Indexes for table `companies`
--
ALTER TABLE `companies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `sr_no` (`sr_no`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sr_no` (`sr_no`);

--
-- Indexes for table `custom_codes`
--
ALTER TABLE `custom_codes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sr_no` (`sr_no`);

--
-- Indexes for table `custom_files`
--
ALTER TABLE `custom_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sr_no` (`sr_no`);

--
-- Indexes for table `custom_tables`
--
ALTER TABLE `custom_tables`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sr_no` (`sr_no`);

--
-- Indexes for table `custom_table_processes`
--
ALTER TABLE `custom_table_processes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sr_no` (`sr_no`);

--
-- Indexes for table `custom_table_tasks`
--
ALTER TABLE `custom_table_tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sr_no` (`sr_no`);

--
-- Indexes for table `custom_triggers`
--
ALTER TABLE `custom_triggers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sr_no` (`sr_no`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `sr_no` (`sr_no`);

--
-- Indexes for table `designations`
--
ALTER TABLE `designations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `sr_no` (`sr_no`);

--
-- Indexes for table `document_change_requests`
--
ALTER TABLE `document_change_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sr_no` (`sr_no`);

--
-- Indexes for table `document_downloads`
--
ALTER TABLE `document_downloads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sr_no` (`sr_no`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sr_no` (`sr_no`);

--
-- Indexes for table `employee_leaves`
--
ALTER TABLE `employee_leaves`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sr_no` (`sr_no`);

--
-- Indexes for table `files`
--
ALTER TABLE `files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sr_no` (`sr_no`);

--
-- Indexes for table `graph_panels`
--
ALTER TABLE `graph_panels`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sr_no` (`sr_no`);

--
-- Indexes for table `histories`
--
ALTER TABLE `histories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sr_no` (`sr_no`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sr_no` (`sr_no`);

--
-- Indexes for table `password_settings`
--
ALTER TABLE `password_settings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sr_no` (`sr_no`);

--
-- Indexes for table `pdf_templates`
--
ALTER TABLE `pdf_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sr_no` (`sr_no`);

--
-- Indexes for table `processes`
--
ALTER TABLE `processes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sr_no` (`sr_no`);

--
-- Indexes for table `qc_documents`
--
ALTER TABLE `qc_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sr_no` (`sr_no`);

--
-- Indexes for table `qc_document_categories`
--
ALTER TABLE `qc_document_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sr_no` (`sr_no`);

--
-- Indexes for table `records`
--
ALTER TABLE `records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sr_no` (`sr_no`);

--
-- Indexes for table `record_locks`
--
ALTER TABLE `record_locks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sr_no` (`sr_no`);

--
-- Indexes for table `schedules`
--
ALTER TABLE `schedules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `sr_no` (`sr_no`);

--
-- Indexes for table `standards`
--
ALTER TABLE `standards`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sr_no` (`sr_no`);

--
-- Indexes for table `system_tables`
--
ALTER TABLE `system_tables`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `system_name` (`system_name`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `sr_no` (`sr_no`);

--
-- Indexes for table `tbl_audit_catetory_v0s`
--
ALTER TABLE `tbl_audit_catetory_v0s`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sr_no` (`sr_no`);

--
-- Indexes for table `tbl_audit_checklist_0_v0s`
--
ALTER TABLE `tbl_audit_checklist_0_v0s`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sr_no` (`sr_no`);

--
-- Indexes for table `tbl_audit_findings_0_v0s`
--
ALTER TABLE `tbl_audit_findings_0_v0s`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sr_no` (`sr_no`);

--
-- Indexes for table `tbl_audit_schedule_0_v0s`
--
ALTER TABLE `tbl_audit_schedule_0_v0s`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sr_no` (`sr_no`);

--
-- Indexes for table `tbl_calibration_0_v0s`
--
ALTER TABLE `tbl_calibration_0_v0s`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sr_no` (`sr_no`);

--
-- Indexes for table `tbl_customer_complaints_0_v0s`
--
ALTER TABLE `tbl_customer_complaints_0_v0s`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sr_no` (`sr_no`);

--
-- Indexes for table `tbl_customer_details_0_v0s`
--
ALTER TABLE `tbl_customer_details_0_v0s`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sr_no` (`sr_no`);

--
-- Indexes for table `tbl_device_equipment_0_v0s`
--
ALTER TABLE `tbl_device_equipment_0_v0s`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sr_no` (`sr_no`);

--
-- Indexes for table `tbl_mrm_0_v0s`
--
ALTER TABLE `tbl_mrm_0_v0s`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sr_no` (`sr_no`);

--
-- Indexes for table `tbl_supplier_details_0_v0s`
--
ALTER TABLE `tbl_supplier_details_0_v0s`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sr_no` (`sr_no`);

--
-- Indexes for table `usage_details`
--
ALTER TABLE `usage_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sr_no` (`sr_no`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `sr_no` (`sr_no`);

--
-- Indexes for table `user_access_controls`
--
ALTER TABLE `user_access_controls`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sr_no` (`sr_no`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sr_no` (`sr_no`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `approvals`
--
ALTER TABLE `approvals`
  MODIFY `sr_no` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `approval_comments`
--
ALTER TABLE `approval_comments`
  MODIFY `sr_no` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `sr_no` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chd_mrm0_child_1_v1s`
--
ALTER TABLE `chd_mrm0_child_1_v1s`
  MODIFY `sr_no` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `clauses`
--
ALTER TABLE `clauses`
  MODIFY `sr_no` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3846;

--
-- AUTO_INCREMENT for table `companies`
--
ALTER TABLE `companies`
  MODIFY `sr_no` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `sr_no` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `custom_codes`
--
ALTER TABLE `custom_codes`
  MODIFY `sr_no` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `custom_files`
--
ALTER TABLE `custom_files`
  MODIFY `sr_no` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `custom_tables`
--
ALTER TABLE `custom_tables`
  MODIFY `sr_no` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `custom_table_processes`
--
ALTER TABLE `custom_table_processes`
  MODIFY `sr_no` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `custom_table_tasks`
--
ALTER TABLE `custom_table_tasks`
  MODIFY `sr_no` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `custom_triggers`
--
ALTER TABLE `custom_triggers`
  MODIFY `sr_no` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `sr_no` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `designations`
--
ALTER TABLE `designations`
  MODIFY `sr_no` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_change_requests`
--
ALTER TABLE `document_change_requests`
  MODIFY `sr_no` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_downloads`
--
ALTER TABLE `document_downloads`
  MODIFY `sr_no` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `sr_no` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_leaves`
--
ALTER TABLE `employee_leaves`
  MODIFY `sr_no` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `files`
--
ALTER TABLE `files`
  MODIFY `sr_no` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `graph_panels`
--
ALTER TABLE `graph_panels`
  MODIFY `sr_no` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `histories`
--
ALTER TABLE `histories`
  MODIFY `sr_no` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `sr_no` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_settings`
--
ALTER TABLE `password_settings`
  MODIFY `sr_no` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `pdf_templates`
--
ALTER TABLE `pdf_templates`
  MODIFY `sr_no` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `processes`
--
ALTER TABLE `processes`
  MODIFY `sr_no` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `qc_documents`
--
ALTER TABLE `qc_documents`
  MODIFY `sr_no` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `qc_document_categories`
--
ALTER TABLE `qc_document_categories`
  MODIFY `sr_no` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `records`
--
ALTER TABLE `records`
  MODIFY `sr_no` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `record_locks`
--
ALTER TABLE `record_locks`
  MODIFY `sr_no` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `sr_no` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `standards`
--
ALTER TABLE `standards`
  MODIFY `sr_no` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `system_tables`
--
ALTER TABLE `system_tables`
  MODIFY `sr_no` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_audit_catetory_v0s`
--
ALTER TABLE `tbl_audit_catetory_v0s`
  MODIFY `sr_no` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_audit_checklist_0_v0s`
--
ALTER TABLE `tbl_audit_checklist_0_v0s`
  MODIFY `sr_no` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_audit_findings_0_v0s`
--
ALTER TABLE `tbl_audit_findings_0_v0s`
  MODIFY `sr_no` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_audit_schedule_0_v0s`
--
ALTER TABLE `tbl_audit_schedule_0_v0s`
  MODIFY `sr_no` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_calibration_0_v0s`
--
ALTER TABLE `tbl_calibration_0_v0s`
  MODIFY `sr_no` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_customer_complaints_0_v0s`
--
ALTER TABLE `tbl_customer_complaints_0_v0s`
  MODIFY `sr_no` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_customer_details_0_v0s`
--
ALTER TABLE `tbl_customer_details_0_v0s`
  MODIFY `sr_no` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_device_equipment_0_v0s`
--
ALTER TABLE `tbl_device_equipment_0_v0s`
  MODIFY `sr_no` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_mrm_0_v0s`
--
ALTER TABLE `tbl_mrm_0_v0s`
  MODIFY `sr_no` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_supplier_details_0_v0s`
--
ALTER TABLE `tbl_supplier_details_0_v0s`
  MODIFY `sr_no` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `usage_details`
--
ALTER TABLE `usage_details`
  MODIFY `sr_no` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `sr_no` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_access_controls`
--
ALTER TABLE `user_access_controls`
  MODIFY `sr_no` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `sr_no` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=457;
COMMIT;

