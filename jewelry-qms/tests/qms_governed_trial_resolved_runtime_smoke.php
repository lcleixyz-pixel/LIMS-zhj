<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\command\QmsGovernedTrialResolve;
use app\service\GovernedTrialResolvedDocumentService;
use think\facade\Db;

(new think\App())->initialize();

function resolved_runtime_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$before = (int)Db::name('qms_structured_documents')
    ->where('version', 'GOV-TRIAL/0.2')
    ->where('soft_delete', 0)
    ->count();
$inspect = GovernedTrialResolvedDocumentService::inspect();
$after = (int)Db::name('qms_structured_documents')
    ->where('version', 'GOV-TRIAL/0.2')
    ->where('soft_delete', 0)
    ->count();
resolved_runtime_assert(($inspect['mode'] ?? '') === 'inspect_only', '默认检查必须是只读模式');
resolved_runtime_assert($before === $after, '默认检查不得写入结构化文件');
resolved_runtime_assert(($inspect['summary']['document_count'] ?? 0) === 38, '默认检查应识别38份连续文件');

$disabled = GovernedTrialResolvedDocumentService::writableEnvironmentErrors(false, 'GOV-TRIAL-20260724', 'jewelry_qms');
resolved_runtime_assert($disabled !== [], '未启用试运行模式必须拒绝写入');
$wrongBatch = GovernedTrialResolvedDocumentService::writableEnvironmentErrors(true, 'WRONG-BATCH', 'jewelry_qms');
resolved_runtime_assert($wrongBatch !== [], '隔离环境批次不匹配必须拒绝写入');
$wrongDatabase = GovernedTrialResolvedDocumentService::writableEnvironmentErrors(true, 'GOV-TRIAL-20260724', 'production');
resolved_runtime_assert($wrongDatabase !== [], '数据库特征不匹配必须拒绝写入');
$allowed = GovernedTrialResolvedDocumentService::writableEnvironmentErrors(true, 'GOV-TRIAL-20260724', 'jewelry_qms');
resolved_runtime_assert($allowed === [], '8021治理试运行环境特征应通过写入闸门');

resolved_runtime_assert(class_exists(QmsGovernedTrialResolve::class), '应实现连续解析稿命令');
$console = file_get_contents(__DIR__ . '/../config/console.php') ?: '';
resolved_runtime_assert(str_contains($console, 'QmsGovernedTrialResolve'), '连续解析稿命令应注册到控制台');
$routes = file_get_contents(__DIR__ . '/../route/app.php') ?: '';
resolved_runtime_assert(str_contains($routes, 'planning/structures/resolved-artifact'), '应提供连续正文与审查材料页面路由');
$view = file_get_contents(__DIR__ . '/../app/view/planning_structure/view.html') ?: '';
resolved_runtime_assert(str_contains($view, '连续正文'), '结构化文件页面应提供连续正文入口');
resolved_runtime_assert(str_contains($view, '修订对照'), '结构化文件页面应提供修订对照入口');
resolved_runtime_assert(str_contains($view, '冲突审查'), '结构化文件页面应提供冲突审查入口');
resolved_runtime_assert(str_contains($view, '存在阻断冲突，不能提交审核'), '阻断冲突应在页面明确禁用后续审核');

if ($after === 38) {
    $recordLinks = Db::name('qms_document_block_links')->alias('link')
        ->join('qms_document_blocks block', 'block.id = link.block_id')
        ->join('qms_structured_documents structure', 'structure.id = block.structured_document_id')
        ->join('record_form_templates template', 'template.id = link.record_form_template_id')
        ->where('structure.version', 'GOV-TRIAL/0.2')
        ->where('structure.document_role', 'procedure')
        ->where('structure.soft_delete', 0)
        ->where('block.block_type', 'record_requirement')
        ->where('block.soft_delete', 0)
        ->where('link.soft_delete', 0)
        ->field('block.markdown,template.doc_number,template.canonical_doc_number,template.name')
        ->select()
        ->toArray();
    foreach ($recordLinks as $recordLink) {
        $markdown = (string)$recordLink['markdown'];
        $canonical = (string)($recordLink['canonical_doc_number'] ?? '');
        $trialNumber = (string)($recordLink['doc_number'] ?? '');
        $name = (string)($recordLink['name'] ?? '');
        resolved_runtime_assert(
            ($canonical !== '' && str_contains($markdown, $canonical))
                || ($trialNumber !== '' && str_contains($markdown, $trialNumber))
                || ($name !== '' && str_contains($markdown, $name)),
            '程序记录块不得继承正文中未出现的记录表关系'
        );
    }
}

echo "qms_governed_trial_resolved_runtime_smoke passed\n";
