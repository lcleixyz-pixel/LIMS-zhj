<?php
declare(strict_types=1);

namespace app\service;

use DomainException;
use RuntimeException;
use think\facade\Db;

final class P0ControlledMigrationPackageService
{
    public const REHEARSAL_MARKER = 'B6_REHEARSAL_ONLY_NOT_REAL_APPROVAL';

    private const TARGETS = [
        ['俞炳星', 'company_general_manager', null, 'role', '总经理'],
        ['俞炳星', 'top_management', null, 'role', 'CMA 登记最高管理者'],
        ['俞炳星', 'authorized_signatory', null, 'authorization', '两场所灵活轮调授权签字人'],
        ['俞炳星', 'internal_auditor', null, 'role', '内审组员候选'],
        ['俞炳星', 'supervisor', 'PLACE02', 'role', '和田实验室监督工作'],
        ['张晓磊', 'quality_manager', null, 'role', '质量负责人'],
        ['张晓磊', 'top_management', null, 'role', '内部最高管理者；不替代 CMA 登记最高管理者'],
        ['张晓磊', 'authorized_signatory', null, 'authorization', '两场所灵活轮调授权签字人'],
        ['张晓磊', 'internal_auditor', null, 'role', '通常担任内审组长'],
        ['张晓磊', 'supervisor', 'PLACE01', 'role', '乌鲁木齐实验室监督工作'],
        ['张晓磊', 'system_administrator', null, 'responsibility', 'LIMS 系统管理员'],
        ['刘恒春', 'site_quality_coordinator', null, 'role', '质量负责人代理/两场所质量协调；代理启用需另行受控记录'],
        ['刘恒春', 'technical_manager', null, 'role', '总体技术责任人'],
        ['刘恒春', 'authorized_signatory', 'PLACE01', 'authorization', '乌鲁木齐授权签字人'],
        ['刘恒春', 'authorized_signatory', 'PLACE02', 'authorization', '和田授权签字人'],
        ['刘恒春', 'internal_auditor', null, 'role', '内审组员候选'],
        ['曹红', 'technical_manager', 'PLACE01', 'role', '乌鲁木齐技术负责人'],
        ['曹红', 'authorized_signatory', 'PLACE01', 'authorization', '乌鲁木齐固定授权签字人'],
        ['曹红', 'internal_auditor', null, 'role', '内审组员候选'],
        ['李成辉', 'technical_manager', 'PLACE02', 'role', '和田技术负责人'],
        ['李成辉', 'authorized_signatory', 'PLACE02', 'authorization', '和田固定授权签字人'],
        ['付丽', 'document_controller', 'PLACE01', 'role', '乌鲁木齐文件管理员'],
        ['王胜林', 'equipment_manager', 'PLACE01', 'role', '乌鲁木齐设备管理员'],
        ['__HETIAN_DOCUMENT_CONTROLLER__', 'document_controller', 'PLACE02', 'role', '和田文件管理员'],
        ['__HETIAN_EQUIPMENT_MANAGER__', 'equipment_manager', 'PLACE02', 'role', '和田设备管理员'],
    ];

    private const POSITION_NAMES = [
        'company_general_manager' => '总经理',
        'top_management' => '最高管理者',
        'authorized_signatory' => '授权签字人',
        'internal_auditor' => '内审员',
        'supervisor' => '监督员',
        'quality_manager' => '质量负责人',
        'system_administrator' => 'LIMS系统管理员',
        'site_quality_coordinator' => '场所质量协调人',
        'technical_manager' => '技术负责人',
        'document_controller' => '文件管理员',
        'equipment_manager' => '设备管理员',
    ];

    public static function build(array $confirmation, string $outputDir, bool $rehearsal): array
    {
        self::validateConfirmation($confirmation, $rehearsal);
        $state = self::captureCurrentState();
        self::validateCurrentState($confirmation, $state, $rehearsal);
        return self::writePackage($confirmation, $state, $outputDir, $rehearsal);
    }

    private static function validateConfirmation(array $confirmation, bool $rehearsal): void
    {
        if (($confirmation['schema_version'] ?? '') !== 'g-r13-b6-confirmation-v0.1') {
            throw new DomainException('schema_version 不受支持');
        }
        if (($confirmation['status'] ?? '') !== 'approved') {
            throw new DomainException('status 必须为 approved');
        }

        foreach (['target_database', 'company_id', 'document_number', 'effective_date', 'source_excerpt'] as $field) {
            if (trim((string)($confirmation[$field] ?? '')) === '') {
                throw new DomainException("{$field} 不得为空");
            }
        }
        if (!self::validDate((string)$confirmation['effective_date'])) {
            throw new DomainException('effective_date 不是合法日期');
        }
        if (preg_match('/(?:^|[-_])(B5|SIM)(?:[-_]|$)/i', (string)$confirmation['document_number']) === 1) {
            throw new DomainException('document_number 不得使用测试标识');
        }

        foreach (['hetian_document_controller', 'hetian_equipment_manager'] as $key) {
            $person = (array)($confirmation['people'][$key] ?? []);
            if (trim((string)($person['formal_name'] ?? '')) === '') {
                throw new DomainException("people.{$key}.formal_name 不得为空");
            }
            if (trim((string)($person['employee_number'] ?? '')) === '') {
                throw new DomainException("people.{$key}.employee_number 不得为空");
            }
        }
        $numbers = [
            (string)$confirmation['people']['hetian_document_controller']['employee_number'],
            (string)$confirmation['people']['hetian_equipment_manager']['employee_number'],
        ];
        if (count(array_unique($numbers)) !== 2) {
            throw new DomainException('两名人员的 employee_number 不得相同');
        }

        $reviewNames = [
            'quality_manager' => '张晓磊',
            'technical_manager' => '刘恒春',
            'top_management' => '俞炳星',
        ];
        foreach ($reviewNames as $key => $expectedName) {
            $review = (array)($confirmation['reviews'][$key] ?? []);
            if (($review['name'] ?? '') !== $expectedName || ($review['decision'] ?? '') !== 'approved') {
                throw new DomainException("reviews.{$key} 必须由 {$expectedName} 批准");
            }
            if (!self::validDate((string)($review['date'] ?? ''))) {
                throw new DomainException("reviews.{$key}.date 不是合法日期");
            }
        }

        if ($rehearsal && ($confirmation['rehearsal_marker'] ?? '') !== self::REHEARSAL_MARKER) {
            throw new DomainException('rehearsal_marker 不匹配');
        }
    }

    private static function captureCurrentState(): array
    {
        $database = (string)(Db::query('SELECT DATABASE() AS name')[0]['name'] ?? '');
        $company = Db::name('companies')->where('soft_delete', 0)->order('created')->find();
        $sites = Db::name('sites')
            ->whereIn('code', ['PLACE01', 'PLACE02'])
            ->where('publish', 1)
            ->where('soft_delete', 0)
            ->order('code')
            ->select()
            ->toArray();
        $employees = Db::name('employees')
            ->whereIn('name', ['俞炳星', '张晓磊', '刘恒春', '曹红', '李成辉', '付丽', '王胜林'])
            ->where('publish', 1)
            ->where('soft_delete', 0)
            ->order('name')
            ->select()
            ->toArray();
        $admin = Db::name('users')->where('username', 'admin')->where('soft_delete', 0)->find();
        $positions = Db::name('qms_positions')
            ->whereIn('code', array_keys(self::POSITION_NAMES))
            ->where('publish', 1)
            ->where('soft_delete', 0)
            ->select()
            ->toArray();
        $appointments = Db::name('employee_appointments')
            ->where('soft_delete', 0)
            ->select()
            ->toArray();
        return [
            'database' => $database,
            'company' => $company,
            'sites' => $sites,
            'employees' => $employees,
            'admin' => $admin,
            'positions' => $positions,
            'appointments' => $appointments,
            'preflight' => P0PreflightService::scan(),
            'counts' => [
                'active_employees' => (int)Db::name('employees')->where('publish', 1)->where('soft_delete', 0)->count(),
                'active_sites' => (int)Db::name('sites')->where('publish', 1)->where('soft_delete', 0)->count(),
                'active_positions' => (int)Db::name('qms_positions')->where('publish', 1)->where('soft_delete', 0)->count(),
                'active_appointments' => (int)Db::name('employee_appointments')->where('soft_delete', 0)->count(),
            ],
        ];
    }

    private static function validateCurrentState(array $confirmation, array $state, bool $rehearsal): void
    {
        if ((string)$confirmation['target_database'] !== (string)$state['database']) {
            throw new DomainException('target_database 与当前连接数据库不一致');
        }
        if ($rehearsal && (string)$state['database'] !== 'jewelry_qms_p0_r13b6') {
            throw new DomainException('演练只允许 jewelry_qms_p0_r13b6');
        }
        if ((string)$confirmation['company_id'] !== (string)($state['company']['id'] ?? '')) {
            throw new DomainException('company_id 与当前公司不一致');
        }
        $sites = array_column($state['sites'], 'name', 'code');
        if ($sites !== ['PLACE01' => '乌鲁木齐实验室', 'PLACE02' => '和田实验室']) {
            throw new DomainException('PLACE01/PLACE02 场所指纹不一致');
        }
        if (($state['preflight']['blocked'] ?? true) === true) {
            throw new DomainException('P0 只读预检阻断');
        }

        $activeNames = array_column($state['employees'], 'name');
        foreach (['俞炳星', '张晓磊', '刘恒春', '曹红', '李成辉', '付丽', '王胜林'] as $name) {
            if (count(array_keys($activeNames, $name, true)) !== 1) {
                throw new DomainException("现行活动人员 {$name} 不是唯一记录");
            }
        }

        $requestedNumbers = [
            (string)$confirmation['people']['hetian_document_controller']['employee_number'],
            (string)$confirmation['people']['hetian_equipment_manager']['employee_number'],
        ];
        $numberConflict = Db::name('employees')
            ->whereIn('employee_number', $requestedNumbers)
            ->where('soft_delete', 0)
            ->count();
        if ($numberConflict > 0) {
            throw new DomainException('待补人员 employee_number 已被占用');
        }
    }

    private static function writePackage(
        array $confirmation,
        array $state,
        string $outputDir,
        bool $rehearsal
    ): array {
        self::removeTree($outputDir);
        foreach (['sql', 'snapshot', 'evidence'] as $directory) {
            self::ensureDirectory($outputDir . '/' . $directory);
        }

        $people = self::peopleMap($confirmation, $state);
        $appointments = self::appointments($confirmation, $people);
        $semantic = [
            'confirmation' => $confirmation,
            'database' => $state['database'],
            'company_id' => (string)$state['company']['id'],
            'sites' => array_map(
                static fn (array $row): array => ['id' => $row['id'], 'code' => $row['code'], 'name' => $row['name']],
                $state['sites']
            ),
            'appointments' => $appointments,
        ];
        $semanticSha = hash('sha256', self::json(self::sortRecursive($semantic)));

        self::writeJson($outputDir . '/01-confirmation.json', $confirmation);
        self::writeJson($outputDir . '/evidence/before-state.json', self::safeState($state));
        self::writeJson($outputDir . '/evidence/after-state.expected.json', [
            'appointments' => 25,
            'admin_employee_name' => '张晓磊',
            'admin_employee_soft_delete' => 0,
            'site_codes' => ['PLACE01', 'PLACE02'],
            'liu_quality_manager' => false,
        ]);
        self::writeJson($outputDir . '/evidence/migration-diff.expected.json', [
            'allowed' => [
                'employees' => 2,
                'qms_positions' => count(array_filter(
                    array_keys(self::POSITION_NAMES),
                    static fn (string $code): bool => !in_array($code, array_column($state['positions'], 'code'), true)
                )),
                'employee_appointments' => 25,
                'users_updated' => 1,
                'unique_indexes' => 4,
            ],
            'forbidden_business_tables' => [
                'customer_complaints',
                'capas',
                'nonconformities',
                'equipments',
                'competency_records',
                'record_form_templates',
            ],
        ]);

        file_put_contents($outputDir . '/sql/00-preflight-readonly.sql', self::preflightSql($confirmation, $state));
        file_put_contents($outputDir . '/sql/10-schema-integrity.sql', self::schemaSql());
        file_put_contents(
            $outputDir . '/sql/20-organization-migration.sql',
            self::migrationSql($confirmation, $state, $people, $appointments)
        );
        file_put_contents($outputDir . '/sql/30-postflight-readonly.sql', self::postflightSql($confirmation));
        file_put_contents(
            $outputDir . '/sql/90-row-rollback.sql',
            self::rollbackSql($confirmation, $state, $people, $appointments)
        );
        file_put_contents($outputDir . '/sql/91-schema-rollback-emergency-only.sql', self::schemaRollbackSql());
        file_put_contents($outputDir . '/snapshot/README-v0.1.md', self::snapshotReadme());
        file_put_contents($outputDir . '/02-执行手册-v0.1.md', self::runbook($rehearsal));

        $manifest = [
            'package_version' => 'g-r13-b6-controlled-migration-v0.1',
            'generated_at' => date(DATE_ATOM),
            'git_commit' => trim((string)shell_exec('git rev-parse HEAD 2>/dev/null')),
            'target_database' => $state['database'],
            'company_id' => (string)$state['company']['id'],
            'site_fingerprint' => array_column($state['sites'], 'id', 'code'),
            'before_counts' => $state['counts'],
            'document_number' => $confirmation['document_number'],
            'effective_date' => $confirmation['effective_date'],
            'confirmation_sha256' => hash_file('sha256', $outputDir . '/01-confirmation.json'),
            'semantic_sha256' => $semanticSha,
            'appointment_keys' => array_column($appointments, 'appointment_key'),
            'production_apply_authorized' => false,
            'requires_separate_b7_approval' => true,
            'rehearsal' => $rehearsal,
            'execution_order' => [
                '00-preflight-readonly.sql',
                '10-schema-integrity.sql',
                '20-organization-migration.sql',
                '30-postflight-readonly.sql',
            ],
        ];
        self::writeJson($outputDir . '/00-manifest.json', $manifest);
        self::writeChecksums($outputDir);

        return [
            'output_dir' => $outputDir,
            'semantic_sha256' => $semanticSha,
            'appointment_keys' => array_column($appointments, 'appointment_key'),
            'production_apply_authorized' => false,
            'requires_separate_b7_approval' => true,
        ];
    }

    private static function peopleMap(array $confirmation, array $state): array
    {
        $map = [];
        foreach ($state['employees'] as $employee) {
            $map[(string)$employee['name']] = [
                'id' => (string)$employee['id'],
                'name' => (string)$employee['name'],
                'employee_number' => (string)$employee['employee_number'],
                'new' => false,
            ];
        }
        foreach ([
            'hetian_document_controller' => '__HETIAN_DOCUMENT_CONTROLLER__',
            'hetian_equipment_manager' => '__HETIAN_EQUIPMENT_MANAGER__',
        ] as $key => $token) {
            $person = $confirmation['people'][$key];
            $map[$token] = [
                'id' => self::stableUuid('employee:' . $person['employee_number']),
                'name' => (string)$person['formal_name'],
                'employee_number' => (string)$person['employee_number'],
                'new' => true,
            ];
        }
        return $map;
    }

    private static function appointments(array $confirmation, array $people): array
    {
        $rows = [];
        foreach (self::TARGETS as [$employeeToken, $positionCode, $siteCode, $type, $scope]) {
            $person = $people[$employeeToken];
            $location = $siteCode ?? 'GLOBAL';
            $key = implode(':', ['organization', $person['employee_number'], $positionCode, $location]);
            $rows[] = [
                'id' => self::stableUuid('appointment:' . $key),
                'appointment_key' => $key,
                'employee_id' => $person['id'],
                'employee_name' => $person['name'],
                'employee_number' => $person['employee_number'],
                'position_code' => $positionCode,
                'position_name' => self::POSITION_NAMES[$positionCode],
                'site_code' => $siteCode,
                'appointment_type' => $type,
                'appointment_scope' => $scope,
                'source_document_number' => $confirmation['document_number'],
            ];
        }
        return $rows;
    }

    private static function preflightSql(array $confirmation, array $state): string
    {
        $db = self::sql((string)$confirmation['target_database']);
        $company = self::sql((string)$confirmation['company_id']);
        $adminEmployee = self::sql((string)($state['admin']['employee_id'] ?? ''));
        return <<<SQL
-- G-R13-B6 只读预检；不修改数据。
DELIMITER //
DROP PROCEDURE IF EXISTS qms_b6_preflight//
CREATE PROCEDURE qms_b6_preflight()
BEGIN
    IF DATABASE() <> {$db} THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'B6 target database mismatch';
    END IF;
    IF (SELECT COUNT(*) FROM companies WHERE id = {$company} AND soft_delete = 0) <> 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'B6 company fingerprint mismatch';
    END IF;
    IF (SELECT COUNT(*) FROM sites WHERE code IN ('PLACE01','PLACE02') AND publish = 1 AND soft_delete = 0) <> 2 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'B6 site fingerprint mismatch';
    END IF;
    IF (SELECT employee_id FROM users WHERE username = 'admin' AND soft_delete = 0 LIMIT 1) <> {$adminEmployee} THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'B6 admin fingerprint mismatch';
    END IF;
    SELECT DATABASE() database_name, 'read_only' mode, 'pass' result;
END//
CALL qms_b6_preflight()//
DROP PROCEDURE qms_b6_preflight//
DELIMITER ;
SQL;
    }

    private static function schemaSql(): string
    {
        return (string)file_get_contents(dirname(__DIR__, 2) . '/database/migrations/20260717_p0_record_integrity.sql');
    }

    private static function migrationSql(
        array $confirmation,
        array $state,
        array $people,
        array $appointments
    ): string {
        $db = self::sql((string)$confirmation['target_database']);
        $company = self::sql((string)$confirmation['company_id']);
        $document = self::sql((string)$confirmation['document_number']);
        $effective = self::sql((string)$confirmation['effective_date']);
        $excerpt = self::sql((string)$confirmation['source_excerpt']);
        $activeZhang = array_values(array_filter(
            $state['employees'],
            static fn (array $row): bool => $row['name'] === '张晓磊'
        ))[0];

        $statements = [];
        foreach (['__HETIAN_DOCUMENT_CONTROLLER__', '__HETIAN_EQUIPMENT_MANAGER__'] as $token) {
            $person = $people[$token];
            $siteId = self::sql((string)array_column($state['sites'], 'id', 'code')['PLACE02']);
            $statements[] = sprintf(
                "    INSERT INTO employees (id, company_id, primary_site_id, employee_number, name, publish, soft_delete, created, modified)\n"
                . "    SELECT %s, %s, %s, %s, %s, 1, 0, NOW(), NOW()\n"
                . "    WHERE NOT EXISTS (SELECT 1 FROM employees WHERE (id = %s OR employee_number = %s OR name = %s) AND soft_delete = 0);",
                self::sql($person['id']),
                $company,
                $siteId,
                self::sql($person['employee_number']),
                self::sql($person['name']),
                self::sql($person['id']),
                self::sql($person['employee_number']),
                self::sql($person['name'])
            );
        }
        foreach (self::POSITION_NAMES as $code => $name) {
            $positionId = self::stableUuid('position:' . $code);
            $statements[] = sprintf(
                "    INSERT INTO qms_positions (id, company_id, code, name, source, review_status, publish, soft_delete, created, modified)\n"
                . "    SELECT %s, %s, %s, %s, 'controlled_migration', 'published', 1, 0, NOW(), NOW()\n"
                . "    WHERE NOT EXISTS (SELECT 1 FROM qms_positions WHERE code = %s AND publish = 1 AND soft_delete = 0);",
                self::sql($positionId),
                $company,
                self::sql($code),
                self::sql($name),
                self::sql($code)
            );
        }
        foreach ($appointments as $row) {
            $siteExpression = $row['site_code'] === null
                ? 'NULL'
                : '(SELECT id FROM sites WHERE code = ' . self::sql($row['site_code']) . ' AND soft_delete = 0 LIMIT 1)';
            $statements[] = sprintf(
                "    INSERT INTO employee_appointments (\n"
                . "        id, company_id, employee_id, position_id, site_id, appointment_key,\n"
                . "        appointment_type, position_name, appointment_scope, appointed_at,\n"
                . "        source_document_number, source_excerpt, source_kind, status,\n"
                . "        publish, soft_delete, created, modified\n"
                . "    )\n"
                . "    SELECT %s, %s,\n"
                . "        (SELECT id FROM employees WHERE employee_number = %s AND publish = 1 AND soft_delete = 0 LIMIT 1),\n"
                . "        (SELECT id FROM qms_positions WHERE code = %s AND publish = 1 AND soft_delete = 0 LIMIT 1),\n"
                . "        %s, %s, %s, %s, %s, %s, %s, %s, 'corporate_evidence', 'active', 1, 0, NOW(), NOW()\n"
                . "    WHERE NOT EXISTS (SELECT 1 FROM employee_appointments WHERE appointment_key = %s AND soft_delete = 0);",
                self::sql($row['id']),
                $company,
                self::sql($row['employee_number']),
                self::sql($row['position_code']),
                $siteExpression,
                self::sql($row['appointment_key']),
                self::sql($row['appointment_type']),
                self::sql($row['position_name']),
                self::sql($row['appointment_scope']),
                $effective,
                $document,
                $excerpt,
                self::sql($row['appointment_key'])
            );
        }
        $body = implode("\n\n", $statements);
        $keys = implode(',', array_map(
            static fn (array $row): string => self::sql($row['appointment_key']),
            $appointments
        ));
        $zhangId = self::sql((string)$activeZhang['id']);

        return <<<SQL
-- G-R13-B6 组织数据迁移；只允许在清单指定数据库执行。
DELIMITER //
DROP PROCEDURE IF EXISTS qms_b6_apply_organization//
CREATE PROCEDURE qms_b6_apply_organization()
BEGIN
    IF DATABASE() <> {$db} THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'B6 target database mismatch';
    END IF;
    START TRANSACTION;

{$body}

    UPDATE users SET employee_id = {$zhangId}, modified = NOW()
    WHERE username = 'admin' AND soft_delete = 0;

    IF (SELECT COUNT(*) FROM employee_appointments WHERE appointment_key IN ({$keys}) AND soft_delete = 0) <> 25 THEN
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'B6 appointment assertion failed';
    END IF;
    IF (
        SELECT COUNT(*) FROM employee_appointments ea
        JOIN employees e ON e.id = ea.employee_id
        JOIN qms_positions p ON p.id = ea.position_id
        WHERE e.name = '刘恒春' AND p.code = 'quality_manager'
          AND ea.status = 'active' AND ea.soft_delete = 0
    ) <> 0 THEN
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'B6 least privilege assertion failed';
    END IF;
    COMMIT;
END//
CALL qms_b6_apply_organization()//
DROP PROCEDURE qms_b6_apply_organization//
DELIMITER ;
SQL;
    }

    private static function postflightSql(array $confirmation): string
    {
        $document = self::sql((string)$confirmation['document_number']);
        return <<<SQL
-- G-R13-B6 迁移后只读核对。
SELECT 'appointments' item, COUNT(*) count
FROM employee_appointments
WHERE source_document_number = {$document} AND status = 'active' AND soft_delete = 0
UNION ALL
SELECT 'active_sites', COUNT(*) FROM sites WHERE publish = 1 AND soft_delete = 0
UNION ALL
SELECT 'liu_quality_manager', COUNT(*)
FROM employee_appointments ea
JOIN employees e ON e.id = ea.employee_id
JOIN qms_positions p ON p.id = ea.position_id
WHERE e.name = '刘恒春' AND p.code = 'quality_manager'
  AND ea.status = 'active' AND ea.soft_delete = 0;
SQL;
    }

    private static function rollbackSql(
        array $confirmation,
        array $state,
        array $people,
        array $appointments
    ): string {
        $db = self::sql((string)$confirmation['target_database']);
        $keys = implode(',', array_map(
            static fn (array $row): string => self::sql($row['appointment_key']),
            $appointments
        ));
        $adminBefore = self::sql((string)($state['admin']['employee_id'] ?? ''));
        $newEmployeeIds = implode(',', array_map(
            static fn (string $token): string => self::sql($people[$token]['id']),
            ['__HETIAN_DOCUMENT_CONTROLLER__', '__HETIAN_EQUIPMENT_MANAGER__']
        ));
        $newPositionCodes = array_values(array_filter(
            array_keys(self::POSITION_NAMES),
            static fn (string $code): bool => !in_array($code, array_column($state['positions'], 'code'), true)
        ));
        $positionClause = $newPositionCodes === []
            ? "'__none__'"
            : implode(',', array_map([self::class, 'sql'], $newPositionCodes));

        return <<<SQL
-- G-R13-B6 行级回退；只按本包固定键和 before-state 回退。
DELIMITER //
DROP PROCEDURE IF EXISTS qms_b6_rollback_organization//
CREATE PROCEDURE qms_b6_rollback_organization()
BEGIN
    IF DATABASE() <> {$db} THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'B6 target database mismatch';
    END IF;
    START TRANSACTION;
    DELETE FROM employee_appointments WHERE appointment_key IN ({$keys});
    UPDATE users SET employee_id = {$adminBefore}, modified = NOW()
    WHERE username = 'admin' AND soft_delete = 0;
    DELETE FROM employees
    WHERE id IN ({$newEmployeeIds})
      AND NOT EXISTS (SELECT 1 FROM users u WHERE u.employee_id = employees.id)
      AND NOT EXISTS (SELECT 1 FROM employee_appointments ea WHERE ea.employee_id = employees.id);
    DELETE FROM qms_positions
    WHERE code IN ({$positionClause})
      AND NOT EXISTS (SELECT 1 FROM employee_appointments ea WHERE ea.position_id = qms_positions.id);
    COMMIT;
END//
CALL qms_b6_rollback_organization()//
DROP PROCEDURE qms_b6_rollback_organization//
DELIMITER ;
SQL;
    }

    private static function schemaRollbackSql(): string
    {
        return <<<'SQL'
-- EMERGENCY ONLY：需另行批准并重新快照后执行。
ALTER TABLE customer_complaints DROP INDEX uq_complaint_company_number;
ALTER TABLE capas DROP INDEX uq_capa_company_number;
ALTER TABLE nonconformities DROP INDEX uq_nc_company_number;
ALTER TABLE capas DROP INDEX uq_capa_company_source_record;
SQL;
    }

    private static function snapshotReadme(): string
    {
        return <<<'MD'
# B6 快照说明

执行任何写入前，使用 `mysqldump --single-transaction --quick` 生成完整快照，并单独导出
`employees`、`users`、`qms_positions`、`employee_appointments`。快照不得提交 Git、不得进入聊天，
必须生成 SHA256，并在另一临时库完成恢复验证。
MD;
    }

    private static function runbook(bool $rehearsal): string
    {
        $mode = $rehearsal ? '隔离演练' : '候选包生成';
        return <<<MD
# G-R13-B6 执行手册

模式：{$mode}

1. 校验 `SHA256SUMS.txt`。
2. 运行 `00-preflight-readonly.sql`。
3. 完成并验证数据库快照。
4. 运行 `10-schema-integrity.sql`。
5. 运行 `20-organization-migration.sql`。
6. 运行 `30-postflight-readonly.sql`。
7. 失败时先运行 `90-row-rollback.sql`；无法证明完整恢复时使用已验证快照。

本包 `production_apply_authorized=false`，不得据此执行现行库迁移；现行执行必须另行批准 B7。
MD;
    }

    private static function safeState(array $state): array
    {
        return [
            'database' => $state['database'],
            'company' => ['id' => $state['company']['id'], 'name' => $state['company']['name']],
            'sites' => array_map(
                static fn (array $row): array => ['id' => $row['id'], 'code' => $row['code'], 'name' => $row['name']],
                $state['sites']
            ),
            'employees' => array_map(
                static fn (array $row): array => [
                    'id' => $row['id'],
                    'employee_number' => $row['employee_number'],
                    'name' => $row['name'],
                ],
                $state['employees']
            ),
            'admin' => [
                'id' => $state['admin']['id'] ?? '',
                'employee_id' => $state['admin']['employee_id'] ?? '',
            ],
            'positions' => array_map(
                static fn (array $row): array => ['id' => $row['id'], 'code' => $row['code'], 'name' => $row['name']],
                $state['positions']
            ),
            'counts' => $state['counts'],
            'preflight' => $state['preflight'],
        ];
    }

    private static function writeChecksums(string $outputDir): void
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($outputDir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getFilename() === 'SHA256SUMS.txt') {
                continue;
            }
            $absolute = $file->getPathname();
            $relative = ltrim(str_replace($outputDir, '', $absolute), DIRECTORY_SEPARATOR);
            $files[$relative] = hash_file('sha256', $absolute);
        }
        ksort($files);
        $lines = [];
        foreach ($files as $relative => $hash) {
            $lines[] = $hash . '  ' . $relative;
        }
        file_put_contents($outputDir . '/SHA256SUMS.txt', implode("\n", $lines) . "\n");
    }

    private static function writeJson(string $path, array $data): void
    {
        file_put_contents($path, self::json($data) . "\n");
    }

    private static function json(array $data): string
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            throw new RuntimeException('JSON 编码失败');
        }
        return $json;
    }

    private static function sortRecursive(array $value): array
    {
        if (!array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = self::sortRecursive($item);
            }
        }
        return $value;
    }

    private static function sql(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    private static function stableUuid(string $value): string
    {
        $hash = hash('sha256', 'g-r13-b6:' . $value);
        return sprintf(
            '%s-%s-4%s-8%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 13, 3),
            substr($hash, 17, 3),
            substr($hash, 20, 12)
        );
    }

    private static function validDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private static function ensureDirectory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
            throw new RuntimeException("无法创建目录：{$path}");
        }
    }

    private static function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
            $item = $path . DIRECTORY_SEPARATOR . $entry;
            is_dir($item) ? self::removeTree($item) : unlink($item);
        }
        rmdir($path);
    }
}
