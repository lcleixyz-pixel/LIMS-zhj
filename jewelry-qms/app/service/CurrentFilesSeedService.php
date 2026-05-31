<?php
declare(strict_types=1);

namespace app\service;

use app\model\Document;
use think\facade\Config;
use think\facade\Db;
use ZipArchive;

class CurrentFilesSeedService
{
    private const COMPANY_NAME = '新疆中和鉴珠宝玉石质量检测研究所（有限公司）';
    private const MANUAL_FILE = '质量手册（第四版）.docx';
    private const WORK_INSTRUCTION_FILE = '作业指导书181201.docx';
    private const MANUAL_EFFECTIVE_DATE = '2026-01-01';
    private const WORK_INSTRUCTION_EFFECTIVE_DATE = '2018-12-01';

    public static function seed(array $options = []): array
    {
        self::ensureSchema();

        $apply = (bool)($options['apply'] ?? false);
        $root = self::appRoot();
        $workspaceRoot = dirname($root);
        $sourceRoot = (string)($options['source_root'] ?? ($workspaceRoot . DIRECTORY_SEPARATOR . '现用文件'));
        $urumqiEquipmentPath = (string)($options['urumqi_equipment_path'] ?? '/Users/lc.leixyz/Downloads/仪器设备（标准物质）配置信息 (2).xlsx');
        $hetianEquipmentPath = (string)($options['hetian_equipment_path'] ?? '/Users/lc.leixyz/Downloads/仪器设备（标准物质）配置信息 (3).xlsx');

        $summary = [
            'apply' => $apply,
            'company' => ['updated' => 0],
            'sites' => ['upserted' => 0],
            'departments' => ['upserted' => 0],
            'positions' => ['upserted' => 0],
            'employees' => ['upserted' => 0],
            'appointments' => ['upserted' => 0],
            'documents' => ['manual' => 0, 'procedures' => 0, 'work_instructions' => 0],
            'quality' => ['policies' => 0, 'objectives' => 0],
            'equipment' => ['urumqi' => 0, 'hetian' => 0],
            'reference_materials' => ['created' => 0],
            'structured' => [],
            'missing_evidence' => [],
        ];

        if (!$apply) {
            $summary['missing_evidence'] = self::standardMaterialEvidenceGaps($sourceRoot);
            return $summary;
        }

        Db::transaction(function () use (&$summary, $sourceRoot, $urumqiEquipmentPath, $hetianEquipmentPath): void {
            $companyId = self::companyId();
            $summary['company']['updated'] += self::seedCompany($companyId);
            $sites = self::seedSites($companyId);
            $summary['sites']['upserted'] += count($sites);
            $departments = self::seedDepartments($companyId);
            $summary['departments']['upserted'] += count($departments);
            $positions = self::seedPositions($companyId);
            $summary['positions']['upserted'] += count($positions);
            $manual = self::seedDocuments($companyId, $sourceRoot, $summary);
            $summary['quality'] = self::seedQualityPolicyAndObjectives($companyId, $manual ? (string)$manual->id : null, $positions);
            $employees = self::seedEmployees($companyId, $departments, $sites);
            $summary['employees']['upserted'] += count($employees);
            $summary['appointments']['upserted'] += self::seedAppointments($companyId, $employees, $positions, $sites, $manual);
            $summary['equipment']['urumqi'] += self::seedEquipmentWorkbook($companyId, $urumqiEquipmentPath, (string)$sites['MAIN']['id']);
            $summary['equipment']['hetian'] += self::seedEquipmentWorkbook($companyId, $hetianEquipmentPath, (string)$sites['HETIAN']['id']);
            $summary['missing_evidence'] = self::standardMaterialEvidenceGaps($sourceRoot);
        });

        QmsElementService::seedAll();
        $summary['structured'] = QmsDocumentStructureService::seedAll();
        $summary['structured']['work_instruction_overrides'] = self::seedWorkInstructionStructures();
        self::writeReport($summary);

        return $summary;
    }

    public static function ensureSchema(): void
    {
        foreach ([
            'measurement_range' => 'ALTER TABLE `equipments` ADD COLUMN `measurement_range` varchar(200) DEFAULT NULL AFTER `serial_number`',
            'traceability_method' => 'ALTER TABLE `equipments` ADD COLUMN `traceability_method` varchar(50) DEFAULT NULL AFTER `measurement_range`',
            'traceability_due_date' => 'ALTER TABLE `equipments` ADD COLUMN `traceability_due_date` date DEFAULT NULL AFTER `traceability_method`',
            'traceability_confirm_result' => 'ALTER TABLE `equipments` ADD COLUMN `traceability_confirm_result` varchar(50) DEFAULT NULL AFTER `traceability_due_date`',
        ] as $column => $ddl) {
            if (!self::columnExists('equipments', $column)) {
                Db::execute($ddl);
            }
        }

        Db::execute("CREATE TABLE IF NOT EXISTS `employee_appointments` (
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
          `status` enum('active','inactive','expired') DEFAULT 'active',
          `publish` tinyint(1) DEFAULT 1,
          `soft_delete` tinyint(1) DEFAULT 0,
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
          KEY `status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    private static function seedCompany(string $companyId): int
    {
        Db::name('companies')->where('id', $companyId)->update([
            'name' => self::COMPANY_NAME,
            'address' => '乌鲁木齐实验室：新疆乌鲁木齐市水磨沟区昆仑路2号华凌玉器城（华凌国际汽车用品进出口中心）四楼B-3-03号；和田实验室：新疆和田地区和田市伊里其乡喀河社区远东国际玉街4号楼103号',
            'phone' => '0991-4633781 / 0903-6671118',
            'modified' => date('Y-m-d H:i:s'),
            'soft_delete' => 0,
            'publish' => 1,
        ]);

        return 1;
    }

    private static function seedSites(string $companyId): array
    {
        return [
            'MAIN' => self::upsertSite($companyId, 'MAIN', '乌鲁木齐实验室', 'main', '新疆乌鲁木齐市水磨沟区昆仑路2号华凌玉器城（华凌国际汽车用品进出口中心）四楼B-3-03号', '0991-4633781', 0),
            'HETIAN' => self::upsertSite($companyId, 'HETIAN', '和田实验室', 'branch', '新疆和田地区和田市伊里其乡喀河社区远东国际玉街4号楼103号', '0903-6671118', 10),
        ];
    }

    private static function upsertSite(string $companyId, string $code, string $name, string $type, string $address, string $phone, int $sortOrder): array
    {
        $row = Db::name('sites')->where('code', $code)->where('soft_delete', 0)->find();
        $id = (string)($row['id'] ?? qms_uuid());
        $payload = [
            'id' => $id,
            'company_id' => $companyId,
            'code' => $code,
            'name' => $name,
            'site_type' => $type,
            'address' => $address,
            'phone' => $phone,
            'status' => 'active',
            'sort_order' => $sortOrder,
            'publish' => 1,
            'soft_delete' => 0,
            'modified' => date('Y-m-d H:i:s'),
        ];
        if ($row) {
            Db::name('sites')->where('id', $id)->update($payload);
        } else {
            $payload['created'] = date('Y-m-d H:i:s');
            Db::name('sites')->insert($payload);
        }

        return $payload;
    }

    private static function seedDepartments(string $companyId): array
    {
        $definitions = [
            'MANAGE' => '管理层',
            'URM-OFFICE' => '乌鲁木齐实验室办公室',
            'URM-TEST' => '乌鲁木齐实验室检测室',
            'HT-OFFICE' => '和田实验室办公室',
            'HT-TEST' => '和田实验室检测室',
        ];
        $departments = [];
        foreach ($definitions as $code => $name) {
            $row = Db::name('departments')->where('code', $code)->where('soft_delete', 0)->find();
            $id = (string)($row['id'] ?? qms_uuid());
            $payload = [
                'id' => $id,
                'company_id' => $companyId,
                'code' => $code,
                'name' => $name,
                'publish' => 1,
                'soft_delete' => 0,
                'modified' => date('Y-m-d H:i:s'),
            ];
            if ($row) {
                Db::name('departments')->where('id', $id)->update($payload);
            } else {
                $payload['created'] = date('Y-m-d H:i:s');
                Db::name('departments')->insert($payload);
            }
            $departments[$code] = $payload;
        }

        return $departments;
    }

    private static function seedPositions(string $companyId): array
    {
        $positions = [
            'legal_representative' => '法定代表人',
            'lab_director' => '实验室主任',
            'technical_manager' => '技术负责人',
            'quality_manager' => '质量负责人',
            'finance_manager' => '财务负责人',
            'internal_auditor' => '内审员',
            'supervisor' => '监督员',
            'office_manager' => '办公室主任',
            'testing_room_manager' => '检测室主任',
            'authorized_signatory' => '授权签字人',
            'equipment_manager' => '设备管理员',
            'document_controller' => '资料管理员',
            'sample_manager' => '样品管理员',
            'testing_staff' => '检测师',
            'comment_interpreter' => '意见解释人',
        ];
        $rows = [];
        foreach ($positions as $code => $name) {
            $row = Db::name('qms_positions')->where('code', $code)->where('soft_delete', 0)->find();
            $id = (string)($row['id'] ?? qms_uuid());
            $payload = [
                'id' => $id,
                'company_id' => $companyId,
                'code' => $code,
                'name' => $name,
                'source' => 'current_quality_manual_2026',
                'review_status' => 'published',
                'publish' => 1,
                'soft_delete' => 0,
                'modified' => date('Y-m-d H:i:s'),
            ];
            if ($row) {
                Db::name('qms_positions')->where('id', $id)->update($payload);
            } else {
                $payload['created'] = date('Y-m-d H:i:s');
                Db::name('qms_positions')->insert($payload);
            }
            self::upsertDesignation($companyId, $name);
            $rows[$code] = $payload;
        }

        return $rows;
    }

    private static function upsertDesignation(string $companyId, string $name): string
    {
        $row = Db::name('designations')->where('name', $name)->where('soft_delete', 0)->find();
        if ($row) {
            return (string)$row['id'];
        }
        $id = qms_uuid();
        Db::name('designations')->insert([
            'id' => $id,
            'company_id' => $companyId,
            'name' => $name,
            'publish' => 1,
            'soft_delete' => 0,
            'created' => date('Y-m-d H:i:s'),
        ]);

        return $id;
    }

    private static function seedDocuments(string $companyId, string $sourceRoot, array &$summary): ?Document
    {
        $manualPath = rtrim($sourceRoot, '/\\') . DIRECTORY_SEPARATOR . self::MANUAL_FILE;
        $manual = self::upsertDocument($companyId, [
            'level' => 1,
            'old_doc_number' => 'QM-04',
            'doc_number' => 'XZTC/SC',
            'title' => '质量手册（第四版）',
            'version' => '第四版',
            'effective_date' => self::MANUAL_EFFECTIVE_DATE,
            'file_path' => self::relativeWorkspacePath($manualPath),
            'file_name' => self::MANUAL_FILE,
            'file_type' => 'docx',
        ]);
        $summary['documents']['manual']++;

        foreach (self::procedureRows($sourceRoot) as $row) {
            self::upsertDocument($companyId, $row);
            $summary['documents']['procedures']++;
        }
        foreach (self::workInstructionRows($sourceRoot) as $row) {
            self::upsertDocument($companyId, $row);
            $summary['documents']['work_instructions']++;
        }

        return $manual;
    }

    private static function upsertDocument(string $companyId, array $row): Document
    {
        $document = null;
        if ((string)($row['old_doc_number'] ?? '') !== '') {
            $document = Document::where('doc_number', (string)$row['old_doc_number'])->where('soft_delete', 0)->find();
        }
        if (!$document) {
            $document = Document::where('doc_number', (string)$row['doc_number'])->where('soft_delete', 0)->find();
        }
        if (!$document && (string)($row['title'] ?? '') !== '') {
            $document = Document::where('level', (int)$row['level'])
                ->where('title', (string)$row['title'])
                ->where('soft_delete', 0)
                ->find();
        }
        if (!$document) {
            $document = new Document();
            $document->id = qms_uuid();
        }
        $document->save([
            'company_id' => $companyId,
            'level' => (int)$row['level'],
            'doc_number' => (string)$row['doc_number'],
            'title' => (string)$row['title'],
            'version' => (string)($row['version'] ?? 'A/0'),
            'effective_date' => $row['effective_date'] ?? null,
            'status' => 'published',
            'file_path' => (string)($row['file_path'] ?? ''),
            'file_name' => (string)($row['file_name'] ?? ''),
            'file_type' => (string)($row['file_type'] ?? ''),
            'publish' => 1,
            'soft_delete' => 0,
        ]);

        return $document;
    }

    private static function procedureRows(string $sourceRoot): array
    {
        $dir = rtrim($sourceRoot, '/\\') . '/程序文件/程序文件2022';
        $rows = [];
        foreach (array_merge(glob($dir . '/*.docx') ?: [], glob($dir . '/*.doc') ?: []) as $path) {
            $baseName = pathinfo((string)$path, PATHINFO_FILENAME);
            if (preg_match('/(封面|目录|批准页|修改页)$/u', $baseName)) {
                continue;
            }
            if (!preg_match('/^([0-9]+(?:-[0-9]+)?)\s*[-－]\s*(20[0-9]{2})(.+)$/u', $baseName, $match)) {
                continue;
            }
            $title = trim((string)$match[3]);
            if ($title === '' || !str_ends_with($title, '程序')) {
                continue;
            }
            $number = (string)$match[1];
            $year = (string)$match[2];
            $rows[] = [
                'level' => 2,
                'old_doc_number' => 'QP-' . $number,
                'doc_number' => 'XZTC/CX-' . $number . '-' . $year,
                'title' => $title,
                'version' => $year,
                'file_path' => self::relativeWorkspacePath((string)$path),
                'file_name' => basename((string)$path),
                'file_type' => strtolower((string)pathinfo((string)$path, PATHINFO_EXTENSION)),
            ];
        }
        usort($rows, static fn (array $a, array $b): int => strnatcmp((string)$a['doc_number'], (string)$b['doc_number']));

        return $rows;
    }

    private static function workInstructionRows(string $sourceRoot): array
    {
        $path = rtrim($sourceRoot, '/\\') . DIRECTORY_SEPARATOR . self::WORK_INSTRUCTION_FILE;
        $definitions = [
            ['XZTC/ZY-1-01-2018', '检测工作作业指导书'],
            ['XZTC/ZY-1-02-2018', '数据录入作业指导书'],
            ['XZTC/ZY-1-03-2018', '照相作业指导书'],
            ['XZTC/ZY-1-04-2018', '处理图片作业指导书'],
            ['XZTC/ZY-1-05-2018', '证书打印作业指导书'],
            ['XZTC/ZY-1-06-2018', '证书后期制作作业指导书'],
            ['XZTC/ZY-2-01-2018', '电子天平作业指导书'],
            ['XZTC/ZY-2-02-2018', '分光镜作业指导书'],
            ['XZTC/ZY-2-03-2018', '二色镜作业指导书'],
            ['XZTC/ZY-2-04-2018', '硬度笔作业指导书'],
            ['XZTC/ZY-2-05-2018', '紫外荧光灯作业指导书'],
            ['XZTC/ZY-2-06-2018', '偏光镜作业指导书'],
            ['XZTC/ZY-2-07-2018', '折射仪作业指导书'],
            ['XZTC/ZY-2-08-2018', '光纤灯作业指导书'],
            ['XZTC/ZY-2-09-2018', '游标卡尺作业指导书'],
            ['XZTC/ZY-2-10-2018', '温湿度计作业指导书'],
            ['XZTC/ZY-2-11-2018', '显微镜作业指导书'],
            ['XZTC/ZY-2-12-2018', '10X放大镜作业指导书'],
            ['XZTC/ZY-2-13-2018', '红外光谱仪作业指导书'],
            ['XZTC/ZY-2-14-2018', '净水称重作业指导书'],
            ['XZTC/ZY-2-15-2018', 'X射线荧光光谱测定贵金属含量作业指导书'],
            ['XZTC/ZY-2-16-2018', '紫外-可见光纤光谱仪作业指导书'],
            ['XZTC/ZY-3-01-2018', 'X射线荧光光谱仪期间核查作业指导书'],
            ['XZTC/ZY-3-02-2018', '紫外-可见光纤光谱仪期间核查作业指导书'],
            ['XZTC/ZY-4-01-2018', '常规珠宝玉石检测作业指导书'],
            ['XZTC/ZY-4-02-2018', '和田玉检测作业指导书'],
            ['XZTC/ZY-4-03-2018', '金丝玉检测作业指导书'],
        ];
        $rows = [];
        foreach ($definitions as [$docNumber, $title]) {
            $rows[] = [
                'level' => 3,
                'doc_number' => $docNumber,
                'title' => $title,
                'version' => '第二版',
                'effective_date' => self::WORK_INSTRUCTION_EFFECTIVE_DATE,
                'file_path' => self::relativeWorkspacePath($path),
                'file_name' => self::WORK_INSTRUCTION_FILE,
                'file_type' => 'docx',
            ];
        }

        return $rows;
    }

    private static function seedQualityPolicyAndObjectives(string $companyId, ?string $manualId, array $positions): array
    {
        Db::name('qms_quality_policies')->where('is_current', 1)->where('soft_delete', 0)->update([
            'is_current' => 0,
            'review_status' => 'obsolete',
            'modified' => date('Y-m-d H:i:s'),
        ]);
        $policy = Db::name('qms_quality_policies')->where('title', '质量方针')->where('soft_delete', 0)->find();
        $policyId = (string)($policy['id'] ?? qms_uuid());
        $payload = [
            'id' => $policyId,
            'company_id' => $companyId,
            'title' => '质量方针',
            'policy_text' => '公正 科学 准确 高效',
            'version' => '第四版',
            'effective_date' => self::MANUAL_EFFECTIVE_DATE,
            'source_document_id' => $manualId,
            'is_current' => 1,
            'management_review_input' => 1,
            'review_status' => 'published',
            'publish' => 1,
            'soft_delete' => 0,
            'modified' => date('Y-m-d H:i:s'),
        ];
        if ($policy) {
            Db::name('qms_quality_policies')->where('id', $policyId)->update($payload);
        } else {
            $payload['created'] = date('Y-m-d H:i:s');
            Db::name('qms_quality_policies')->insert($payload);
        }

        $objectives = [
            ['检测数据出错率为零', '检测数据出错率', '0', '%', '检测室', '检测室主任'],
            ['客户满意率 >95%', '客户满意率', '>95', '%', '办公室', '办公室主任'],
            ['客户投诉处理率 100%', '客户投诉处理率', '100', '%', '办公室', '质量负责人'],
        ];
        foreach ($objectives as [$title, $metric, $target, $unit, $department, $position]) {
            $row = Db::name('qms_quality_objectives')->where('title', $title)->where('year', 2026)->where('soft_delete', 0)->find();
            $id = (string)($row['id'] ?? qms_uuid());
            $payload = [
                'id' => $id,
                'company_id' => $companyId,
                'policy_id' => $policyId,
                'year' => 2026,
                'position_id' => self::positionIdByName($positions, $position),
                'title' => $title,
                'metric_name' => $metric,
                'target_value' => $target,
                'unit' => $unit,
                'statistic_cycle' => 'annual',
                'responsible_department' => $department,
                'responsible_position' => $position,
                'management_review_input' => 1,
                'review_status' => 'published',
                'publish' => 1,
                'soft_delete' => 0,
                'modified' => date('Y-m-d H:i:s'),
            ];
            if ($row) {
                Db::name('qms_quality_objectives')->where('id', $id)->update($payload);
            } else {
                $payload['created'] = date('Y-m-d H:i:s');
                Db::name('qms_quality_objectives')->insert($payload);
            }
        }

        return ['policies' => 1, 'objectives' => count($objectives)];
    }

    private static function seedEmployees(string $companyId, array $departments, array $sites): array
    {
        $definitions = [
            ['XZTC-RY-001', '俞炳星', 'MANAGE', null, '法定代表人'],
            ['XZTC-RY-002', '张晓磊', 'MANAGE', 'MAIN', '实验室主任'],
            ['XZTC-RY-003', '曹红', 'URM-TEST', 'MAIN', '检测室主任'],
            ['XZTC-RY-004', '李成辉', 'HT-TEST', 'HETIAN', '检测室主任'],
            ['XZTC-RY-005', '陈辉', 'MANAGE', null, '财务负责人'],
            ['XZTC-RY-006', '付丽', 'URM-OFFICE', 'MAIN', '办公室主任'],
            ['XZTC-RY-007', '许莉', 'URM-OFFICE', 'MAIN', '样品管理员'],
            ['XZTC-RY-008', '如则托合提', 'HT-OFFICE', 'HETIAN', '办公室主任'],
            ['XZTC-RY-009', '米尔布拉', 'HT-OFFICE', 'HETIAN', '设备管理员'],
            ['XZTC-RY-010', '史广', 'HT-TEST', 'HETIAN', '样品管理员'],
            ['XZTC-RY-011', '许库尔', 'URM-TEST', 'MAIN', '检测师'],
            ['XZTC-RY-012', '毛天一', 'URM-TEST', 'MAIN', '检测师'],
            ['XZTC-RY-013', '王胜林', 'URM-TEST', 'MAIN', '检测师'],
        ];
        $employees = [];
        foreach ($definitions as [$number, $name, $departmentCode, $siteCode, $designationName]) {
            $row = Db::name('employees')->where('name', $name)->where('soft_delete', 0)->find();
            $id = (string)($row['id'] ?? qms_uuid());
            $payload = [
                'id' => $id,
                'company_id' => $companyId,
                'department_id' => $departments[$departmentCode]['id'] ?? null,
                'primary_site_id' => $siteCode ? ($sites[$siteCode]['id'] ?? null) : null,
                'designation_id' => self::upsertDesignation($companyId, $designationName),
                'employee_number' => $number,
                'name' => $name,
                'publish' => 1,
                'soft_delete' => 0,
                'modified' => date('Y-m-d H:i:s'),
            ];
            if ($row) {
                Db::name('employees')->where('id', $id)->update($payload);
            } else {
                $payload['created'] = date('Y-m-d H:i:s');
                Db::name('employees')->insert($payload);
            }
            $employees[$name] = $payload;
        }

        return $employees;
    }

    private static function seedAppointments(string $companyId, array $employees, array $positions, array $sites, ?Document $manual): int
    {
        $rows = [
            ['俞炳星', 'legal_representative', null, 'role', '法定代表人（总经理）'],
            ['张晓磊', 'lab_director', null, 'role', '实验室主任'],
            ['俞炳星', 'quality_manager', null, 'role', '实验室质量负责人'],
            ['张晓磊', 'quality_manager', null, 'role', '实验室质量负责人'],
            ['曹红', 'technical_manager', null, 'role', '实验室技术负责人'],
            ['李成辉', 'technical_manager', null, 'role', '实验室技术负责人'],
            ['陈辉', 'finance_manager', null, 'role', '实验室财务负责人'],
            ['张晓磊', 'internal_auditor', null, 'role', '实验室内审员'],
            ['曹红', 'internal_auditor', null, 'role', '实验室内审员'],
            ['张晓磊', 'supervisor', 'MAIN', 'role', '乌鲁木齐实验室监督员'],
            ['俞炳星', 'supervisor', 'HETIAN', 'role', '和田实验室监督员'],
            ['付丽', 'office_manager', 'MAIN', 'role', '乌鲁木齐实验室办公室主任'],
            ['曹红', 'testing_room_manager', 'MAIN', 'role', '乌鲁木齐实验室检测室主任'],
            ['曹红', 'document_controller', 'MAIN', 'role', '乌鲁木齐实验室资料管理员'],
            ['张晓磊', 'equipment_manager', 'MAIN', 'role', '乌鲁木齐实验室设备管理员'],
            ['许莉', 'sample_manager', 'MAIN', 'role', '乌鲁木齐实验室样品管理员'],
            ['如则托合提', 'office_manager', 'HETIAN', 'role', '和田实验室办公室主任'],
            ['李成辉', 'testing_room_manager', 'HETIAN', 'role', '和田实验室检测室主任'],
            ['李成辉', 'document_controller', 'HETIAN', 'role', '和田实验室资料管理员'],
            ['米尔布拉', 'equipment_manager', 'HETIAN', 'role', '和田实验室设备管理员'],
            ['史广', 'sample_manager', 'HETIAN', 'role', '和田实验室样品管理员'],
            ['许库尔', 'testing_staff', 'MAIN', 'role', '乌鲁木齐实验室检测师'],
            ['毛天一', 'testing_staff', 'MAIN', 'role', '乌鲁木齐实验室检测师'],
            ['王胜林', 'testing_staff', 'MAIN', 'role', '乌鲁木齐实验室检测师'],
            ['史广', 'testing_staff', 'HETIAN', 'role', '和田实验室检测师'],
        ];

        $wideScope = '珠宝玉石；贵金属及其饰品检测；和田玉（子料）';
        $regularScope = '珠宝玉石；贵金属及其饰品检测';
        foreach (['俞炳星', '曹红', '张晓磊', '李成辉'] as $name) {
            $rows[] = [$name, 'authorized_signatory', null, 'authorization', $wideScope];
            $rows[] = [$name, 'comment_interpreter', null, 'authorization', $wideScope];
        }
        $rows[] = ['如则托合提', 'authorized_signatory', 'HETIAN', 'authorization', $wideScope];
        foreach (['如则托合提', '米尔布拉', '许库尔', '毛天一', '王胜林'] as $name) {
            $rows[] = [$name, 'testing_staff', null, 'authorization', in_array($name, ['如则托合提'], true) ? $wideScope : $regularScope];
        }

        $count = 0;
        foreach ($rows as [$employeeName, $positionCode, $siteCode, $type, $scope]) {
            if (!isset($employees[$employeeName], $positions[$positionCode])) {
                continue;
            }
            self::upsertAppointment($companyId, $employees[$employeeName], $positions[$positionCode], $siteCode ? ($sites[$siteCode] ?? null) : null, $type, $scope, $manual);
            $count++;
        }

        return $count;
    }

    private static function upsertAppointment(string $companyId, array $employee, array $position, ?array $site, string $type, string $scope, ?Document $manual): void
    {
        $key = 'manual-2026-' . substr(hash('sha256', implode('|', [
            (string)$employee['id'],
            (string)$position['code'],
            (string)($site['id'] ?? ''),
            $type,
            $scope,
        ])), 0, 32);
        $row = Db::name('employee_appointments')->where('company_id', $companyId)->where('appointment_key', $key)->find();
        $id = (string)($row['id'] ?? qms_uuid());
        $payload = [
            'id' => $id,
            'company_id' => $companyId,
            'employee_id' => (string)$employee['id'],
            'position_id' => (string)$position['id'],
            'site_id' => $site['id'] ?? null,
            'appointment_key' => $key,
            'appointment_type' => $type,
            'position_name' => (string)$position['name'],
            'appointment_scope' => $scope,
            'appointed_at' => self::MANUAL_EFFECTIVE_DATE,
            'source_document_id' => $manual ? (string)$manual->id : null,
            'source_document_number' => $manual ? (string)$manual->doc_number : 'XZTC/SC',
            'source_excerpt' => '质量手册附录《任命书》与《授权情况一览表》',
            'status' => 'active',
            'publish' => 1,
            'soft_delete' => 0,
            'modified' => date('Y-m-d H:i:s'),
        ];
        if ($row) {
            Db::name('employee_appointments')->where('id', $id)->update($payload);
        } else {
            $payload['created'] = date('Y-m-d H:i:s');
            Db::name('employee_appointments')->insert($payload);
        }
    }

    private static function seedEquipmentWorkbook(string $companyId, string $path, string $siteId): int
    {
        $count = 0;
        foreach (self::equipmentRowsFromWorkbook($path) as $row) {
            $equipment = Db::name('equipments')->where('equipment_number', $row['equipment_number'])->where('soft_delete', 0)->find();
            $id = (string)($equipment['id'] ?? qms_uuid());
            $isCalibration = (string)$row['traceability_method'] === '校准';
            $payload = [
                'id' => $id,
                'company_id' => $companyId,
                'equipment_number' => $row['equipment_number'],
                'name' => $row['name'],
                'model' => $row['model'],
                'measurement_range' => $row['measurement_range'],
                'traceability_method' => $row['traceability_method'],
                'traceability_due_date' => $row['traceability_due_date'],
                'traceability_confirm_result' => $row['traceability_confirm_result'],
                'site_id' => $siteId,
                'location' => $siteId,
                'calibration_required' => $isCalibration ? 1 : 0,
                'next_calibration_date' => $isCalibration ? $row['traceability_due_date'] : null,
                'status' => 'active',
                'publish' => 1,
                'soft_delete' => 0,
                'modified' => date('Y-m-d H:i:s'),
            ];
            if ($equipment) {
                Db::name('equipments')->where('id', $id)->update($payload);
            } else {
                $payload['created'] = date('Y-m-d H:i:s');
                Db::name('equipments')->insert($payload);
            }
            $count++;
        }

        return $count;
    }

    private static function seedWorkInstructionStructures(): int
    {
        $count = 0;
        $companyId = self::companyId();
        $dir = self::appRoot() . '/runtime/qms_structured/work_instruction';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        foreach (Db::name('documents')
            ->where('level', 3)
            ->whereLike('doc_number', 'XZTC/ZY-%')
            ->where('soft_delete', 0)
            ->order('doc_number', 'asc')
            ->select() as $document) {
            $docNumber = (string)$document['doc_number'];
            $version = (string)($document['version'] ?? '第二版');
            $structured = Db::name('qms_structured_documents')
                ->where('document_role', 'work_instruction')
                ->where('doc_number', $docNumber)
                ->where('version', $version)
                ->where('soft_delete', 0)
                ->find();
            $structuredId = (string)($structured['id'] ?? qms_uuid());
            $token = self::stableToken($docNumber . '-' . (string)$document['title']);
            $markdownPath = 'runtime/qms_structured/work_instruction/' . $token . '.md';
            $markdown = '# ' . $docNumber . ' ' . (string)$document['title'] . "\n\n"
                . "- 文件类型：作业指导书\n"
                . "- 版本：{$version}\n"
                . "- 原始文件：" . (string)$document['file_path'] . "\n"
                . "- 结构边界：该条记录来自《作业指导书》合订本目录，后续可按本作业指导书单独维护、修订和组合输出。\n";
            file_put_contents(self::appRoot() . '/' . $markdownPath, $markdown);
            $payload = [
                'id' => $structuredId,
                'company_id' => $companyId,
                'source_asset_id' => $structured['source_asset_id'] ?? null,
                'document_id' => (string)$document['id'],
                'document_role' => 'work_instruction',
                'doc_number' => $docNumber,
                'title' => (string)$document['title'],
                'version' => $version,
                'source_status' => 'current',
                'markdown_path' => $markdownPath,
                'rendered_file_path' => $markdownPath,
                'render_status' => 'rendered',
                'status' => 'structured',
                'review_note' => '由现用作业指导书合订本目录拆分生成，后续可逐条补充源文块。',
                'publish' => 1,
                'soft_delete' => 0,
                'modified' => date('Y-m-d H:i:s'),
            ];
            if ($structured) {
                Db::name('qms_structured_documents')->where('id', $structuredId)->update($payload);
            } else {
                $payload['created'] = date('Y-m-d H:i:s');
                Db::name('qms_structured_documents')->insert($payload);
            }

            self::upsertWorkInstructionBlock($companyId, $structuredId, (string)$document['id'], $docNumber, (string)$document['title'], (string)$document['file_path'], $markdown);
            $count++;
        }

        return $count;
    }

    private static function upsertWorkInstructionBlock(string $companyId, string $structuredId, string $documentId, string $docNumber, string $title, string $filePath, string $markdown): void
    {
        $stableKey = 'work_instruction:' . self::stableToken($docNumber) . ':overview';
        $block = Db::name('qms_document_blocks')
            ->where('structured_document_id', $structuredId)
            ->where('stable_key', $stableKey)
            ->where('soft_delete', 0)
            ->find();
        $id = (string)($block['id'] ?? qms_uuid());
        $payload = [
            'id' => $id,
            'company_id' => $companyId,
            'structured_document_id' => $structuredId,
            'document_id' => $documentId,
            'stable_key' => $stableKey,
            'section_number' => $docNumber,
            'title' => $title,
            'block_type' => 'control_requirement',
            'markdown' => $markdown,
            'sort_order' => 100,
            'source_locator' => $filePath . '#' . $docNumber,
            'status' => 'effective',
            'publish' => 1,
            'soft_delete' => 0,
            'modified' => date('Y-m-d H:i:s'),
        ];
        if ($block) {
            Db::name('qms_document_blocks')->where('id', $id)->update($payload);
        } else {
            $payload['created'] = date('Y-m-d H:i:s');
            Db::name('qms_document_blocks')->insert($payload);
        }
    }

    private static function equipmentRowsFromWorkbook(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }
        $rows = [];
        foreach (self::xlsxRows($path) as $index => $row) {
            if ($index < 3) {
                continue;
            }
            $code = self::cell($row, 9);
            if ($code === '' || $code === '/' || str_contains($code, '*编号')) {
                continue;
            }
            $rows[$code] = [
                'equipment_number' => $code,
                'name' => self::cell($row, 10),
                'model' => self::cell($row, 11),
                'measurement_range' => self::cell($row, 12),
                'traceability_method' => self::cell($row, 13),
                'traceability_due_date' => self::dateCell(self::cell($row, 14)),
                'traceability_confirm_result' => self::cell($row, 15),
            ];
        }

        return array_values($rows);
    }

    private static function xlsxRows(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return [];
        }
        $sharedStrings = self::xlsxSharedStrings($zip);
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetXml === false) {
            $zip->close();
            return [];
        }
        $xml = simplexml_load_string($sheetXml);
        if (!$xml) {
            $zip->close();
            return [];
        }
        $rows = [];
        foreach ($xml->sheetData->row as $rowNode) {
            $row = [];
            foreach ($rowNode->c as $cell) {
                $ref = (string)$cell['r'];
                $columnLetters = preg_replace('/\d+/', '', $ref) ?: '';
                $columnIndex = self::columnIndex($columnLetters);
                $type = (string)$cell['t'];
                $value = '';
                if ($type === 's') {
                    $value = $sharedStrings[(int)$cell->v] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = (string)($cell->is->t ?? '');
                } else {
                    $value = (string)($cell->v ?? '');
                }
                $row[$columnIndex] = trim($value);
            }
            if ($row !== []) {
                $rows[] = $row;
            }
        }
        $zip->close();

        return $rows;
    }

    private static function xlsxSharedStrings(ZipArchive $zip): array
    {
        $xmlString = $zip->getFromName('xl/sharedStrings.xml');
        if ($xmlString === false) {
            return [];
        }
        $xml = simplexml_load_string($xmlString);
        if (!$xml) {
            return [];
        }
        $strings = [];
        foreach ($xml->si as $item) {
            if (isset($item->t)) {
                $strings[] = (string)$item->t;
                continue;
            }
            $parts = [];
            foreach ($item->r as $run) {
                $parts[] = (string)($run->t ?? '');
            }
            $strings[] = implode('', $parts);
        }

        return $strings;
    }

    private static function columnIndex(string $letters): int
    {
        $index = 0;
        foreach (str_split(strtoupper($letters)) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return max(0, $index - 1);
    }

    private static function cell(array $row, int $index): string
    {
        $value = trim((string)($row[$index] ?? ''));

        return $value === '/' ? '' : $value;
    }

    private static function dateCell(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }
        if (is_numeric($value) && (float)$value > 20000) {
            $timestamp = ((int)$value - 25569) * 86400;
            return gmdate('Y-m-d', $timestamp);
        }
        $timestamp = strtotime($value);

        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }

    private static function standardMaterialEvidenceGaps(string $sourceRoot): array
    {
        $dir = rtrim($sourceRoot, '/\\') . '/记录表格/记录表格2017/04仪器设备和标准物质期间核查程序';
        $gaps = [];
        foreach (glob($dir . '/*') ?: [] as $path) {
            $name = basename((string)$path);
            if (preg_match('/(金标片G\d+|银标片G\d+|锆石标样|尖晶石标样|合成红宝石标样)/u', $name, $match)) {
                $gaps[] = [
                    'type' => 'reference_material',
                    'name' => (string)$match[1],
                    'source' => self::relativeWorkspacePath((string)$path),
                    'reason' => '期间核查模板提及该标准物质，但标准物质台账源文件为空，暂不创建正式台账。',
                ];
            }
        }

        return array_values(array_unique($gaps, SORT_REGULAR));
    }

    private static function writeReport(array $summary): void
    {
        $path = self::appRoot() . '/runtime/qms_current_files_seed_report.json';
        file_put_contents($path, json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }

    private static function positionIdByName(array $positions, string $name): ?string
    {
        foreach ($positions as $position) {
            if ((string)$position['name'] === $name) {
                return (string)$position['id'];
            }
        }

        return null;
    }

    private static function relativeWorkspacePath(string $absolutePath): string
    {
        $workspace = dirname(self::appRoot());
        $absolutePath = str_replace('\\', '/', $absolutePath);
        $workspace = str_replace('\\', '/', $workspace);
        if (str_starts_with($absolutePath, $workspace . '/')) {
            return substr($absolutePath, strlen($workspace) + 1);
        }

        return $absolutePath;
    }

    private static function stableToken(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9\x{4e00}-\x{9fa5}]+/u', '-', $value) ?: 'item';
        $value = trim($value, '-');

        return $value !== '' ? mb_substr($value, 0, 100) : 'item';
    }

    private static function appRoot(): string
    {
        return rtrim(app()->getRootPath(), '/\\');
    }

    private static function companyId(): string
    {
        return (string)Config::get('qms.company_id', '00000000-0000-0000-0000-000000000001');
    }

    private static function columnExists(string $table, string $column): bool
    {
        $result = Db::query(
            'SELECT COUNT(*) AS total FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );

        return (int)($result[0]['total'] ?? 0) > 0;
    }
}
