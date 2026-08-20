<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\service\FinalCandidateAssemblyService;
use think\facade\Db;

(new think\App())->initialize();

function final_candidate_runtime_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function final_candidate_runtime_fingerprint(string $table, string $fields, string $order): array
{
    $rows = Db::name($table)->field($fields)->order($order)->select()->toArray();
    return [
        'count' => count($rows),
        'sha256' => hash('sha256', json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
    ];
}

$sourceDir = trim((string)getenv('QMS_FINAL_CANDIDATE_SOURCE_DIR'));
$outputDir = trim((string)getenv('QMS_FINAL_CANDIDATE_OUTPUT_DIR'));
final_candidate_runtime_assert($sourceDir !== '' && is_dir($sourceDir), '必须提供最终确认稿来源目录');
final_candidate_runtime_assert($outputDir !== '', '必须提供候选交接目录');

$protectedBefore = [
    'templates' => final_candidate_runtime_fingerprint(
        'record_form_templates',
        'id,doc_number,version,status,trial_batch,publish,soft_delete,modified',
        'id'
    ),
    'instances' => final_candidate_runtime_fingerprint(
        'record_form_instances',
        'id,doc_number,status,is_simulation,trial_batch,modified',
        'id'
    ),
];
$oldVersionsBefore = [
    'v01' => (int)Db::name('qms_structured_documents')->where('version', 'GOV-TRIAL/0.1')->where('soft_delete', 0)->count(),
    'v02' => (int)Db::name('qms_structured_documents')->where('version', 'GOV-TRIAL/0.2')->where('soft_delete', 0)->count(),
];

$first = FinalCandidateAssemblyService::apply($sourceDir, $outputDir);
final_candidate_runtime_assert(($first['mode'] ?? '') === 'trial_apply', '写入结果必须标记trial_apply');
final_candidate_runtime_assert(($first['validation']['ok'] ?? false) === true, '写入后验证必须通过');

$candidateDocuments = Db::name('documents')
    ->where('version', 'GOV-TRIAL/0.3')
    ->whereLike('doc_number', 'SIM-GOV03-%')
    ->where('soft_delete', 0)
    ->order('doc_number')
    ->select()
    ->toArray();
final_candidate_runtime_assert(count($candidateDocuments) === 65, '数据库必须有65份0.3候选文件');
final_candidate_runtime_assert(count(array_filter($candidateDocuments, static fn(array $row): bool => $row['status'] === 'draft')) === 64, '数据库必须有64份草稿');
final_candidate_runtime_assert(count(array_filter($candidateDocuments, static fn(array $row): bool => $row['status'] === 'obsolete')) === 1, '数据库必须有1份废止留痕');
final_candidate_runtime_assert(count(array_filter($candidateDocuments, static fn(array $row): bool => $row['status'] === 'published')) === 0, '数据库不得有published候选');
final_candidate_runtime_assert(count(array_filter($candidateDocuments, static fn(array $row): bool => (int)$row['publish'] !== 0)) === 0, '候选文件publish标志必须为0');
final_candidate_runtime_assert(count(array_filter($candidateDocuments, static fn(array $row): bool => !empty($row['approved_by']) || !empty($row['effective_date']))) === 0, '候选文件不得带批准人或生效日期');

$candidateStructures = Db::name('qms_structured_documents')
    ->where('version', 'GOV-TRIAL/0.3')
    ->whereLike('doc_number', 'SIM-GOV03-%')
    ->where('soft_delete', 0)
    ->order('doc_number')
    ->select()
    ->toArray();
final_candidate_runtime_assert(count($candidateStructures) === 65, '数据库必须有65份0.3结构化文件');
final_candidate_runtime_assert(count(array_filter($candidateStructures, static fn(array $row): bool => $row['status'] === 'published')) === 0, '结构化候选不得published');

$candidateBlocks = (int)Db::name('qms_document_blocks')->alias('block')
    ->join('qms_structured_documents structure', 'structure.id=block.structured_document_id')
    ->where('structure.version', 'GOV-TRIAL/0.3')
    ->whereLike('structure.doc_number', 'SIM-GOV03-%')
    ->where('structure.soft_delete', 0)
    ->where('block.soft_delete', 0)
    ->count();
final_candidate_runtime_assert($candidateBlocks >= 65, '每份候选至少应形成一个内容块');

$templateLinks = (int)Db::name('qms_document_block_links')->alias('link')
    ->join('qms_document_blocks block', 'block.id=link.block_id')
    ->join('qms_structured_documents structure', 'structure.id=block.structured_document_id')
    ->where('structure.version', 'GOV-TRIAL/0.3')
    ->whereNotNull('link.record_form_template_id')
    ->where('link.soft_delete', 0)
    ->count();
final_candidate_runtime_assert($templateLinks === 0, '第一轮候选不得连接或装配记录模板');

$idsAfterFirst = array_column($candidateDocuments, 'id', 'doc_number');
$second = FinalCandidateAssemblyService::apply($sourceDir, $outputDir);
final_candidate_runtime_assert(($second['validation']['ok'] ?? false) === true, '幂等重跑必须通过验证');
$idsAfterSecond = array_column(
    Db::name('documents')
        ->where('version', 'GOV-TRIAL/0.3')
        ->whereLike('doc_number', 'SIM-GOV03-%')
        ->where('soft_delete', 0)
        ->order('doc_number')
        ->select()
        ->toArray(),
    'id',
    'doc_number'
);
final_candidate_runtime_assert($idsAfterSecond === $idsAfterFirst, '幂等重跑不得生成重复文件或更换文件ID');

$protectedAfter = [
    'templates' => final_candidate_runtime_fingerprint(
        'record_form_templates',
        'id,doc_number,version,status,trial_batch,publish,soft_delete,modified',
        'id'
    ),
    'instances' => final_candidate_runtime_fingerprint(
        'record_form_instances',
        'id,doc_number,status,is_simulation,trial_batch,modified',
        'id'
    ),
];
final_candidate_runtime_assert($protectedAfter === $protectedBefore, '现有模板或记录实例发生变化');
final_candidate_runtime_assert((int)Db::name('record_form_templates')->where('status', 'trial_ready')->where('soft_delete', 0)->count() === 104, '104张trial_ready模板必须保持不变');
final_candidate_runtime_assert((int)Db::name('qms_structured_documents')->where('version', 'GOV-TRIAL/0.1')->where('soft_delete', 0)->count() === $oldVersionsBefore['v01'], 'GOV-TRIAL/0.1不得变化');
final_candidate_runtime_assert((int)Db::name('qms_structured_documents')->where('version', 'GOV-TRIAL/0.2')->where('soft_delete', 0)->count() === $oldVersionsBefore['v02'], 'GOV-TRIAL/0.2不得变化');
final_candidate_runtime_assert(is_file($outputDir . '/07-写入报告-v0.1.json'), '必须生成写入报告');

echo "qms_final_candidate_runtime_smoke passed\n";
