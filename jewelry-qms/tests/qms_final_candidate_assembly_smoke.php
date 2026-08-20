<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\service\FinalCandidateAssemblyService;

(new think\App())->initialize();

function final_candidate_assembly_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$sourceDir = trim((string)getenv('QMS_FINAL_CANDIDATE_SOURCE_DIR'));
final_candidate_assembly_assert($sourceDir !== '' && is_dir($sourceDir), '必须提供最终确认稿来源目录');

$preview = FinalCandidateAssemblyService::preview($sourceDir);
final_candidate_assembly_assert(($preview['mode'] ?? '') === 'inspect_only', '预览必须明确为只检查模式');
final_candidate_assembly_assert(($preview['validation']['ok'] ?? false) === true, '65份候选正文预览必须通过校验');
final_candidate_assembly_assert(count($preview['documents'] ?? []) === 65, '预览必须包含65份候选正文');
final_candidate_assembly_assert(($preview['summary']['record_form_templates_planned'] ?? -1) === 0, '第一轮不得装配记录模板');
final_candidate_assembly_assert(($preview['summary']['record_instances_planned'] ?? -1) === 0, '第一轮不得装配真实或SIM记录');
final_candidate_assembly_assert(($preview['summary']['published_planned'] ?? -1) === 0, '第一轮不得产生published文件');
final_candidate_assembly_assert(($preview['summary']['draft_planned'] ?? 0) === 64, '应计划64份草稿');
final_candidate_assembly_assert(($preview['summary']['obsolete_planned'] ?? 0) === 1, '应计划1份废止留痕');
final_candidate_assembly_assert(($preview['summary']['time_patch_count'] ?? 0) > 0, '实际材料应形成明确时限候选补丁');

foreach ($preview['documents'] as $document) {
    final_candidate_assembly_assert(trim((string)($document['resolved_body'] ?? '')) !== '', ($document['canonical_doc_number'] ?? '文件') . ' 正文不得为空');
    final_candidate_assembly_assert(preg_match('/^[a-f0-9]{64}$/', (string)($document['resolved_text_sha256'] ?? '')) === 1, '解析正文必须登记SHA-256');
    final_candidate_assembly_assert(($document['status'] ?? '') !== 'published', '候选正文不得标记published');
}

$blockerIds = array_column($preview['decision_shortlist'] ?? [], 'id');
foreach ([
    'cross_document_conflicts',
    'supervisor_qualification_evidence',
    'cx19_retention_rule',
    'ag990_capability_path',
    'sample_lock_form_number',
    'lims_administrator_role',
] as $requiredBlocker) {
    final_candidate_assembly_assert(in_array($requiredBlocker, $blockerIds, true), '关键待决清单缺少 ' . $requiredBlocker);
}

$command = file_get_contents(__DIR__ . '/../app/command/QmsFinalCandidateAssemble.php');
final_candidate_assembly_assert(is_string($command), '候选装配命令必须存在');
foreach (['qms:assemble-final-candidate', 'source-dir', 'output', 'apply', 'ack-8021-candidate'] as $token) {
    final_candidate_assembly_assert(str_contains($command, $token), '命令缺少契约：' . $token);
}
final_candidate_assembly_assert(!str_contains($command, 'seed-samples'), '第一轮命令不得提供样例记录写入入口');

$console = file_get_contents(__DIR__ . '/../config/console.php');
final_candidate_assembly_assert(is_string($console) && str_contains($console, 'QmsFinalCandidateAssemble'), '命令必须注册到控制台');

echo "qms_final_candidate_assembly_smoke passed\n";
