<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Config;
use think\facade\Db;

final class ManagementReviewInputService
{
    /**
     * @return array{generated_at:string,categories:list<array<string,mixed>>}
     */
    public static function snapshot(): array
    {
        $calibrations = self::recordSet('calibrations');
        $calibrationTotal = $calibrations['count'];
        $calibrationPass = self::recordSet('calibrations', static function ($query): void {
            $query->whereIn('t.result', ['pass', 'qualified', 'conform', '合格', '通过']);
        })['count'];
        $calibrationFail = self::recordSet('calibrations', static function ($query): void {
            $query->whereIn('t.result', ['fail', 'failed', 'unqualified', 'nonconform', '不合格', '失败']);
        })['count'];
        $calibrationLimited = self::recordSet('calibrations', static function ($query): void {
            $query->whereIn('t.result', ['limited', 'restricted', '限用']);
        })['count'];

        $categories = [
            self::category('8.9.2.a', '以往管理评审所采取措施的状态', self::recordSet('review_actions'), '/review_action/index'),
            self::category('8.9.2.b', '与管理体系相关的内外部问题变化', self::recordSet('qms_external_change_events'), '/planning/change-events'),
            self::category('8.9.2.c', '质量目标实现情况', self::recordSet('qms_quality_objectives'), '/planning/objectives'),
            self::category('8.9.2.d', '政策和程序的适宜性', self::recordSet('documents'), '/document/index'),
            self::category('8.9.2.e', '近期内审结果', self::recordSet('audit_findings'), '/audit_finding/index'),
            self::category('8.9.2.f', '纠正措施及 CAPA 状态', self::recordSet('capas'), '/capa/index'),
            self::category(
                '8.9.2.g',
                '外部机构评审结果',
                self::recordSet('external_evidence_references', static function ($query): void {
                    $query->where('t.object_type', 'like', '%评审%');
                }),
                '/external_evidence_reference/index?subject_type=quality_event'
            ),
            self::category('8.9.2.h', '工作量、工作类型或活动范围变化', self::emptyRecordSet(), '/planning/change-events'),
            self::category('8.9.2.i', '客户和人员反馈', self::recordSet('customer_complaints'), '/complaint/index'),
            self::category('8.9.2.j', '投诉', self::recordSet('customer_complaints'), '/complaint/index'),
            self::category(
                '8.9.2.k',
                '已实施改进的有效性',
                self::recordSet('capas', static function ($query): void {
                    $query->where('t.status', 'closed')
                        ->whereNotNull('t.effectiveness_result')
                        ->where('t.effectiveness_result', '<>', '');
                }),
                '/capa/index'
            ),
            self::category('8.9.2.l', '资源充分性：设备', self::recordSet('equipments'), '/equipment/index'),
            self::category('8.9.2.l', '资源充分性：人员', self::recordSet('employees'), '/employee/index'),
            self::category('8.9.2.m', '风险识别结果', self::emptyRecordSet(), '/planning/change-events'),
            self::category(
                '8.9.2.n',
                '保证结果有效性的质控活动',
                self::recordSet('record_form_instances', static function ($query): void {
                    $query->where('t.doc_number', 'like', '%BG-30-%')
                        ->where('t.status', '<>', 'voided');
                }, '', false),
                '/record_form_instance/index?keyword=BG-30-'
            ),
            self::category('8.9.2.o', '其他相关因素：培训', self::recordSet('trainings'), '/training/index'),
            self::category(
                '8.9.2.o',
                '其他相关因素：日常监督',
                self::recordSet('record_form_instances', static function ($query): void {
                    $query->where('t.doc_number', 'like', '%BG-31-02%')
                        ->where('t.status', '<>', 'voided');
                }, '', false),
                '/record_form_instance/index?keyword=BG-31-02'
            ),
            self::category(
                '8.9.2.o',
                '其他相关因素：设备校准',
                $calibrations,
                '/calibration/index',
                $calibrationTotal > 0
                    ? sprintf('合格/通过 %d；不合格/失败 %d；限用 %d；其他或待确认 %d', $calibrationPass, $calibrationFail, $calibrationLimited, max(0, $calibrationTotal - $calibrationPass - $calibrationFail - $calibrationLimited))
                    : ''
            ),
        ];

        $snapshot = [
            'generated_at' => date('Y-m-d H:i:s'),
            'categories' => $categories,
        ];
        $snapshot['snapshot_sha256'] = hash(
            'sha256',
            json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''
        );

        return $snapshot;
    }

    public static function format(array $snapshot): string
    {
        $lines = [];
        foreach ((array)($snapshot['categories'] ?? []) as $category) {
            $lines[] = sprintf(
                '%s %s：%s%s',
                (string)($category['clause'] ?? ''),
                (string)($category['label'] ?? ''),
                (string)($category['status_label'] ?? '未形成/待补充'),
                trim((string)($category['summary'] ?? '')) !== '' ? '；' . (string)$category['summary'] : ''
            );
        }

        return implode("\n", $lines);
    }

    /**
     * @param array{count:int,record_ids:list<string>,record_ids_truncated:bool} $records
     */
    private static function category(string $clause, string $label, array $records, string $detailUrl, string $summary = ''): array
    {
        $count = $records['count'];
        $formed = $count > 0;

        return [
            'clause' => $clause,
            'label' => $label,
            'count' => $count,
            'status' => $formed ? 'formed' : 'not_formed',
            'status_label' => $formed ? '已形成' : '未形成/待补充',
            'summary' => $summary !== '' ? $summary : ($formed ? '已关联 ' . $count . ' 条明细' : ''),
            'detail_url' => $detailUrl,
            'record_ids' => $records['record_ids'],
            'record_ids_truncated' => $records['record_ids_truncated'],
        ];
    }

    public static function verifySnapshot(array $snapshot): bool
    {
        $expected = trim((string)($snapshot['snapshot_sha256'] ?? ''));
        if ($expected === '') {
            return false;
        }
        unset($snapshot['snapshot_sha256']);

        return hash_equals(
            $expected,
            hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '')
        );
    }

    /**
     * @return array{count:int,record_ids:list<string>,record_ids_truncated:bool}
     */
    private static function recordSet(
        string $table,
        ?callable $filter = null,
        string $idPrefix = '',
        bool $hasSoftDelete = true
    ): array
    {
        $query = Db::name($table)->alias('t');
        if ($table === 'audit_findings') {
            $query->join('audit_schedules s', 's.id = t.audit_schedule_id')
                ->join('audit_plans p', 'p.id = s.audit_plan_id')
                ->where('p.company_id', (string)Config::get('qms.company_id'))
                ->where('s.soft_delete', 0)
                ->where('p.soft_delete', 0);
        } elseif ($table === 'review_actions') {
            $query->join('management_reviews mr', 'mr.id = t.management_review_id')
                ->where('mr.company_id', (string)Config::get('qms.company_id'))
                ->where('mr.soft_delete', 0);
        } elseif ($table === 'calibrations') {
            $query->join('equipments e', 'e.id = t.equipment_id')
                ->where('e.company_id', (string)Config::get('qms.company_id'))
                ->where('e.soft_delete', 0);
        } else {
            $query->where('t.company_id', (string)Config::get('qms.company_id'));
        }
        if ($hasSoftDelete) {
            $query->where('t.soft_delete', 0);
        }
        if ($filter !== null) {
            $filter($query);
        }

        $count = (int)(clone $query)->count();
        $recordIds = array_values(array_map(
            static fn ($id): string => $idPrefix . (string)$id,
            (clone $query)->order('t.id', 'asc')->limit(200)->column('t.id')
        ));

        return [
            'count' => $count,
            'record_ids' => $recordIds,
            'record_ids_truncated' => $count > count($recordIds),
        ];
    }

    /**
     * @return array{count:int,record_ids:list<string>,record_ids_truncated:bool}
     */
    private static function emptyRecordSet(): array
    {
        return ['count' => 0, 'record_ids' => [], 'record_ids_truncated' => false];
    }
}
