<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\service\FinalCandidateTraceSyncService;

(new think\App())->initialize();

function final_candidate_trace_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$preview = FinalCandidateTraceSyncService::preview();
final_candidate_trace_assert(($preview['validation']['ok'] ?? false) === true, '链路同步预览必须通过基本校验');
final_candidate_trace_assert(($preview['counts']['candidate_documents'] ?? 0) === 65, '8021测试正式文件必须恰好65份');
final_candidate_trace_assert(($preview['counts']['candidate_structures'] ?? 0) === 65, '8021测试正式结构化文件必须恰好65份');
final_candidate_trace_assert(($preview['counts']['active_elements'] ?? 0) === 29, '质量要素必须为29个');
final_candidate_trace_assert(($preview['counts']['trial_ready_templates'] ?? 0) === 104, '现有104张trial_ready表单必须保持不变');

$verification = FinalCandidateTraceSyncService::verifyFormalTrace();
final_candidate_trace_assert(($verification['ok'] ?? false) === true, '8021链路同步后验证必须通过：' . implode('；', $verification['errors'] ?? []));
final_candidate_trace_assert(($verification['counts']['candidate_blocks'] ?? 0) === 315, '0.3制度解析块必须保持315个');
final_candidate_trace_assert(($verification['counts']['candidate_element_documents'] ?? 0) >= 25, '0.3制度必须形成要素-制度对应关系');
final_candidate_trace_assert(($verification['counts']['candidate_block_links'] ?? 0) > 0, '0.3制度必须形成要素-章节链路');
final_candidate_trace_assert(($verification['counts']['candidate_template_block_links'] ?? -1) === 0, '本轮不得自动挂接记录表单模板');
final_candidate_trace_assert(($verification['counts']['old_active_block_links'] ?? -1) === 0, '旧版本章节链路必须从现行测试视图隐藏');
final_candidate_trace_assert(($verification['counts']['old_active_element_documents'] ?? -1) === 0, '旧版本制度-要素链路必须从现行测试视图隐藏');
final_candidate_trace_assert(($verification['counts']['active_non_candidate_documents'] ?? -1) === 0, '现行测试视图不得保留旧版本制度');

echo "qms_final_candidate_trace_sync_smoke passed\n";
