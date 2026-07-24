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
resolved_runtime_assert(str_contains($view, 'blocking_message'), '治理解析稿页面应按当前状态显示阻断或可提交说明');
resolved_runtime_assert(str_contains($view, 'notice_class'), '治理解析稿页面应区分就绪与阻断提示样式');

$activeLinks = GovernedTrialResolvedDocumentService::resolvedArtifactLinks([
    'id' => 'active-test',
    'version' => 'GOV-TRIAL/0.2',
    'doc_number' => 'SIM-GOV02-XZTC/CX-01-2022',
    'status' => 'draft',
]);
resolved_runtime_assert(($activeLinks['can_submit'] ?? false) === true, '无阻断的活动文件应可进入8021 SIM审核');
$obsoleteLinks = GovernedTrialResolvedDocumentService::resolvedArtifactLinks([
    'id' => 'obsolete-test',
    'version' => 'GOV-TRIAL/0.2',
    'doc_number' => 'SIM-GOV02-XZTC/CX-35-2022',
    'status' => 'obsolete',
]);
resolved_runtime_assert(($obsoleteLinks['can_submit'] ?? true) === false, '作废保留的CX-35不得进入审核');

if ($after === 38) {
    $activeTrialReady = (int)Db::name('documents')
        ->where('version', 'GOV-TRIAL/0.2')
        ->where('status', 'trial_ready')
        ->where('soft_delete', 0)
        ->count();
    resolved_runtime_assert($activeTrialReady === 37, '除CX-35外的37份解析稿应进入SIM试运行就绪状态');
    $obsoleteCx35 = (int)Db::name('documents')
        ->where('version', 'GOV-TRIAL/0.2')
        ->where('doc_number', 'SIM-GOV02-XZTC/CX-35-2022')
        ->where('status', 'obsolete')
        ->where('soft_delete', 0)
        ->count();
    resolved_runtime_assert($obsoleteCx35 === 1, 'CX-35解析稿应在8021标为作废保留');
    $appendixBlocks = (int)Db::name('qms_document_blocks')->alias('block')
        ->join('qms_structured_documents structure', 'structure.id = block.structured_document_id')
        ->where('structure.version', 'GOV-TRIAL/0.2')
        ->where('structure.doc_number', 'SIM-GOV02-XZTC/SC')
        ->whereIn('block.title', ['附录14：程序文件目录', '附录15：各岗位任职资格条件', '附录16：质量手册条款对照表'])
        ->where('block.soft_delete', 0)
        ->count();
    resolved_runtime_assert($appendixBlocks === 3, '附录14至16应形成三个独立结构块');

    $bg3503 = Db::name('record_form_templates')->alias('template')
        ->leftJoin('documents procedure', 'procedure.id = template.procedure_doc_id')
        ->where('template.trial_batch', 'GOV-TRIAL-20260724')
        ->where('template.canonical_doc_number', 'XZTC/BG-35-03')
        ->where('template.soft_delete', 0)
        ->field('template.name,procedure.doc_number procedure_doc_number')
        ->find();
    resolved_runtime_assert(
        is_array($bg3503) && ($bg3503['name'] ?? '') === '[治理试运行] 标准物质报废申请表',
        'BG-35-03必须按记录总台账恢复为标准物质报废申请表'
    );
    resolved_runtime_assert(
        ($bg3503['procedure_doc_number'] ?? '') === 'SIM-XZTC/CX-03-02-2022',
        'BG-35-03必须关联标准物质管理程序'
    );
    $samplingTemplates = (int)Db::name('record_form_templates')
        ->where('trial_batch', 'GOV-TRIAL-20260724')
        ->where('soft_delete', 0)
        ->whereLike('name', '%抽样%')
        ->count();
    resolved_runtime_assert($samplingTemplates === 0, '不开展抽样时不得生成活动抽样记录模板');

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
