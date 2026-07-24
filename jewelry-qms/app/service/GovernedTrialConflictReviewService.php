<?php
declare(strict_types=1);

namespace app\service;

final class GovernedTrialConflictReviewService
{
    public static function review(array $documents): array
    {
        $blocking = [];
        $warnings = [];
        $combined = implode("\n", array_map('strval', $documents));

        foreach ($documents as $docNumber => $content) {
            if (preg_match_all('/XZTC\/CX-[0-9]+(?:-[0-9]+)?-2018/u', (string)$content, $matches)) {
                $blocking[] = self::finding(
                    'retired_2018_reference',
                    (string)$docNumber,
                    '仍引用2018版程序编号：' . implode('、', array_values(array_unique($matches[0])))
                );
            }
        }

        $noSampling = str_contains($combined, '不开展抽样');
        $samplingStillActive = false;
        foreach ($documents as $docNumber => $content) {
            if (str_contains((string)$docNumber, 'CX-35') && !preg_match('/作废|不适用/u', (string)$content)) {
                $samplingStillActive = true;
            }
        }
        if ($noSampling && $samplingStillActive) {
            $blocking[] = self::finding(
                'sampling_scope_conflict',
                'SYSTEM',
                '体系声明不开展抽样，但CX-35仍作为有效程序存在。'
            );
        }

        $managementText = (string)($documents['XZTC/CX-21-2022'] ?? '');
        if (
            str_contains($managementText, '实验室主任批准管理评审报告')
            && str_contains($managementText, '总经理批准管理评审报告')
        ) {
            $blocking[] = self::finding(
                'management_review_approval_conflict',
                'XZTC/CX-21-2022',
                '管理评审报告同时出现实验室主任批准和总经理批准。'
            );
        }

        if (preg_match('/保存期限(?:为|：|:)?\s*3年/u', $combined)) {
            $blocking[] = self::finding(
                'retention_period_conflict',
                'SYSTEM',
                '发现保存3年的要求，与本治理批次已签认的“不少于6年”统一口径冲突。'
            );
        }
        if (str_contains($combined, '不分包') && preg_match('/分包方|实施分包|分包样品/u', $combined)) {
            $blocking[] = self::finding(
                'subcontracting_scope_conflict',
                'SYSTEM',
                '不分包声明与仍可执行的分包路径并存。'
            );
        }
        if (str_contains($combined, '不设常规留样') && preg_match('/留样登记|常规留样/u', $combined)) {
            $blocking[] = self::finding(
                'sample_retention_conflict',
                'SYSTEM',
                '不设常规留样与留样执行路径并存。'
            );
        }
        if (preg_match('/RB\/T\s*214[-—]2017/u', $combined)) {
            $warnings[] = [
                'type' => 'legacy_cma_basis_review',
                'doc_number' => 'SYSTEM',
                'message' => '发现RB/T 214-2017，须核对其是否仍被表述为现行CMA主依据。',
                'blocking' => false,
            ];
        }

        return [
            'blocking_conflicts' => $blocking,
            'warnings' => $warnings,
            'ok' => $blocking === [],
        ];
    }

    private static function finding(string $type, string $docNumber, string $message): array
    {
        return [
            'type' => $type,
            'doc_number' => $docNumber,
            'message' => $message,
            'blocking' => true,
        ];
    }
}
