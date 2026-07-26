<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use app\service\GovernedTrialPatchEngine;

function resolved_patch_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function signed_patch(array $override): array
{
    return array_merge([
        'patch_id' => 'PATCH-DEFAULT',
        'target_doc_number' => 'XZTC/CX-01-2022',
        'operation' => 'replace_exact',
        'anchor' => '旧要求',
        'expected_old_sha256' => hash('sha256', '旧要求'),
        'replacement_markdown' => '新要求',
        'source_path' => '.team/交接箱/终局签认.md',
        'source_sha256' => str_repeat('a', 64),
        'decision_status' => 'signed',
        'decision_date' => '2026-07-22',
        'supersedes_patch_id' => '',
        'clause_refs' => ['7.11'],
        'reason' => '终局签认要求',
    ], $override);
}

$baseline = "# 程序\n\n旧要求\n\n## 记录\n\n原记录要求\n\n|字段|内容|\n|---|---|\n|A|B|\n";
$patches = [
    signed_patch([
        'patch_id' => 'PATCH-REPLACE',
    ]),
    signed_patch([
        'patch_id' => 'PATCH-INSERT',
        'operation' => 'insert_after_heading',
        'anchor' => '# 程序',
        'expected_old_sha256' => hash('sha256', '# 程序'),
        'replacement_markdown' => '适用范围：试运行。',
    ]),
    signed_patch([
        'patch_id' => 'PATCH-RECORD',
        'operation' => 'append_record_requirement',
        'anchor' => '## 记录',
        'expected_old_sha256' => hash('sha256', '## 记录'),
        'replacement_markdown' => '新增记录要求。',
    ]),
    signed_patch([
        'patch_id' => 'PATCH-DELETE',
        'operation' => 'delete_exact',
        'anchor' => '原记录要求',
        'expected_old_sha256' => hash('sha256', '原记录要求'),
        'replacement_markdown' => '',
        'reason' => '终局签认明确删除旧记录要求',
    ]),
];

$result = GovernedTrialPatchEngine::apply($baseline, $patches);
resolved_patch_assert(($result['blocking_conflicts'] ?? []) === [], '合法补丁不应产生阻断冲突');
resolved_patch_assert(count($result['applied_patches'] ?? []) === 4, '四种合法操作均应应用');
resolved_patch_assert(str_contains((string)$result['content'], '新要求'), '精确替换应进入正文');
resolved_patch_assert(str_contains((string)$result['content'], "程序\n\n适用范围：试运行。"), '标题后插入应进入正文');
resolved_patch_assert(str_contains((string)$result['content'], "记录\n\n新增记录要求。"), '记录要求应追加');
resolved_patch_assert(!str_contains((string)$result['content'], '原记录要求'), '明确签认的删除应生效');
resolved_patch_assert(str_contains((string)$result['content'], '|A|B|'), '未涉及表格必须保留');
resolved_patch_assert(($result['preservation_check']['ok'] ?? false) === true, '未修改区段应通过保留校验');

$ambiguous = GovernedTrialPatchEngine::apply(
    "重复\n重复\n",
    [signed_patch(['patch_id' => 'PATCH-AMBIGUOUS', 'anchor' => '重复', 'expected_old_sha256' => hash('sha256', '重复')])]
);
resolved_patch_assert(($ambiguous['content'] ?? '') === "重复\n重复\n", '多义锚点必须保留原文');
resolved_patch_assert(($ambiguous['blocking_conflicts'][0]['type'] ?? '') === 'anchor_ambiguous', '多义锚点必须明确分类');

$missing = GovernedTrialPatchEngine::apply(
    $baseline,
    [signed_patch(['patch_id' => 'PATCH-MISSING', 'anchor' => '不存在', 'expected_old_sha256' => hash('sha256', '不存在')])]
);
resolved_patch_assert(($missing['blocking_conflicts'][0]['type'] ?? '') === 'anchor_missing', '缺失锚点必须阻断');

$drift = GovernedTrialPatchEngine::apply(
    $baseline,
    [signed_patch(['patch_id' => 'PATCH-DRIFT', 'expected_old_sha256' => str_repeat('0', 64)])]
);
resolved_patch_assert(($drift['blocking_conflicts'][0]['type'] ?? '') === 'old_text_hash_mismatch', '旧文哈希漂移必须阻断');

$unsigned = GovernedTrialPatchEngine::apply(
    $baseline,
    [signed_patch(['patch_id' => 'PATCH-UNSIGNED', 'decision_status' => 'candidate'])]
);
resolved_patch_assert(($unsigned['blocking_conflicts'][0]['type'] ?? '') === 'source_not_signed', '未签认候选不得改写正文');
resolved_patch_assert(($unsigned['content'] ?? '') === $baseline, '未签认候选必须保留原文');

$unsupportedDelete = GovernedTrialPatchEngine::apply(
    $baseline,
    [signed_patch(['patch_id' => 'PATCH-BAD-DELETE', 'operation' => 'delete_exact', 'replacement_markdown' => '', 'reason' => ''])],
);
resolved_patch_assert(($unsupportedDelete['blocking_conflicts'][0]['type'] ?? '') === 'deletion_without_signed_reason', '无明确理由的删除必须阻断');

$overlap = GovernedTrialPatchEngine::apply(
    $baseline,
    [
        signed_patch(['patch_id' => 'PATCH-A', 'replacement_markdown' => '版本A']),
        signed_patch(['patch_id' => 'PATCH-B', 'replacement_markdown' => '版本B']),
    ]
);
resolved_patch_assert(
    in_array('patch_overlap', array_column($overlap['blocking_conflicts'] ?? [], 'type'), true),
    '无世系关系的重叠补丁必须阻断'
);
resolved_patch_assert(($overlap['content'] ?? '') === $baseline, '冲突补丁不得择一静默覆盖');

$superseded = GovernedTrialPatchEngine::apply(
    $baseline,
    [
        signed_patch(['patch_id' => 'PATCH-OLD', 'replacement_markdown' => '旧签认文本']),
        signed_patch([
            'patch_id' => 'PATCH-NEW',
            'replacement_markdown' => '终局文本',
            'supersedes_patch_id' => 'PATCH-OLD',
        ]),
    ]
);
resolved_patch_assert(($superseded['blocking_conflicts'] ?? []) === [], '明确取代关系不应产生冲突');
resolved_patch_assert(str_contains((string)$superseded['content'], '终局文本'), '明确取代后应采用终局文本');
resolved_patch_assert(!str_contains((string)$superseded['content'], '旧签认文本'), '被取代文字不得进入正文');

$lineEndings = GovernedTrialPatchEngine::apply("甲\r\n旧要求\r\n乙\r\n", [signed_patch(['patch_id' => 'PATCH-LF'])]);
resolved_patch_assert(($lineEndings['content'] ?? '') === "甲\n新要求\n乙\n", '只允许统一换行符并应用补丁');

echo "qms_governed_trial_patch_engine_smoke passed\n";
