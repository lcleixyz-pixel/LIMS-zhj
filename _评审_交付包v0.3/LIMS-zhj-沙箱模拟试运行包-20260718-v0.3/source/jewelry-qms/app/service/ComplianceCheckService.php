<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Db;

class ComplianceCheckService
{
    private const STATUSES = ['pass', 'fail', 'warning', 'insufficient_data', 'not_applicable'];

    public static function runFullAssessment(string $companyId, string $triggerType = 'manual', ?string $triggeredBy = null): array
    {
        self::seedDefaultChecks($companyId);

        $checks = Db::name('compliance_checks')
            ->where('company_id', $companyId)
            ->where('is_active', 1)
            ->where('soft_delete', 0)
            ->order('sort_order', 'asc')
            ->select()
            ->toArray();

        return Db::transaction(function () use ($checks, $companyId, $triggerType, $triggeredBy): array {
            $registry = self::checkRegistry();
            $snapshotId = qms_uuid();
            $now = date('Y-m-d H:i:s');
            $results = [];

            foreach ($checks as $check) {
                $handler = $registry[(string)$check['check_code']] ?? null;
                $result = is_callable($handler)
                    ? call_user_func($handler, $companyId)
                    : self::buildResult('not_applicable', 0, 0, 0, [], '未配置自动判定方法');

                $results[] = ['check' => $check, 'result' => $result];

                Db::name('compliance_check_results')->insert([
                    'id' => qms_uuid(),
                    'snapshot_id' => $snapshotId,
                    'check_id' => (string)$check['id'],
                    'check_code' => (string)$check['check_code'],
                    'dimension' => (string)$check['dimension'],
                    'status' => (string)$result['status'],
                    'score' => $result['score'],
                    'total_checked' => (int)$result['total_checked'],
                    'fail_count' => (int)$result['fail_count'],
                    'warning_count' => (int)$result['warning_count'],
                    'fail_items' => $result['fail_items'] !== []
                        ? json_encode($result['fail_items'], JSON_UNESCAPED_UNICODE)
                        : null,
                    'checked_at' => $now,
                ]);
            }

            $dimensionScores = self::calcDimensionScores($results);
            $totalScore = self::calcTotalScore($dimensionScores);
            $summary = self::calcSummary($results);

            Db::name('compliance_snapshots')->insert([
                'id' => $snapshotId,
                'company_id' => $companyId,
                'snapshot_time' => $now,
                'trigger_type' => in_array($triggerType, ['scheduled', 'manual'], true) ? $triggerType : 'manual',
                'total_score' => $totalScore,
                'dimension_scores' => json_encode($dimensionScores, JSON_UNESCAPED_UNICODE),
                'summary' => json_encode($summary, JSON_UNESCAPED_UNICODE),
                'created_by' => $triggeredBy,
            ]);

            return [
                'snapshot_id' => $snapshotId,
                'snapshot_time' => $now,
                'total_score' => $totalScore,
                'dimension_scores' => $dimensionScores,
                'summary' => $summary,
                'gaps' => self::getGapsForSnapshot($snapshotId),
            ];
        });
    }

    public static function getLatestScorecard(string $companyId): ?array
    {
        $snapshot = Db::name('compliance_snapshots')
            ->where('company_id', $companyId)
            ->order('snapshot_time', 'desc')
            ->order('id', 'desc')
            ->find();

        if (!$snapshot) {
            return null;
        }

        return [
            'snapshot_id' => (string)$snapshot['id'],
            'snapshot_time' => (string)$snapshot['snapshot_time'],
            'total_score' => (float)$snapshot['total_score'],
            'dimension_scores' => self::decodeJson((string)$snapshot['dimension_scores']),
            'summary' => self::decodeJson((string)$snapshot['summary']),
            'results' => self::getResultsForSnapshot((string)$snapshot['id']),
        ];
    }

    public static function getGapsByDimension(string $companyId, string $dimension): array
    {
        $snapshotId = self::latestSnapshotId($companyId);
        if ($snapshotId === null) {
            return [];
        }

        return array_values(array_filter(
            self::getGapsForSnapshot($snapshotId),
            static fn (array $gap): bool => (string)$gap['dimension'] === $dimension
        ));
    }

    public static function getAllGaps(string $companyId): array
    {
        $snapshotId = self::latestSnapshotId($companyId);
        return $snapshotId ? self::getGapsForSnapshot($snapshotId) : [];
    }

    public static function scoreTrend(string $companyId, int $count = 10): array
    {
        $rows = Db::name('compliance_snapshots')
            ->where('company_id', $companyId)
            ->order('snapshot_time', 'desc')
            ->limit(max(1, $count))
            ->select()
            ->toArray();
        $rows = array_reverse($rows);

        $labels = [];
        $scores = [];
        $dimensions = [];
        foreach ($rows as $row) {
            $labels[] = date('m-d H:i', strtotime((string)$row['snapshot_time']));
            $scores[] = (float)$row['total_score'];
            foreach (self::decodeJson((string)$row['dimension_scores']) as $dimension => $score) {
                if (!isset($dimensions[$dimension])) {
                    $dimensions[$dimension] = [];
                }
                $dimensions[$dimension][] = $score === null ? null : (float)$score;
            }
        }

        return ['labels' => $labels, 'scores' => $scores, 'dimensions' => $dimensions];
    }

    public static function seedDefaultChecks(string $companyId): int
    {
        $created = 0;
        $now = date('Y-m-d H:i:s');
        foreach (self::defaultChecks() as $check) {
            $existing = Db::name('compliance_checks')
                ->where('company_id', $companyId)
                ->where('check_code', $check['check_code'])
                ->find();

            $payload = array_merge($check, [
                'company_id' => $companyId,
                'modified' => $now,
                'soft_delete' => 0,
                'is_active' => 1,
            ]);

            if ($existing) {
                Db::name('compliance_checks')->where('id', (string)$existing['id'])->update($payload);
                continue;
            }

            $payload['id'] = qms_uuid();
            $payload['created'] = $now;
            Db::name('compliance_checks')->insert($payload);
            $created++;
        }

        return $created;
    }

    public static function dimensionLabels(): array
    {
        return [
            'personnel' => '人员 (6.2)',
            'equipment' => '设备 (6.4)',
            'material' => '标准物质 (6.5)',
            'method' => '方法 (7.2)',
            'environment' => '环境 (6.3)',
            'document' => '文件 (8.2-8.3)',
            'record' => '记录 (7.5/8.4)',
            'management' => '管理体系 (8.5-8.9)',
        ];
    }

    private static function checkRegistry(): array
    {
        return [
            'equip_calibration_valid' => [self::class, 'checkEquipCalibration'],
            'equip_has_authorization' => [self::class, 'checkEquipAuthorization'],
            'personnel_competency_valid' => [self::class, 'checkPersonnelCompetency'],
            'personnel_certificate_valid' => [self::class, 'checkPersonnelCertificate'],
            'personnel_has_training' => [self::class, 'checkPersonnelTraining'],
            'refmat_not_expired' => [self::class, 'checkRefMatExpiry'],
            'doc_review_not_overdue' => [self::class, 'checkDocReview'],
            'doc_has_traceability' => [self::class, 'checkDocTraceability'],
            'procedure_has_record_template' => [self::class, 'checkProcedureRecordTemplate'],
            'procedure_has_record_instance' => [self::class, 'checkProcedureRecordInstance'],
            'capa_not_overdue' => [self::class, 'checkCapaOverdue'],
            'findings_not_stale' => [self::class, 'checkFindingsStale'],
            'management_review_current' => [self::class, 'checkManagementReview'],
            'internal_audit_current' => [self::class, 'checkInternalAudit'],
            'clause_has_element' => [self::class, 'checkClauseElement'],
            'element_has_document' => [self::class, 'checkElementDocument'],
        ];
    }

    private static function defaultChecks(): array
    {
        return [
            ['check_code' => 'equip_calibration_valid', 'dimension' => 'equipment', 'severity' => 'critical', 'clause_number' => '6.4.6', 'element_key' => 'equipment', 'check_name' => '设备校准有效性', 'check_description' => '在用且需校准设备的下次校准日期不得过期。', 'weight' => 1.00, 'suggestion_template' => '安排校准或暂停使用过期设备。', 'sort_order' => 10],
            ['check_code' => 'equip_has_authorization', 'dimension' => 'equipment', 'severity' => 'major', 'clause_number' => '6.4.2', 'element_key' => 'equipment', 'check_name' => '设备授权使用人', 'check_description' => '在用设备至少应有一名有效授权使用人。', 'weight' => 1.00, 'suggestion_template' => '补充设备授权使用人记录。', 'sort_order' => 20],
            ['check_code' => 'personnel_competency_valid', 'dimension' => 'personnel', 'severity' => 'critical', 'clause_number' => '6.2.5', 'element_key' => 'personnel', 'check_name' => '人员能力确认有效性', 'check_description' => '能力确认记录应为合格且未过期。', 'weight' => 1.00, 'suggestion_template' => '补充或更新能力确认记录。', 'sort_order' => 30],
            ['check_code' => 'personnel_certificate_valid', 'dimension' => 'personnel', 'severity' => 'major', 'clause_number' => '6.2.2', 'element_key' => 'personnel', 'check_name' => '人员资质证书有效性', 'check_description' => '在用人员资质证书不得过期。', 'weight' => 1.00, 'suggestion_template' => '更新人员资质证书或调整授权范围。', 'sort_order' => 40],
            ['check_code' => 'personnel_has_training', 'dimension' => 'personnel', 'severity' => 'major', 'clause_number' => '6.2.3', 'element_key' => 'personnel', 'check_name' => '人员培训记录覆盖', 'check_description' => '在册人员应有培训记录。', 'weight' => 1.00, 'suggestion_template' => '补充培训记录或安排培训。', 'sort_order' => 50],
            ['check_code' => 'refmat_not_expired', 'dimension' => 'material', 'severity' => 'critical', 'clause_number' => '6.5', 'element_key' => 'metrological_traceability', 'check_name' => '标准物质有效性', 'check_description' => '在用标准物质应在有效期内。', 'weight' => 1.00, 'suggestion_template' => '补充标准物质台账或更换过期标准物质。', 'sort_order' => 60],
            ['check_code' => 'doc_review_not_overdue', 'dimension' => 'document', 'severity' => 'major', 'clause_number' => '8.3', 'element_key' => 'document_control', 'check_name' => '文件评审有效性', 'check_description' => '已发布文件的评审日期不得过期。', 'weight' => 1.00, 'suggestion_template' => '安排文件定期评审。', 'sort_order' => 70],
            ['check_code' => 'doc_has_traceability', 'dimension' => 'document', 'severity' => 'minor', 'clause_number' => '8.2', 'element_key' => 'management_system_documents', 'check_name' => '文件要素追溯关联', 'check_description' => '已发布文件应关联至少一个体系要素。', 'weight' => 1.00, 'suggestion_template' => '在体系策划中补充文件与要素映射。', 'sort_order' => 80],
            ['check_code' => 'procedure_has_record_template', 'dimension' => 'record', 'severity' => 'major', 'clause_number' => '8.4', 'element_key' => 'record_control', 'check_name' => '程序文件记录模板覆盖', 'check_description' => '程序文件或作业指导书应关联所需记录表格。', 'weight' => 1.00, 'suggestion_template' => '补充文件块到记录表格模板的追溯。', 'sort_order' => 90],
            ['check_code' => 'procedure_has_record_instance', 'dimension' => 'record', 'severity' => 'major', 'clause_number' => '7.5', 'element_key' => 'technical_records', 'check_name' => '运行记录实例覆盖', 'check_description' => '已有记录模板应产生已锁定的运行记录。', 'weight' => 1.00, 'suggestion_template' => '填写并锁定对应运行记录。', 'sort_order' => 100],
            ['check_code' => 'capa_not_overdue', 'dimension' => 'management', 'severity' => 'major', 'clause_number' => '8.7', 'element_key' => 'corrective_action', 'check_name' => 'CAPA 超期检查', 'check_description' => '不应存在超期未关闭 CAPA。', 'weight' => 1.00, 'suggestion_template' => '跟进超期 CAPA 并完成验证关闭。', 'sort_order' => 110],
            ['check_code' => 'findings_not_stale', 'dimension' => 'management', 'severity' => 'minor', 'clause_number' => '8.8', 'element_key' => 'internal_audit', 'check_name' => '审核发现长期未关闭', 'check_description' => '审核发现不应超过 90 天未关闭。', 'weight' => 1.00, 'suggestion_template' => '推进审核发现整改或关联 CAPA。', 'sort_order' => 120],
            ['check_code' => 'management_review_current', 'dimension' => 'management', 'severity' => 'major', 'clause_number' => '8.9', 'element_key' => 'management_review', 'check_name' => '年度管理评审', 'check_description' => '近 12 个月应至少完成一次管理评审。', 'weight' => 1.00, 'suggestion_template' => '组织并完成管理评审。', 'sort_order' => 130],
            ['check_code' => 'internal_audit_current', 'dimension' => 'management', 'severity' => 'major', 'clause_number' => '8.8', 'element_key' => 'internal_audit', 'check_name' => '年度内部审核', 'check_description' => '近 12 个月应至少完成一次内部审核。', 'weight' => 1.00, 'suggestion_template' => '组织并完成内部审核。', 'sort_order' => 140],
            ['check_code' => 'clause_has_element', 'dimension' => 'document', 'severity' => 'minor', 'clause_number' => '4-8', 'element_key' => null, 'check_name' => '条款到要素覆盖', 'check_description' => '适用条款至少 80% 应映射到体系要素。', 'weight' => 1.00, 'suggestion_template' => '补充条款与要素映射。', 'sort_order' => 150],
            ['check_code' => 'element_has_document', 'dimension' => 'document', 'severity' => 'major', 'clause_number' => '8.2', 'element_key' => null, 'check_name' => '要素到文件覆盖', 'check_description' => '适用体系要素应关联至少一份体系文件。', 'weight' => 1.00, 'suggestion_template' => '补充要素与文件关联。', 'sort_order' => 160],
        ];
    }

    private static function checkEquipCalibration(string $companyId): array
    {
        $query = Db::name('equipments')
            ->where('company_id', $companyId)
            ->where('soft_delete', 0)
            ->where('status', 'active')
            ->where('calibration_required', 1);

        $total = (clone $query)->count();
        if ($total === 0) {
            return self::buildResult('insufficient_data', 0, 0, 0, [], '系统中无需校准设备记录，请确认设备台账是否已录入');
        }

        $today = date('Y-m-d');
        $warningDate = date('Y-m-d', strtotime('+30 days'));
        $failItems = (clone $query)->where(function ($q) use ($today) {
            $q->whereNull('next_calibration_date')->whereOr('next_calibration_date', '<', $today);
        })->field('id,name,equipment_number,next_calibration_date')->select()->toArray();

        $warningCount = (clone $query)
            ->where('next_calibration_date', '>=', $today)
            ->where('next_calibration_date', '<=', $warningDate)
            ->count();

        return self::buildResult(
            count($failItems) > 0 ? 'fail' : ($warningCount > 0 ? 'warning' : 'pass'),
            (int)$total,
            count($failItems),
            (int)$warningCount,
            self::formatEquipmentDateItems($failItems, 'next_calibration_date', '校准')
        );
    }

    private static function checkEquipAuthorization(string $companyId): array
    {
        $equipments = Db::name('equipments')
            ->where('company_id', $companyId)
            ->where('soft_delete', 0)
            ->where('status', 'active')
            ->field('id,name,equipment_number')
            ->select()
            ->toArray();
        if ($equipments === []) {
            return self::buildResult('insufficient_data', 0, 0, 0, [], '系统中无在用设备');
        }

        $today = date('Y-m-d');
        $failItems = [];
        foreach ($equipments as $equipment) {
            $count = Db::name('equipment_authorizations')
                ->where('company_id', $companyId)
                ->where('equipment_id', (string)$equipment['id'])
                ->where('soft_delete', 0)
                ->where('status', 'active')
                ->where(function ($q) use ($today) {
                    $q->whereNull('valid_until')->whereOr('valid_until', '>=', $today);
                })
                ->count();
            if ((int)$count === 0) {
                $failItems[] = self::item((string)$equipment['id'], (string)$equipment['name'], (string)$equipment['equipment_number'], '无有效授权使用人');
            }
        }

        return self::buildResult(count($failItems) > 0 ? 'fail' : 'pass', count($equipments), count($failItems), 0, $failItems);
    }

    private static function checkPersonnelCompetency(string $companyId): array
    {
        $records = Db::name('competency_records')
            ->alias('c')
            ->leftJoin('employees e', 'e.id = c.employee_id AND e.company_id = c.company_id AND e.soft_delete = 0')
            ->where('c.company_id', $companyId)
            ->where('c.soft_delete', 0)
            ->field('c.id,c.test_item,c.method_standard,c.result,c.valid_until,e.name employee_name')
            ->select()
            ->toArray();
        if ($records === []) {
            return self::buildResult('insufficient_data', 0, 0, 0, [], '系统中无能力确认记录');
        }

        $today = date('Y-m-d');
        $failItems = [];
        foreach ($records as $record) {
            if ((string)$record['result'] !== 'qualified' || ($record['valid_until'] && (string)$record['valid_until'] < $today)) {
                $reason = (string)$record['result'] !== 'qualified' ? '能力确认结果不是合格' : '能力确认已过期';
                $failItems[] = self::item((string)$record['id'], (string)($record['employee_name'] ?: $record['test_item']), (string)$record['method_standard'], $reason);
            }
        }

        return self::buildResult(count($failItems) > 0 ? 'fail' : 'pass', count($records), count($failItems), 0, $failItems);
    }

    private static function checkPersonnelCertificate(string $companyId): array
    {
        $records = Db::name('employee_certificates')
            ->alias('c')
            ->leftJoin('employees e', 'e.id = c.employee_id AND e.company_id = c.company_id AND e.soft_delete = 0')
            ->where('c.company_id', $companyId)
            ->where('c.soft_delete', 0)
            ->field('c.id,c.certificate_type,c.certificate_number,c.status,c.valid_until,e.name employee_name')
            ->select()
            ->toArray();
        if ($records === []) {
            return self::buildResult('insufficient_data', 0, 0, 0, [], '系统中无人员资质证书记录');
        }

        $today = date('Y-m-d');
        $failItems = [];
        $warningCount = 0;
        foreach ($records as $record) {
            $validUntil = (string)($record['valid_until'] ?? '');
            if ((string)$record['status'] !== 'active' || ($validUntil !== '' && $validUntil < $today)) {
                $failItems[] = self::item((string)$record['id'], (string)($record['employee_name'] ?: $record['certificate_type']), (string)$record['certificate_number'], '资质证书无效或已过期');
                continue;
            }
            if ($validUntil !== '' && $validUntil <= date('Y-m-d', strtotime('+30 days'))) {
                $warningCount++;
            }
        }

        return self::buildResult(count($failItems) > 0 ? 'fail' : ($warningCount > 0 ? 'warning' : 'pass'), count($records), count($failItems), $warningCount, $failItems);
    }

    private static function checkPersonnelTraining(string $companyId): array
    {
        $employees = Db::name('employees')
            ->where('company_id', $companyId)
            ->where('publish', 1)
            ->where('soft_delete', 0)
            ->field('id,name,employee_number')
            ->select()
            ->toArray();
        if ($employees === []) {
            return self::buildResult('insufficient_data', 0, 0, 0, [], '系统中无在册人员');
        }

        $failItems = [];
        foreach ($employees as $employee) {
            $count = Db::name('training_records')
                ->alias('r')
                ->join('trainings t', 't.id = r.training_id')
                ->where('t.company_id', $companyId)
                ->where('t.soft_delete', 0)
                ->where('r.employee_id', (string)$employee['id'])
                ->where('r.soft_delete', 0)
                ->where('r.attendance', 'present')
                ->count();
            if ((int)$count === 0) {
                $failItems[] = self::item((string)$employee['id'], (string)$employee['name'], (string)$employee['employee_number'], '无培训记录');
            }
        }

        return self::buildResult(count($failItems) > 0 ? 'fail' : 'pass', count($employees), count($failItems), 0, $failItems);
    }

    private static function checkRefMatExpiry(string $companyId): array
    {
        $records = Db::name('reference_materials')
            ->where('company_id', $companyId)
            ->where('soft_delete', 0)
            ->where('status', 'active')
            ->field('id,name,code,valid_until')
            ->select()
            ->toArray();
        if ($records === []) {
            return self::buildResult('insufficient_data', 0, 0, 0, [], '标准物质台账为空或无在用标准物质');
        }

        $today = date('Y-m-d');
        $warningDate = date('Y-m-d', strtotime('+30 days'));
        $failItems = [];
        $warningCount = 0;
        foreach ($records as $record) {
            $validUntil = (string)($record['valid_until'] ?? '');
            if ($validUntil === '' || $validUntil < $today) {
                $failItems[] = self::item((string)$record['id'], (string)$record['name'], (string)$record['code'], $validUntil === '' ? '未设置有效期' : '标准物质已过期');
                continue;
            }
            if ($validUntil <= $warningDate) {
                $warningCount++;
            }
        }

        return self::buildResult(count($failItems) > 0 ? 'fail' : ($warningCount > 0 ? 'warning' : 'pass'), count($records), count($failItems), $warningCount, $failItems);
    }

    private static function checkDocReview(string $companyId): array
    {
        $records = Db::name('documents')
            ->where('company_id', $companyId)
            ->where('soft_delete', 0)
            ->where('status', 'published')
            ->field('id,title,doc_number,review_date')
            ->select()
            ->toArray();
        if ($records === []) {
            return self::buildResult('insufficient_data', 0, 0, 0, [], '系统中无已发布文件');
        }

        $today = date('Y-m-d');
        $warningDate = date('Y-m-d', strtotime('+30 days'));
        $failItems = [];
        $warningCount = 0;
        foreach ($records as $record) {
            $reviewDate = (string)($record['review_date'] ?? '');
            if ($reviewDate === '' || $reviewDate < $today) {
                $failItems[] = self::item((string)$record['id'], (string)$record['title'], (string)$record['doc_number'], $reviewDate === '' ? '未设置评审日期' : '文件评审已过期');
                continue;
            }
            if ($reviewDate <= $warningDate) {
                $warningCount++;
            }
        }

        return self::buildResult(count($failItems) > 0 ? 'fail' : ($warningCount > 0 ? 'warning' : 'pass'), count($records), count($failItems), $warningCount, $failItems);
    }

    private static function checkDocTraceability(string $companyId): array
    {
        $docs = Db::name('documents')
            ->where('company_id', $companyId)
            ->where('soft_delete', 0)
            ->where('status', 'published')
            ->field('id,title,doc_number')
            ->select()
            ->toArray();
        if ($docs === []) {
            return self::buildResult('insufficient_data', 0, 0, 0, [], '系统中无已发布文件');
        }

        $failItems = [];
        foreach ($docs as $doc) {
            $count = Db::name('qms_element_documents')
                ->where('company_id', $companyId)
                ->where('document_id', (string)$doc['id'])
                ->where('soft_delete', 0)
                ->count();
            if ((int)$count === 0) {
                $failItems[] = self::item((string)$doc['id'], (string)$doc['title'], (string)$doc['doc_number'], '未建立要素追溯关联');
            }
        }

        return self::buildResult(count($failItems) > 0 ? 'fail' : 'pass', count($docs), count($failItems), 0, array_slice($failItems, 0, 20));
    }

    private static function checkProcedureRecordTemplate(string $companyId): array
    {
        $docs = self::publishedProcedureDocuments($companyId);
        if ($docs === []) {
            return self::buildResult('insufficient_data', 0, 0, 0, [], '系统中无已发布程序文件或作业指导书');
        }

        $failItems = [];
        foreach ($docs as $doc) {
            if (self::recordTemplateIdsForDocument($companyId, (string)$doc['id']) === []) {
                $failItems[] = self::item((string)$doc['id'], (string)$doc['title'], (string)$doc['doc_number'], '未关联记录表格模板');
            }
        }

        return self::buildResult(count($failItems) > 0 ? 'fail' : 'pass', count($docs), count($failItems), 0, array_slice($failItems, 0, 20));
    }

    private static function checkProcedureRecordInstance(string $companyId): array
    {
        $templateIds = [];
        foreach (self::publishedProcedureDocuments($companyId) as $doc) {
            $templateIds = array_merge($templateIds, self::recordTemplateIdsForDocument($companyId, (string)$doc['id']));
        }
        $templateIds = array_values(array_unique($templateIds));
        if ($templateIds === []) {
            return self::buildResult('insufficient_data', 0, 0, 0, [], '无程序文件关联记录表格模板，请先建立文件结构化追溯');
        }

        $failItems = [];
        $hasInstance = 0;
        foreach ($templateIds as $templateId) {
            $count = Db::name('record_form_instances')
                ->where('company_id', $companyId)
                ->where('template_id', $templateId)
                ->where('status', 'locked')
                ->count();
            if ((int)$count > 0) {
                $hasInstance++;
                continue;
            }
            $template = Db::name('record_form_templates')
                ->where('company_id', $companyId)
                ->where('id', $templateId)
                ->field('id,name,doc_number')
                ->find();
            if ($template) {
                $failItems[] = self::item((string)$template['id'], (string)$template['name'], (string)$template['doc_number'], '无已锁定的运行记录实例');
            }
        }

        return self::buildResult(count($failItems) > 0 ? 'fail' : 'pass', count($templateIds), count($failItems), 0, array_slice($failItems, 0, 20));
    }

    private static function checkCapaOverdue(string $companyId): array
    {
        $today = date('Y-m-d');
        $failItems = Db::name('capas')
            ->where('company_id', $companyId)
            ->where('soft_delete', 0)
            ->where('status', '<>', 'closed')
            ->whereNotNull('due_date')
            ->where('due_date', '<', $today)
            ->field('id,capa_number,description,due_date')
            ->select()
            ->toArray();
        $openCount = Db::name('capas')->where('company_id', $companyId)->where('soft_delete', 0)->where('status', '<>', 'closed')->count();

        return self::buildResult(count($failItems) > 0 ? 'fail' : 'pass', max(1, (int)$openCount), count($failItems), 0, array_map(
            static fn (array $row): array => self::item((string)$row['id'], mb_substr((string)$row['description'], 0, 40), (string)$row['capa_number'], 'CAPA 已超期'),
            $failItems
        ));
    }

    private static function checkFindingsStale(string $companyId): array
    {
        $threshold = date('Y-m-d H:i:s', strtotime('-90 days'));
        $rows = Db::name('audit_findings')
            ->alias('f')
            ->join('audit_schedules s', 's.id = f.audit_schedule_id AND s.soft_delete = 0')
            ->join('audit_plans p', 'p.id = s.audit_plan_id')
            ->where('p.company_id', $companyId)
            ->where('p.soft_delete', 0)
            ->where('f.soft_delete', 0)
            ->where('f.status', '<>', 'closed')
            ->where('f.created', '<', $threshold)
            ->field('f.id,f.finding_number,f.description,f.created')
            ->select()
            ->toArray();
        $openCount = Db::name('audit_findings')
            ->alias('f')
            ->join('audit_schedules s', 's.id = f.audit_schedule_id AND s.soft_delete = 0')
            ->join('audit_plans p', 'p.id = s.audit_plan_id')
            ->where('p.company_id', $companyId)
            ->where('p.soft_delete', 0)
            ->where('f.soft_delete', 0)
            ->where('f.status', '<>', 'closed')
            ->count();

        return self::buildResult(count($rows) > 0 ? 'fail' : 'pass', max(1, (int)$openCount), count($rows), 0, array_map(
            static fn (array $row): array => self::item((string)$row['id'], mb_substr((string)$row['description'], 0, 40), (string)($row['finding_number'] ?? ''), '审核发现超过90天未关闭'),
            $rows
        ));
    }

    private static function checkManagementReview(string $companyId): array
    {
        $count = Db::name('management_reviews')
            ->where('company_id', $companyId)
            ->where('soft_delete', 0)
            ->where('status', 'completed')
            ->where('review_date', '>=', date('Y-m-d', strtotime('-12 months')))
            ->count();

        return (int)$count > 0
            ? self::buildResult('pass', 1, 0, 0, [])
            : self::buildResult('fail', 1, 1, 0, [self::item('', '管理评审', '', '近12个月无已完成管理评审')]);
    }

    private static function checkInternalAudit(string $companyId): array
    {
        $count = Db::name('audit_plans')
            ->where('company_id', $companyId)
            ->where('soft_delete', 0)
            ->where('status', 'completed')
            ->where('created', '>=', date('Y-m-d H:i:s', strtotime('-12 months')))
            ->count();

        return (int)$count > 0
            ? self::buildResult('pass', 1, 0, 0, [])
            : self::buildResult('fail', 1, 1, 0, [self::item('', '内部审核', '', '近12个月无已完成内部审核')]);
    }

    private static function checkClauseElement(string $companyId): array
    {
        $clauses = Db::name('qms_clauses')
            ->where('company_id', $companyId)
            ->where('soft_delete', 0)
            ->where('review_status', 'published')
            ->where('applicability', '<>', 'not_applicable')
            ->field('id,clause_number,title')
            ->select()
            ->toArray();
        if ($clauses === []) {
            return self::buildResult('insufficient_data', 0, 0, 0, [], '条款库为空或无适用条款');
        }

        $linked = 0;
        $failItems = [];
        foreach ($clauses as $clause) {
            $count = Db::name('qms_element_clause_links')
                ->where('company_id', $companyId)
                ->where('clause_id', (string)$clause['id'])
                ->where('soft_delete', 0)
                ->count();
            if ((int)$count > 0) {
                $linked++;
            } else {
                $failItems[] = self::item((string)$clause['id'], (string)$clause['title'], (string)$clause['clause_number'], '未映射体系要素');
            }
        }

        $score = $linked / count($clauses);
        $status = $score >= 0.8 ? 'pass' : 'fail';
        return self::buildResult($status, count($clauses), count($failItems), 0, array_slice($failItems, 0, 20));
    }

    private static function checkElementDocument(string $companyId): array
    {
        $elements = Db::name('qms_elements')
            ->where('company_id', $companyId)
            ->where('soft_delete', 0)
            ->where('applicability', '<>', 'not_applicable')
            ->field('id,name,`key`')
            ->select()
            ->toArray();
        if ($elements === []) {
            return self::buildResult('insufficient_data', 0, 0, 0, [], '体系要素库为空');
        }

        $failItems = [];
        foreach ($elements as $element) {
            $count = Db::name('qms_element_documents')
                ->where('company_id', $companyId)
                ->where('element_id', (string)$element['id'])
                ->where('soft_delete', 0)
                ->count();
            if ((int)$count === 0) {
                $failItems[] = self::item((string)$element['id'], (string)$element['name'], (string)$element['key'], '未关联体系文件');
            }
        }

        return self::buildResult(count($failItems) > 0 ? 'fail' : 'pass', count($elements), count($failItems), 0, array_slice($failItems, 0, 20));
    }

    private static function buildResult(string $status, int $totalChecked, int $failCount, int $warningCount, array $failItems, ?string $insufficientReason = null): array
    {
        if (!in_array($status, self::STATUSES, true)) {
            $status = 'not_applicable';
        }

        if ($status === 'insufficient_data' && $insufficientReason) {
            $failItems = [self::item('', '数据不足', '', $insufficientReason)];
        }

        $score = null;
        if (!in_array($status, ['not_applicable', 'insufficient_data'], true)) {
            $score = $totalChecked > 0 ? round(($totalChecked - $failCount) / $totalChecked, 4) : 1.0;
        }

        return [
            'status' => $status,
            'score' => $score,
            'total_checked' => $totalChecked,
            'fail_count' => $failCount,
            'warning_count' => $warningCount,
            'fail_items' => $failItems,
        ];
    }

    private static function calcDimensionScores(array $results): array
    {
        $dimensions = [];
        foreach (array_keys(self::dimensionLabels()) as $dimension) {
            $dimensions[$dimension] = ['total_weight' => 0.0, 'earned' => 0.0];
        }

        foreach ($results as $item) {
            $check = $item['check'];
            $result = $item['result'];
            $dimension = (string)$check['dimension'];

            if (in_array((string)$result['status'], ['not_applicable', 'insufficient_data'], true)) {
                continue;
            }

            $weight = (float)$check['weight'];
            $dimensions[$dimension]['total_weight'] += $weight;
            $scoreRatio = (string)$result['status'] === 'warning' ? 1.0 : (float)($result['score'] ?? 0.0);
            $dimensions[$dimension]['earned'] += $scoreRatio * $weight;
        }

        $scores = [];
        foreach ($dimensions as $dimension => $data) {
            $scores[$dimension] = $data['total_weight'] > 0
                ? round($data['earned'] / $data['total_weight'] * 100, 1)
                : null;
        }

        return $scores;
    }

    private static function calcTotalScore(array $dimensionScores): float
    {
        $valid = array_values(array_filter($dimensionScores, static fn ($value): bool => $value !== null));
        return $valid === [] ? 0.0 : round(array_sum($valid) / count($valid), 1);
    }

    private static function calcSummary(array $results): array
    {
        $summary = ['total' => count($results), 'pass' => 0, 'fail' => 0, 'warning' => 0, 'insufficient_data' => 0, 'not_applicable' => 0];
        foreach ($results as $item) {
            $status = (string)$item['result']['status'];
            if (isset($summary[$status])) {
                $summary[$status]++;
            }
        }

        return $summary;
    }

    private static function getResultsForSnapshot(string $snapshotId): array
    {
        $rows = Db::name('compliance_check_results')
            ->alias('r')
            ->leftJoin('compliance_checks c', 'c.id = r.check_id')
            ->where('r.snapshot_id', $snapshotId)
            ->field('r.*,c.check_name,c.clause_number,c.severity,c.suggestion_template,c.weight,c.element_key')
            ->order('c.sort_order', 'asc')
            ->select()
            ->toArray();

        return array_map(static fn (array $row): array => self::normalizeResultRow($row), $rows);
    }

    private static function getGapsForSnapshot(string $snapshotId): array
    {
        $rows = array_values(array_filter(
            self::getResultsForSnapshot($snapshotId),
            static fn (array $row): bool => in_array((string)$row['status'], ['fail', 'insufficient_data'], true)
        ));

        usort($rows, static function (array $left, array $right): int {
            return [
                self::statusPriority((string)$left['status'], (string)$left['severity']),
                (string)$left['dimension'],
                (string)$left['check_code'],
            ] <=> [
                self::statusPriority((string)$right['status'], (string)$right['severity']),
                (string)$right['dimension'],
                (string)$right['check_code'],
            ];
        });

        return $rows;
    }

    private static function latestSnapshotId(string $companyId): ?string
    {
        $id = Db::name('compliance_snapshots')
            ->where('company_id', $companyId)
            ->order('snapshot_time', 'desc')
            ->order('id', 'desc')
            ->value('id');

        return $id ? (string)$id : null;
    }

    private static function normalizeResultRow(array $row): array
    {
        $row['score'] = $row['score'] === null ? null : (float)$row['score'];
        $row['total_checked'] = (int)$row['total_checked'];
        $row['fail_count'] = (int)$row['fail_count'];
        $row['warning_count'] = (int)$row['warning_count'];
        $row['weight'] = $row['weight'] === null ? 0.0 : (float)$row['weight'];
        $row['fail_items'] = self::decodeJson((string)($row['fail_items'] ?? '[]'));
        $row['action_url'] = self::actionUrl((string)$row['check_code']);

        return $row;
    }

    private static function actionUrl(string $checkCode): string
    {
        return match ($checkCode) {
            'equip_calibration_valid', 'equip_has_authorization' => '/equipment/index',
            'personnel_competency_valid' => '/competency_record/index',
            'personnel_certificate_valid' => '/employee_certificate/index',
            'personnel_has_training' => '/training_record/index',
            'refmat_not_expired' => '/reference_material/index',
            'doc_review_not_overdue', 'doc_has_traceability' => '/document/index',
            'procedure_has_record_template' => '/record_form_template/index',
            'procedure_has_record_instance' => '/record_form_instance/index',
            'capa_not_overdue' => '/capa/index',
            'findings_not_stale', 'internal_audit_current' => '/audit_plan/index',
            'management_review_current' => '/management_review/index',
            'clause_has_element', 'element_has_document' => '/planning/traceability',
            default => '/compliance/index',
        };
    }

    private static function statusPriority(string $status, string $severity): int
    {
        if ($severity === 'critical') {
            return 0;
        }
        if ($status === 'insufficient_data') {
            return 1;
        }

        return ['major' => 1, 'minor' => 2][$severity] ?? 3;
    }

    private static function publishedProcedureDocuments(string $companyId): array
    {
        return Db::name('documents')
            ->where('company_id', $companyId)
            ->where('soft_delete', 0)
            ->where('status', 'published')
            ->whereIn('level', [2, 3])
            ->field('id,title,doc_number')
            ->select()
            ->toArray();
    }

    private static function recordTemplateIdsForDocument(string $companyId, string $documentId): array
    {
        $ids = Db::name('qms_structured_documents')
            ->alias('sd')
            ->join('qms_document_blocks b', 'b.structured_document_id = sd.id AND b.company_id = sd.company_id AND b.soft_delete = 0')
            ->join('qms_document_block_links bl', 'bl.block_id = b.id AND bl.company_id = sd.company_id AND bl.soft_delete = 0')
            ->where('sd.company_id', $companyId)
            ->where('sd.document_id', $documentId)
            ->where('sd.soft_delete', 0)
            ->whereNotNull('bl.record_form_template_id')
            ->column('bl.record_form_template_id');

        $direct = Db::name('record_form_templates')
            ->where('company_id', $companyId)
            ->where('procedure_doc_id', $documentId)
            ->where('soft_delete', 0)
            ->column('id');

        return array_values(array_unique(array_map('strval', array_merge($ids, $direct))));
    }

    private static function formatEquipmentDateItems(array $items, string $dateField, string $label): array
    {
        $today = date('Y-m-d');
        return array_map(static function (array $item) use ($today, $dateField, $label): array {
            $date = (string)($item[$dateField] ?? '');
            $days = $date !== '' ? (int)((strtotime($today) - strtotime($date)) / 86400) : null;
            return self::item(
                (string)$item['id'],
                (string)$item['name'],
                (string)$item['equipment_number'],
                $days === null ? '未设置' . $label . '日期' : $label . '过期' . $days . '天'
            );
        }, $items);
    }

    private static function item(string $id, string $name, string $code, string $reason): array
    {
        return ['id' => $id, 'name' => $name, 'code' => $code, 'reason' => $reason];
    }

    private static function decodeJson(string $json): array
    {
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }
}
