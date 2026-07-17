<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Db;

final class ManagementReviewInputService
{
    /**
     * @return array{generated_at:string,categories:list<array<string,mixed>>}
     */
    public static function snapshot(): array
    {
        $calibrationTotal = self::count('calibrations');
        $calibrationPass = self::countWhereIn('calibrations', 'result', [
            'pass', 'qualified', 'conform', '合格', '通过',
        ]);
        $calibrationFail = self::countWhereIn('calibrations', 'result', [
            'fail', 'failed', 'unqualified', 'nonconform', '不合格', '失败',
        ]);
        $calibrationLimited = self::countWhereIn('calibrations', 'result', [
            'limited', 'restricted', '限用',
        ]);

        $categories = [
            self::category('8.9.2.a', '以往管理评审所采取措施的状态', self::count('review_actions'), '/review_action/index'),
            self::category('8.9.2.b', '与管理体系相关的内外部问题变化', self::count('planning_change_events'), '/planning/change-events'),
            self::category('8.9.2.c', '质量目标实现情况', self::count('planning_objectives'), '/planning/objectives'),
            self::category('8.9.2.d', '政策和程序的适宜性', self::count('documents'), '/document/index'),
            self::category('8.9.2.e', '近期内审结果', self::count('audit_findings'), '/audit_finding/index'),
            self::category('8.9.2.f', '纠正措施及 CAPA 状态', self::count('capas'), '/capa/index'),
            self::category('8.9.2.g', '外部机构评审结果', self::externalAssessmentCount(), '/external_evidence_reference/index?subject_type=quality_event'),
            self::category('8.9.2.h', '工作量、工作类型或活动范围变化', self::count('planning_change_events'), '/planning/change-events'),
            self::category('8.9.2.i', '客户和人员反馈', self::count('customer_complaints'), '/complaint/index'),
            self::category('8.9.2.j', '投诉', self::count('customer_complaints'), '/complaint/index'),
            self::category('8.9.2.k', '已实施改进的有效性', self::closedCapaEffectivenessCount(), '/capa/index'),
            self::category('8.9.2.l', '资源充分性', self::count('equipments') + self::count('employees'), '/equipment/index'),
            self::category('8.9.2.m', '风险识别结果', self::count('planning_change_events'), '/planning/change-events'),
            self::category('8.9.2.n', '保证结果有效性的质控活动', self::recordInstanceCount('%BG-30-%'), '/record_form_instance/index?keyword=BG-30-'),
            self::category('8.9.2.o', '其他相关因素：培训', self::count('trainings'), '/training/index'),
            self::category('8.9.2.o', '其他相关因素：日常监督', self::recordInstanceCount('%BG-31-02%'), '/record_form_instance/index?keyword=BG-31-02'),
            self::category(
                '8.9.2.o',
                '其他相关因素：设备校准',
                $calibrationTotal,
                '/calibration/index',
                $calibrationTotal > 0
                    ? sprintf('合格/通过 %d；不合格/失败 %d；限用 %d；其他或待确认 %d', $calibrationPass, $calibrationFail, $calibrationLimited, max(0, $calibrationTotal - $calibrationPass - $calibrationFail - $calibrationLimited))
                    : ''
            ),
        ];

        return [
            'generated_at' => date('Y-m-d H:i:s'),
            'categories' => $categories,
        ];
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

    private static function category(string $clause, string $label, int $count, string $detailUrl, string $summary = ''): array
    {
        $formed = $count > 0;

        return [
            'clause' => $clause,
            'label' => $label,
            'count' => $count,
            'status' => $formed ? 'formed' : 'not_formed',
            'status_label' => $formed ? '已形成' : '未形成/待补充',
            'summary' => $summary !== '' ? $summary : ($formed ? '已关联 ' . $count . ' 条明细' : ''),
            'detail_url' => $detailUrl,
        ];
    }

    private static function count(string $table): int
    {
        try {
            return (int)Db::name($table)->where('soft_delete', 0)->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private static function countWhereIn(string $table, string $field, array $values): int
    {
        try {
            return (int)Db::name($table)->where('soft_delete', 0)->whereIn($field, $values)->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private static function recordInstanceCount(string $docNumberLike): int
    {
        try {
            return (int)Db::name('record_form_instances')
                ->where('doc_number', 'like', $docNumberLike)
                ->where('status', '<>', 'voided')
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private static function externalAssessmentCount(): int
    {
        try {
            return (int)Db::name('external_evidence_references')
                ->where('soft_delete', 0)
                ->where('object_type', 'like', '%评审%')
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private static function closedCapaEffectivenessCount(): int
    {
        try {
            return (int)Db::name('capas')
                ->where('soft_delete', 0)
                ->where('status', 'closed')
                ->whereNotNull('effectiveness_result')
                ->where('effectiveness_result', '<>', '')
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }
}
