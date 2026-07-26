<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\service\G2ExpansionBatch4BlueprintService;
use app\service\QmsCx0302GovernanceClosureService;
use app\service\QmsDocumentStructureService;

function cx0302_closure_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$templates = [];
foreach (G2ExpansionBatch4BlueprintService::templates() as $template) {
    $templates[(string)$template['doc_number']] = $template;
}

$bg3503 = $templates['XZTC/BG-35-03'] ?? null;
cx0302_closure_assert(is_array($bg3503), '缺少 XZTC/BG-35-03 蓝图');

$actualKeys = array_map(
    static fn(array $field): string => (string)($field['key'] ?? ''),
    (array)($bg3503['field_schema'] ?? [])
);
$expectedKeys = [
    'material_name',
    'material_code',
    'model_spec',
    'purchase_date',
    'handling_status',
    'application_date',
    'scrap_reason',
    'equipment_custodian_signature',
    'equipment_custodian_date',
    'applicant',
    'department_head',
    'testing_personnel',
    'review_opinion',
    'reviewer_signature',
    'review_date',
    'approval_opinion',
    'approver_signature',
    'approval_date',
    'remarks',
];
cx0302_closure_assert(
    $actualKeys === $expectedKeys,
    'BG-35-03 字段必须逐项对应现用纸质表单，实际为：' . implode(', ', $actualKeys)
);

cx0302_closure_assert(
    method_exists(QmsDocumentStructureService::class, 'structureRecordFormTemplate'),
    '缺少单个记录模板定向结构化能力'
);
cx0302_closure_assert(
    class_exists(QmsCx0302GovernanceClosureService::class),
    '缺少 CX-03-02 定向治理闭环服务'
);
cx0302_closure_assert(
    method_exists(QmsCx0302GovernanceClosureService::class, 'preview')
    && method_exists(QmsCx0302GovernanceClosureService::class, 'apply'),
    'CX-03-02 定向治理闭环服务必须同时支持 preview 与 apply'
);

echo "qms_cx0302_governance_closure_service_smoke passed\n";
