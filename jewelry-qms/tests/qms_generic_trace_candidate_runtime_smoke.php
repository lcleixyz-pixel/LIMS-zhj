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
    foreach (['external_sources', 'manual_sections', 'record_templates'] as $candidateKind) {
        foreach ((array)($candidate[$candidateKind] ?? []) as $candidateRow) {
            generic_trace_candidate_assert(
                !array_key_exists('governance_state', (array)$candidateRow),
                $label . ' 的候选不得伪装成已确认治理关系'
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
            $semanticStatus === 'aligned',
            $label . ' 定向治理结果应继续保持已对齐'
        );
        generic_trace_candidate_assert(
            (array)($viewModel['chain']['missing'] ?? []) === [],
            $label . ' 定向治理结果应继续保持主链闭合'
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
    . json_encode($statusCounts, JSON_UNESCAPED_UNICODE)
    . PHP_EOL;
