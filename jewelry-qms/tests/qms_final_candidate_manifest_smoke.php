<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\service\FinalCandidateManifestService;

(new think\App())->initialize();

function final_candidate_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$sourceDir = trim((string)getenv('QMS_FINAL_CANDIDATE_SOURCE_DIR'));
final_candidate_assert($sourceDir !== '' && is_dir($sourceDir), '必须通过 QMS_FINAL_CANDIDATE_SOURCE_DIR 提供75份最终确认稿');

$manifest = FinalCandidateManifestService::build($sourceDir);
final_candidate_assert(($manifest['version'] ?? '') === 'GOV-TRIAL/0.3', '候选版本必须固定为 GOV-TRIAL/0.3');
final_candidate_assert(($manifest['trial_batch'] ?? '') === 'GOV-TRIAL-20260820-DOCS', '候选批次必须固定');
final_candidate_assert(($manifest['validation']['ok'] ?? false) === true, '实际来源清单必须通过校验');
final_candidate_assert(($manifest['validation']['counts']['all_docx'] ?? 0) === 75, '来源应恰好有75份DOCX');
final_candidate_assert(($manifest['validation']['counts']['included'] ?? 0) === 65, '白名单应恰好有65份制度文件');
final_candidate_assert(($manifest['validation']['counts']['excluded'] ?? 0) === 10, '应排除9份G5/G6和1份待确认项清单');
final_candidate_assert(($manifest['validation']['counts']['quality_manual'] ?? 0) === 1, '应有1份质量手册');
final_candidate_assert(($manifest['validation']['counts']['procedure'] ?? 0) === 35, '应有35份程序文件');
final_candidate_assert(($manifest['validation']['counts']['work_instruction'] ?? 0) === 29, '应有29份作业指导书材料');

$obsolete = array_values(array_filter(
    $manifest['documents'] ?? [],
    static fn(array $row): bool => ($row['status'] ?? '') === 'obsolete'
));
final_candidate_assert(count($obsolete) === 1, '应且仅应有1份废止留痕');
final_candidate_assert(($obsolete[0]['canonical_doc_number'] ?? '') === 'XZTC/ZY-1-01-2026', '废止留痕必须是 ZY-1-01');
final_candidate_assert(($obsolete[0]['review_class'] ?? '') === 'reference_only', '废止件必须标记 reference_only');

$drafts = array_values(array_filter(
    $manifest['documents'] ?? [],
    static fn(array $row): bool => ($row['status'] ?? '') === 'draft'
));
final_candidate_assert(count($drafts) === 64, '其余64份必须保持草稿');
foreach ($manifest['documents'] as $document) {
    final_candidate_assert(($document['trial_doc_number'] ?? '') === 'SIM-GOV03-' . ($document['canonical_doc_number'] ?? ''), '试装编号必须使用 SIM-GOV03- 前缀');
    final_candidate_assert(preg_match('/^[a-f0-9]{64}$/', (string)($document['source_sha256'] ?? '')) === 1, '每份来源必须登记SHA-256');
}

$resolved = FinalCandidateManifestService::resolveRecommendedTimeMarkers(
    "验收后 5 个工作日内＿＿＿＿完成。每季度一次＿＿检查。数字式：≤ 0.5 ℃〔暂定〕。签名：＿＿＿＿。"
);
final_candidate_assert(
    ($resolved['content'] ?? '') === "验收后 5 个工作日内完成。每季度一次检查。数字式：≤ 0.5 ℃〔暂定〕。签名：＿＿＿＿。",
    '只能清除明确时限后的待裁决标记，不得改技术阈值或签名空格'
);
final_candidate_assert(count($resolved['patches'] ?? []) === 2, '样例应形成2条时限裁决补丁');
foreach ($resolved['patches'] as $patch) {
    final_candidate_assert(($patch['decision_status'] ?? '') === 'recommended_candidate', '时限补丁只能是候选推荐状态');
    final_candidate_assert(hash('sha256', (string)$patch['anchor']) === ($patch['expected_old_sha256'] ?? ''), '补丁必须登记原文锚点哈希');
    final_candidate_assert(hash('sha256', (string)$patch['replacement']) === ($patch['replacement_sha256'] ?? ''), '补丁必须登记替换后哈希');
}

$tampered = $manifest;
$tampered['documents'][0]['source_sha256'] = str_repeat('0', 64);
$tamperedValidation = FinalCandidateManifestService::validate($tampered);
final_candidate_assert(($tamperedValidation['ok'] ?? true) === false, '来源哈希漂移必须阻断');

echo "qms_final_candidate_manifest_smoke passed\n";
