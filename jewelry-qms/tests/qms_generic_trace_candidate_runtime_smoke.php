<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/app/common.php';

use app\service\QmsFileGovernanceWorkbenchService;
use think\facade\Db;

(new think\App())->initialize();

function generic_trace_candidate_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function generic_trace_candidate_counts(): array
{
    return [
        'structures' => Db::name('qms_structured_documents')
            ->where('soft_delete', 0)
            ->count(),
        'documents' => Db::name('documents')
            ->where('soft_delete', 0)
            ->count(),
        'links' => Db::name('qms_document_block_links')
            ->where('soft_delete', 0)
            ->count(),
    ];
}

$procedures = Db::name('qms_structured_documents')
    ->where('version', 'GOV-TRIAL/0.2')
    ->where('document_role', 'procedure')
    ->where('soft_delete', 0)
    ->order('doc_number')
    ->field('id,doc_number,title')
    ->select()
    ->toArray();
generic_trace_candidate_assert(
    count($procedures) === 37,
    '8021 应有 37 份 GOV-TRIAL/0.2 程序用于候选链覆盖验收'
);

$before = generic_trace_candidate_counts();
$statusCounts = [];
$routingCounts = ['routable' => 0, 'blocked' => 0];
foreach ($procedures as $procedure) {
    $label = (string)$procedure['doc_number'];
    $viewModel = QmsFileGovernanceWorkbenchService::detail((string)$procedure['id']);
    generic_trace_candidate_assert($viewModel !== [], $label . ' 工作台不得为空');

    $candidate = (array)($viewModel['candidate_trace'] ?? []);
    generic_trace_candidate_assert($candidate !== [], $label . ' 应提供独立治理候选链');
    generic_trace_candidate_assert(
        ($candidate['available'] ?? false) === true,
        $label . ' 应能从治理装配蓝图取得候选'
    );
    generic_trace_candidate_assert(
        ($candidate['review_required'] ?? false) === true,
        $label . ' 的候选必须保持人工复核闸门'
    );
    generic_trace_candidate_assert(
        (string)($candidate['source_label'] ?? '')
            === '治理装配蓝图 / 本地条款映射',
        $label . ' 应明确候选来源'
    );
    generic_trace_candidate_assert(
        (array)($candidate['manual_sections'] ?? []) !== [],
        $label . ' 应提供候选手册章节'
    );
    generic_trace_candidate_assert(
        (array)($candidate['record_templates'] ?? []) !== [],
        $label . ' 应提供候选运行记录'
    );
    foreach ([
        'external_sources' => ['external_source', 'basis', 'clause_id'],
        'manual_sections' => ['manual_section', 'implements', 'manual_section_id'],
        'record_templates' => [
            'record_template',
            'requires_record',
            'record_form_template_id',
        ],
    ] as $candidateCollection => $expectedRouting) {
        foreach ((array)($candidate[$candidateCollection] ?? []) as $candidateRow) {
            generic_trace_candidate_assert(
                !array_key_exists('governance_state', (array)$candidateRow),
                $label . ' 的候选不得伪装成已确认治理关系'
            );
            $routable = (bool)($candidateRow['routable'] ?? false);
            $routingCounts[$routable ? 'routable' : 'blocked']++;
            if (!(bool)($candidateRow['available'] ?? false)) {
                generic_trace_candidate_assert(
                    !$routable
                        && (string)($candidateRow['routing_issue'] ?? '') !== '',
                    $label . ' 的未入库候选应说明为何不能带入'
                );
                continue;
            }
            generic_trace_candidate_assert(
                $routable
                    && (string)($candidateRow['candidate_kind'] ?? '')
                        === $expectedRouting[0]
                    && (string)($candidateRow['relation_type'] ?? '')
                        === $expectedRouting[1]
                    && (string)($candidateRow['target_field'] ?? '')
                        === $expectedRouting[2]
                    && (string)($candidateRow['target_block_id'] ?? '') !== ''
                    && str_contains(
                        (string)($candidateRow['review_url'] ?? ''),
                        'candidate_id='
                    ),
                $label . ' 的可用候选应定向到内容块并生成安全预填入口'
            );
        }
    }

    $semanticStatus = (string)($viewModel['semantic_guard']['status'] ?? '');
    generic_trace_candidate_assert(
        $semanticStatus !== '' && $semanticStatus !== 'not_assessed',
        $label . ' 应进入通用语义状态，不得跳过评估'
    );
    $statusCounts[$semanticStatus] = ($statusCounts[$semanticStatus] ?? 0) + 1;

    if (
        str_contains($label, 'CX-08-2022')
        || str_contains($label, 'CX-19-2022')
        || str_contains($label, 'CX-26-2022')
    ) {
        generic_trace_candidate_assert(
            $semanticStatus === 'suspected_mismatch'
                && (string)(
                    $viewModel['semantic_guard']['issues'][0]['reason_code'] ?? ''
                ) === 'mixed_relation',
            $label . ' 应识别候选章节正确但要素与手册关系混装'
        );
        generic_trace_candidate_assert(
            (array)($viewModel['chain']['missing'] ?? []) === ['手册主链'],
            $label . ' 在拆分前不得把混装关系计入手册主链'
        );
    }
}
$after = generic_trace_candidate_counts();

generic_trace_candidate_assert(
    $before === $after,
    '遍历 37 份程序的候选链不得新增、修改或删除数据库记录'
);

ksort($statusCounts);
echo 'qms_generic_trace_candidate_runtime_smoke passed: '
    . json_encode([
        'statuses' => $statusCounts,
        'routing' => $routingCounts,
    ], JSON_UNESCAPED_UNICODE)
    . PHP_EOL;
