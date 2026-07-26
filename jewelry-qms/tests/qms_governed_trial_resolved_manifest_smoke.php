<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\service\GovernedTrialConflictReviewService;
use app\service\GovernedTrialResolvedManifestService;

(new think\App())->initialize();

function resolved_manifest_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$manifest = GovernedTrialResolvedManifestService::build();
resolved_manifest_assert(($manifest['version'] ?? '') === 'GOV-TRIAL/0.2', '解析稿版本应为GOV-TRIAL/0.2');
resolved_manifest_assert(($manifest['trial_batch'] ?? '') === 'GOV-TRIAL-20260725', '解析稿批次应固定');
resolved_manifest_assert(count($manifest['baselines'] ?? []) === 38, '应登记1份手册和37份程序基线');
resolved_manifest_assert(count($manifest['sources'] ?? []) >= 8, '应登记候选内容与终局签认双重来源');

$manuals = array_values(array_filter(
    $manifest['baselines'] ?? [],
    static fn(array $row): bool => ($row['document_role'] ?? '') === 'quality_manual'
));
$procedures = array_values(array_filter(
    $manifest['baselines'] ?? [],
    static fn(array $row): bool => ($row['document_role'] ?? '') === 'procedure'
));
resolved_manifest_assert(count($manuals) === 1, '应且仅应有1份质量手册基线');
resolved_manifest_assert(count($procedures) === 37, '应有37份程序基线');

foreach ($manifest['baselines'] as $baseline) {
    resolved_manifest_assert(is_file((string)($baseline['absolute_path'] ?? '')), ($baseline['doc_number'] ?? '文件') . ' 基线原件必须存在');
    resolved_manifest_assert(
        preg_match('/^[a-f0-9]{64}$/', (string)($baseline['source_sha256'] ?? '')) === 1,
        ($baseline['doc_number'] ?? '文件') . ' 必须登记来源哈希'
    );
}

foreach ($manifest['sources'] as $source) {
    resolved_manifest_assert(is_file((string)($source['absolute_path'] ?? '')), ($source['source_key'] ?? '依据') . ' 必须存在');
    resolved_manifest_assert(
        hash_file('sha256', (string)$source['absolute_path']) === ($source['sha256'] ?? ''),
        ($source['source_key'] ?? '依据') . ' 来源哈希必须与原件一致'
    );
}

resolved_manifest_assert(($manifest['patches'] ?? []) !== [], '签认清单至少应形成一批可执行补丁');
$patchIds = array_column($manifest['patches'] ?? [], 'patch_id');
resolved_manifest_assert(
    in_array('USER-20260725-RBT214-001', $patchIds, true),
    '人员培训程序应登记RB/T 214依据身份修订补丁'
);
resolved_manifest_assert(
    in_array('USER-20260725-RBT214-002', $patchIds, true),
    '人员管理程序应登记RB/T 214依据身份修订补丁'
);
foreach ($manifest['patches'] as $patch) {
    foreach ([
        'patch_id',
        'target_doc_number',
        'operation',
        'anchor',
        'expected_old_sha256',
        'replacement_markdown',
        'source_path',
        'source_sha256',
        'approval_source_path',
        'approval_source_sha256',
        'decision_status',
        'decision_date',
        'clause_refs',
        'reason',
    ] as $field) {
        resolved_manifest_assert(array_key_exists($field, $patch), ($patch['patch_id'] ?? '补丁') . ' 缺少字段 ' . $field);
    }
    resolved_manifest_assert(($patch['decision_status'] ?? '') === 'signed', ($patch['patch_id'] ?? '补丁') . ' 自动补丁必须已签认');
}

$validation = GovernedTrialResolvedManifestService::validate($manifest);
resolved_manifest_assert(($validation['ok'] ?? false) === true, '清单必须通过来源与字段校验：' . implode('；', $validation['errors'] ?? []));

$tampered = $manifest;
$tampered['patches'][0]['decision_status'] = 'candidate';
$tamperedValidation = GovernedTrialResolvedManifestService::validate($tampered);
resolved_manifest_assert(($tamperedValidation['ok'] ?? true) === false, '候选状态补丁必须被清单校验阻断');

$review = GovernedTrialConflictReviewService::review([
    'XZTC/SC' => '引用 XZTC/CX-08-2018；保存期限为3年；本公司不开展抽样。',
    'XZTC/CX-35-2022' => '抽样控制程序仍有效。',
    'XZTC/CX-21-2022' => '实验室主任批准管理评审报告。总经理批准管理评审报告。',
]);
$types = array_column($review['blocking_conflicts'] ?? [], 'type');
resolved_manifest_assert(in_array('retired_2018_reference', $types, true), '2018程序引用必须纳入专项审查');
resolved_manifest_assert(in_array('sampling_scope_conflict', $types, true), '不抽样与CX-35有效必须纳入专项审查');
resolved_manifest_assert(in_array('management_review_approval_conflict', $types, true), '管评批准岗位冲突必须纳入专项审查');
resolved_manifest_assert(in_array('retention_period_conflict', $types, true), '3年与不少于6年冲突必须纳入专项审查');

$legacyReview = GovernedTrialConflictReviewService::review([
    'XZTC/CX-01-2022' => '授权签字人应满足RB/T214-2017的要求。',
]);
resolved_manifest_assert(
    count($legacyReview['warnings'] ?? []) === 1,
    '把RB/T 214-2017表述为现行要求时必须提醒'
);
$contextualizedReview = GovernedTrialConflictReviewService::review([
    'XZTC/CX-01-2022' => 'RB/T 214-2017仅作为历史制度衔接和辅助参考，不作为现行CMA主依据。',
]);
resolved_manifest_assert(
    count($contextualizedReview['warnings'] ?? []) === 0,
    '已明确历史辅助身份后不应继续产生RB/T 214提醒'
);

echo "qms_governed_trial_resolved_manifest_smoke passed\n";
