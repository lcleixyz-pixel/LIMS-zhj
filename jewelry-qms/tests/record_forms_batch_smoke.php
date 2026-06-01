<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

if (!function_exists('root_path')) {
    function root_path(): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR;
    }
}

use app\service\RecordFormBatchTemplateService;
use app\service\RecordFormPrintService;
use app\service\RecordFormSchemaRebuilder;
use app\service\RecordFormSchemaService;

function assert_same($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function assert_contains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Missing: ' . $needle . PHP_EOL);
        exit(1);
    }
}

function batch_sample_values(array $schema): array
{
    $values = [];
    foreach ($schema as $field) {
        if ($field['type'] === 'repeatable_table') {
            $row = [];
            foreach (($field['columns'] ?? []) as $column) {
                $row[$column['key']] = match ($column['type']) {
                    'date' => '2026-05-22',
                    'number' => '1',
                    'checkbox' => '1',
                    'select' => (string)($column['options'][0] ?? ''),
                    default => $column['label'] . '样例',
                };
            }
            $values[$field['key']] = [$row];
            continue;
        }

        $values[$field['key']] = match ($field['type']) {
            'date' => '2026-05-22',
            'number' => '1',
            'checkbox' => '1',
            'select' => (string)($field['options'][0] ?? ''),
            default => $field['label'] . '样例',
        };
    }

    return $values;
}

function field_map(array $schema): array
{
    $map = [];
    foreach ($schema as $field) {
        $map[$field['key']] = $field;
    }

    return $map;
}

$manifest = RecordFormBatchTemplateService::manifest();
$schemaRegistry = RecordFormSchemaRebuilder::loadRegistry();
assert_true(!isset($schemaRegistry['待定-22-01']), 'Conflicting draft standard-list short key is not kept in schema registry');
assert_true(!isset($schemaRegistry['待定-22-01::待定-22-01-A_0']), 'Conflicting draft standard-list composite key is not kept in schema registry');
assert_true(isset($schemaRegistry['XZTC/BG-22-03']['field_schema']), 'Standard validity list is kept under the formal source record number');

$bg2201RegistryFields = field_map($schemaRegistry['XZTC/BG-22-01']['field_schema']);
foreach (['method_code_name', 'reviewer_signature', 'reviewer_date', 'technical_director_signature', 'technical_director_date'] as $key) {
    assert_true($bg2201RegistryFields[$key]['required'] ?? false, 'BG-22-01 key field is required: ' . $key);
}
assert_same('评审人日期', $bg2201RegistryFields['reviewer_date']['label'], 'BG-22-01 reviewer date label keeps signature context');
assert_same('技术负责人日期', $bg2201RegistryFields['technical_director_date']['label'], 'BG-22-01 technical director date label keeps signature context');

$bg2202RegistryFields = field_map($schemaRegistry['XZTC/BG-22-02']['field_schema']);
foreach (['method_name', 'confirmation_conclusion', 'confirmer_signature', 'confirmer_date', 'reviewer_signature', 'reviewer_date', 'tech_signature', 'tech_date'] as $key) {
    assert_true($bg2202RegistryFields[$key]['required'] ?? false, 'BG-22-02 key field is required: ' . $key);
}

$bg2203RegistryFields = field_map($schemaRegistry['XZTC/BG-22-03']['field_schema']);
assert_true($bg2203RegistryFields['standard_list']['required'] ?? false, 'BG-22-03 standard list is required');
$bg2203Columns = field_map($bg2203RegistryFields['standard_list']['columns']);
foreach (['seq', 'standard_name', 'original_code'] as $key) {
    assert_true($bg2203Columns[$key]['required'] ?? false, 'BG-22-03 standard list core column is required: ' . $key);
}
assert_contains('qms_sources', $bg2203RegistryFields['standard_list']['help_text'] ?? '', 'BG-22-03 standard list explains qms_sources freshness handoff');
$formalPersonnelPrintKeys = [
    'rf_xztc_bg_01_01_5325a1b0bd',
    'training_record',
    'rf_xztc_bg_01_03_5fa5a364df',
    'rf_xztc_bg_01_04_5fb52565ba',
    'rf_xztc_bg_01_05_66b005b382',
    'rf_xztc_bg_01_06_f268e9aaf1',
    'rf_xztc_bg_01_07_a0956d356f',
    'rf_xztc_bg_01_08_6fcb518418',
    'rf_xztc_bg_01_09_5f54bbf750',
];
$isFormalBatchTemplate = static function (array $row) use ($formalPersonnelPrintKeys): bool {
    if (in_array($row['print_template_key'], $formalPersonnelPrintKeys, true)) {
        return true;
    }

    return preg_match('/\AXZTC\/BG-03-0[1-9]\z/', (string)$row['doc_number']) === 1
        || preg_match('/\AXZTC\/BG-04-0[1-6]\z/', (string)$row['doc_number']) === 1
        || preg_match('/\AXZTC\/BG-08-0[1-9]\z/', (string)$row['doc_number']) === 1;
};
assert_same(145, count($manifest), 'Batch manifest includes import plus manual-confirm items only');
assert_same([], array_values(array_filter($manifest, fn (array $row): bool => $row['import_action'] === '跳过')), 'Batch manifest excludes historical skip items');

$manualItems = array_values(array_filter($manifest, fn (array $row): bool => $row['import_action'] === '人工确认'));
assert_same(55, count($manualItems), 'Batch manifest preserves all manual-confirm items');

$docNumbers = array_column($manifest, 'doc_number');
assert_same(19, count(array_filter($docNumbers, fn (string $number): bool => $number === 'XZTC/BG-04-03')), 'Duplicate XZTC/BG-04-03 variants are preserved alongside the generic import item');

$identityKeys = [];
foreach ($manifest as $row) {
    $identityKeys[] = $row['doc_number'] . '|' . $row['name'] . '|' . $row['source_file_name'];
    assert_true(is_file($row['source_absolute_path']), 'Source attachment exists for ' . $row['doc_number'] . ' ' . $row['source_file_name']);
    assert_same('published', $row['status'], 'Source-backed reconstructed template is published for form filling');
    assert_same('completed', $row['review_status'], 'Source-backed reconstructed template passed fillability readiness');
    assert_true($row['print_template_key'] !== '', 'Print template key is set for ' . $row['doc_number']);
    assert_true($row['print_template_key'] !== 'generic_record_form', 'Batch manifest must not use generic print template for ' . $row['doc_number']);
    assert_true(
        $row['print_template_key'] === 'training_record' || str_starts_with($row['print_template_key'], 'rf_'),
        'Batch print template key is per-source and deterministic for ' . $row['doc_number']
    );
    assert_true(
        is_file(dirname(__DIR__) . '/app/record_form_print/' . $row['print_template_key'] . '.php'),
        'Per-source print template file exists for ' . $row['print_template_key']
    );
    assert_true((string)($row['source_file_sha1'] ?? '') !== '', 'Manifest includes source file hash for ' . $row['doc_number']);

    $schema = RecordFormSchemaService::decode(RecordFormSchemaService::encode($row['field_schema']));
    assert_true($schema !== [], 'Generated schema is non-empty for ' . $row['doc_number']);
}
assert_same(count($identityKeys), count(array_unique($identityKeys)), 'Batch manifest identity keeps duplicate numbers distinct');

$printKeys = array_column($manifest, 'print_template_key');
assert_same(count($printKeys), count(array_unique($printKeys)), 'Each source attachment has its own print template key');

$formalRows = array_values(array_filter($manifest, $isFormalBatchTemplate));
assert_same(67, count($formalRows), 'Personnel, equipment, and file-control batches include the directly included equipment usage variant');

$trainingRows = array_values(array_filter($manifest, fn (array $row): bool => $row['print_template_key'] === 'training_record'));
assert_same(1, count($trainingRows), 'Personnel training record is one formal template in the batch manifest');
assert_same(
    ['training_date', 'training_topic', 'trainer', 'training_content', 'attendees', 'effect_evaluation'],
    array_column($trainingRows[0]['field_schema'], 'key'),
    'Formal training record keeps its adjusted production schema'
);

$monitorMaintenanceRows = array_values(array_filter(
    $manifest,
    fn (array $row): bool => $row['doc_number'] === 'XZTC/BG-34-01' && str_contains($row['name'], '监控维护管理记录')
));
assert_same(1, count($monitorMaintenanceRows), 'Monitoring maintenance record is present once in the batch manifest');
assert_same(
    ['maintenance_items'],
    array_column($monitorMaintenanceRows[0]['field_schema'], 'key'),
    'Monitoring maintenance record schema is driven by the source table requirement'
);
assert_same(
    ['sequence', 'maintenance_time', 'monitor_host', 'monitor_display', 'monitor_camera', 'software_system', 'maintained_by', 'remarks'],
    array_column($monitorMaintenanceRows[0]['field_schema'][0]['columns'], 'key'),
    'Monitoring maintenance record keeps procedure-required columns'
);

$monitorImageRows = array_values(array_filter(
    $manifest,
    fn (array $row): bool => $row['doc_number'] === 'XZTC/BG-34-02' && str_contains($row['name'], '监控信息图像查看记录表')
));
assert_same(1, count($monitorImageRows), 'Monitoring image view record is present once in the batch manifest');
assert_same(
    ['request_unit', 'request_person', 'view_time', 'view_purpose', 'approved_by', 'accompanied_by', 'remarks'],
    array_column($monitorImageRows[0]['field_schema'], 'key'),
    'Monitoring image view schema follows the source table and procedure approval requirement'
);

$managementReviewPlanRows = array_values(array_filter(
    $manifest,
    fn (array $row): bool => $row['doc_number'] === 'XZTC/BG-21-01' && str_contains($row['name'], '管理评审计划表')
));
assert_same(1, count($managementReviewPlanRows), 'Management review plan is present once in the batch manifest');
assert_same(
    ['review_time', 'review_place', 'host', 'review_method', 'participants', 'input_materials', 'prepared_by', 'prepared_date', 'approved_by', 'approved_date'],
    array_column($managementReviewPlanRows[0]['field_schema'], 'key'),
    'Management review plan schema follows source table fields instead of file-control generic fields'
);

$managementReviewReportRows = array_values(array_filter(
    $manifest,
    fn (array $row): bool => $row['doc_number'] === 'XZTC/BG-21-02' && str_contains($row['name'], '管理评审报告')
));
assert_same(1, count($managementReviewReportRows), 'Management review report is present once in the batch manifest');
assert_same(
    ['review_purpose', 'review_basis', 'review_time', 'review_form', 'host', 'participants', 'input_summary', 'output_conclusion', 'prepared_by', 'prepared_date', 'approved_by', 'approved_date'],
    array_column($managementReviewReportRows[0]['field_schema'], 'key'),
    'Management review report schema follows source report sections'
);

$managementReviewMeetingRows = array_values(array_filter(
    $manifest,
    fn (array $row): bool => $row['doc_number'] === 'XZTC/BG-21-03' && str_contains($row['name'], '管理评审')
));
assert_same(1, count($managementReviewMeetingRows), 'Management review meeting record is present once in the batch manifest');
assert_same(
    ['host', 'recorder_role', 'meeting_time', 'meeting_place', 'attendees', 'meeting_record', 'recorded_by', 'record_date'],
    array_column($managementReviewMeetingRows[0]['field_schema'], 'key'),
    'Management review meeting record schema follows sign-in and meeting-record source fields'
);

$standardValidityRows = array_values(array_filter(
    $manifest,
    fn (array $row): bool => $row['doc_number'] === 'XZTC/BG-22-03' && str_contains($row['name'], '现行有效标准清单')
));
assert_same(1, count($standardValidityRows), 'Current effective standards list is present once under its formal record number');
assert_same('completed', $standardValidityRows[0]['review_status'], 'Current effective standards list passed fillability readiness');
assert_same(['check_date', 'prepared_by', 'standard_list'], array_column($standardValidityRows[0]['field_schema'], 'key'), 'Current effective standards list keeps trace fields plus the repeatable standard-list field');
assert_same(
    ['seq', 'standard_name', 'original_code', 'remark'],
    array_column($standardValidityRows[0]['field_schema'][2]['columns'], 'key'),
    'Current effective standards list schema preserves source list columns'
);

$computerSoftwareRows = array_values(array_filter(
    $manifest,
    fn (array $row): bool => $row['doc_number'] === 'XZTC/BG-26-01' && str_contains($row['name'], '计算机软件登记表')
));
assert_same(1, count($computerSoftwareRows), 'Computer software register is present once in the batch manifest');
assert_same(
    ['software_items'],
    array_column($computerSoftwareRows[0]['field_schema'], 'key'),
    'Computer software register schema follows source table columns'
);
assert_same(
    ['software_code', 'software_name', 'purchase_date', 'custodian', 'remarks'],
    array_column($computerSoftwareRows[0]['field_schema'][0]['columns'], 'key'),
    'Computer software register keeps source columns'
);

$computerChangeRows = array_values(array_filter(
    $manifest,
    fn (array $row): bool => $row['doc_number'] === 'XZTC/BG-26-02' && str_contains($row['name'], '计算机内容变更申请表')
));
assert_same(1, count($computerChangeRows), 'Computer content change request is present once in the batch manifest');
assert_same(
    ['item_name', 'item_number', 'applicant', 'application_time', 'content_to_change', 'change_reason', 'changed_content', 'evaluation_or_verification', 'office_director', 'office_director_date', 'approved_by', 'approval_date'],
    array_column($computerChangeRows[0]['field_schema'], 'key'),
    'Computer content change request schema follows source application fields'
);

$authorizedSignerReviewRows = array_values(array_filter(
    $manifest,
    fn (array $row): bool => $row['doc_number'] === 'XZTC/BG-20-04' && str_contains($row['name'], '授权签字人审核记录表')
));
assert_same(1, count($authorizedSignerReviewRows), 'Authorized signer review record is present once in the batch manifest');
assert_same(
    ['record_number', 'person_name', 'position', 'professional_title', 'authorization_scope', 'responsibility_authority', 'technical_contact', 'standards_methods', 'result_evaluation', 'equipment_status', 'records_reports', 'criteria_and_mark_use', 'review_result', 'auditor', 'audit_leader', 'review_date'],
    array_column($authorizedSignerReviewRows[0]['field_schema'], 'key'),
    'Authorized signer review schema follows source yes/no review items'
);

$internalAuditCatalogRows = array_values(array_filter(
    $manifest,
    fn (array $row): bool => $row['doc_number'] === 'XZTC/BG-20-10' && str_contains($row['name'], '内部审核资料封皮目录')
));
assert_same(1, count($internalAuditCatalogRows), 'Internal audit archive catalog is present once in the batch manifest');
assert_same(
    ['audit_year', 'archived_by', 'archive_date', 'catalog_items'],
    array_column($internalAuditCatalogRows[0]['field_schema'], 'key'),
    'Internal audit archive catalog schema follows the cover directory source'
);
assert_same(
    ['sequence', 'document_name', 'included', 'remarks'],
    array_column($internalAuditCatalogRows[0]['field_schema'][3]['columns'], 'key'),
    'Internal audit archive catalog keeps source directory item columns'
);

$sampleLabelCardRows = array_values(array_filter(
    $manifest,
    fn (array $row): bool => $row['doc_number'] === 'XZTC/BG-28-02' && str_contains($row['name'], '样品标识卡')
));
assert_same(2, count($sampleLabelCardRows), 'Both sample label card source variants are present in the batch manifest');
foreach ($sampleLabelCardRows as $sampleLabelCardRow) {
    assert_same(
        ['sample_name', 'sample_number', 'sample_quantity', 'received_date', 'detection_status', 'inspector', 'inspector_time', 'photographer', 'photographer_time', 'data_entry_person', 'data_entry_time', 'packer', 'packer_time'],
        array_column($sampleLabelCardRow['field_schema'], 'key'),
        'Sample label card schema follows source card fields for ' . $sampleLabelCardRow['source_file_name']
    );
}

$rebuildTemplate = array_values(array_filter(
    $manifest,
    fn (array $row): bool => !$isFormalBatchTemplate($row)
))[0];
$rebuildHtml = RecordFormPrintService::render($rebuildTemplate['print_template_key'], $rebuildTemplate, batch_sample_values($rebuildTemplate['field_schema']));
assert_contains($rebuildTemplate['doc_number'], $rebuildHtml, 'Per-source rebuild print includes doc number');
assert_contains($rebuildTemplate['name'], $rebuildHtml, 'Per-source rebuild print includes template name');
$statusLabel = ($rebuildTemplate['status'] ?? '') === 'published' ? '受控记录表格' : '草稿状态';
assert_contains($statusLabel, $rebuildHtml, 'Rebuild print shows status-appropriate label');
assert_contains('break-inside: avoid', $rebuildHtml, 'Per-source rebuild print protects table rows from being split across pages');

$routeSource = file_get_contents(dirname(__DIR__) . '/route/app.php') ?: '';
assert_contains("record_form_template/seedBatch", $routeSource, 'Routes expose batch template builder');

$controllerSource = file_get_contents(dirname(__DIR__) . '/app/controller/RecordFormTemplate.php') ?: '';
assert_contains('RecordFormBatchTemplateService::seed', $controllerSource, 'Template controller invokes batch template service');

$serviceSource = file_get_contents(dirname(__DIR__) . '/app/service/RecordFormBatchTemplateService.php') ?: '';
assert_contains('retireGenericTemplates', $serviceSource, 'Batch build retires the previous generic template batch');

$indexSource = file_get_contents(dirname(__DIR__) . '/app/view/record_form_template/index.html') ?: '';
assert_contains('批量建立模板', $indexSource, 'Template index exposes batch build action');

$rbacSource = file_get_contents(dirname(__DIR__) . '/app/middleware/Rbac.php') ?: '';
assert_contains('seedbatch', $rbacSource, 'RBAC treats batch build as a write action');

echo "record_forms_batch_smoke passed\n";
