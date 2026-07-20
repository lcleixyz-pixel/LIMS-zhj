<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$passes = [];

function source(string $relativePath): string
{
    global $root;
    $path = $root . '/' . ltrim($relativePath, '/');
    if (!is_file($path)) {
        return '';
    }

    return (string)file_get_contents($path);
}

function check(bool $condition, string $id, string $message): void
{
    global $failures, $passes;
    if ($condition) {
        $passes[] = $id . ' ' . $message;

        return;
    }

    $failures[] = $id . ' ' . $message;
}

function contains_all(string $haystack, array $needles): bool
{
    foreach ($needles as $needle) {
        if (!str_contains($haystack, $needle)) {
            return false;
        }
    }

    return true;
}

$migration = source('database/migrations/20260717_gr14_controlled_trial.sql');
$config = source('config/qms.php');
$trialService = source('app/service/TrialModeService.php');
$templateController = source('app/controller/RecordFormTemplate.php');
$instanceController = source('app/controller/RecordFormInstance.php');
$externalService = source('app/service/ExternalEvidenceReferenceService.php');
$auditFindingController = source('app/controller/AuditFinding.php');
$workflowService = source('app/service/WorkflowService.php');
$auditPlanController = source('app/controller/AuditPlan.php');

check(
    contains_all($migration, [
        'trial_ready',
        'trial_batch',
        'trial_approved_by',
        'trial_approved_at',
        'trial_note',
        'is_simulation',
        'template_version',
        'external_evidence_references',
        'source_system',
        'object_type',
        'external_number',
        'display_name',
        'readonly_url',
        'cited_at',
        'checksum_summary',
        'notes',
    ]),
    'TR01',
    '可重复迁移声明试运行模板、模拟记录和外部证据引用字段'
);

check(
    str_contains($config, "'trial_mode'")
    && str_contains($config, 'QMS_TRIAL_MODE')
    && str_contains($config, 'QMS_TRIAL_BATCH'),
    'TR02',
    '服务端配置显式声明试运行开关和批次'
);

check(
    contains_all($trialService, [
        'class TrialModeService',
        'isEnabled',
        'isTemplateUsable',
        "'published'",
        "'trial_ready'",
        'simulationNumber',
        "'SIM-'",
        'trialBatch',
        'watermarkHtml',
        '试运行/非正式受控副本',
    ]),
    'TR03',
    '试运行服务集中实施模板可用性、SIM 编号、批次和水印'
);

check(
    str_contains($templateController, 'approveTrial')
    && str_contains($templateController, 'TrialModeService')
    && !str_contains($templateController, '已自动升级为 published'),
    'TR04',
    '模板复核不会自动正式发布，试运行批准使用独立动作'
);

check(
    contains_all($instanceController, [
        'TrialModeService::isTemplateUsable',
        "'is_simulation'",
        "'trial_batch'",
        'TrialModeService::simulationNumber',
        'TrialModeService::watermarkHtml',
    ]),
    'TR05',
    '记录创建与输出由服务端强制模拟标识、批次、SIM 编号和水印'
);

check(
    contains_all($externalService, [
        'class ExternalEvidenceReferenceService',
        'source_system',
        'object_type',
        'external_number',
        'display_name',
        'readonly_url',
        'cited_at',
        'checksum_summary',
        'notes',
        "scheme !== 'https'",
        'isLoopback',
    ])
    && !str_contains($externalService, 'customer_name')
    && !str_contains($externalService, 'report_body')
    && !str_contains($externalService, 'detection_data'),
    'TR06',
    '外部证据服务只接受约定元数据和只读链接，不承载业务正文'
);

check(
    str_contains($instanceController, 'TrialModeService::simulationNumber($recordTitle)')
    && str_contains($instanceController, 'TrialModeService::isEnabled()'),
    'TR07',
    '模拟记录编辑时不能移除 SIM 标识，关闭试运行模式后不能继续改写模拟草稿'
);

check(
    contains_all($auditFindingController, [
        'createWritableFields',
        'writableFields',
        'TrialModeService::simulationNumber',
        "\$data['status'] = 'open'",
    ])
    && !str_contains($auditFindingController, '$data = $this->request->post();'),
    'TR08',
    '审核发现编号、状态和 CAPA 关联均由服务端控制'
);

check(
    str_contains($workflowService, 'allowedAdvanceFields')
    && !str_contains($workflowService, 'foreach ($data as $key => $value)'),
    'TR09',
    'CAPA 状态推进使用字段白名单，不能改写来源和关联元数据'
);

check(
    str_contains($migration, "SIGNAL SQLSTATE '45000'")
    && str_contains($migration, '停止迁移以避免缩窄状态'),
    'TR10',
    '未知状态枚举不会被迁移静默缩窄'
);

check(
    str_contains($auditPlanController, 'TrialModeService::simulationNumber')
    && str_contains($auditPlanController, '$recordId === null'),
    'TR11',
    '试运行新建内审计划由服务端强制 SIM 标识'
);

foreach ($passes as $pass) {
    echo "[PASS] {$pass}\n";
}
foreach ($failures as $failure) {
    fwrite(STDERR, "[FAIL] {$failure}\n");
}

if ($failures !== []) {
    fwrite(STDERR, sprintf("G-R14 试运行契约失败：%d 项未满足。\n", count($failures)));
    exit(1);
}

echo "G-R14 试运行契约通过。\n";
