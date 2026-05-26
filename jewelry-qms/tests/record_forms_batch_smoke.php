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

$manifest = RecordFormBatchTemplateService::manifest();
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
    if ($isFormalBatchTemplate($row)) {
        assert_same('published', $row['status'], 'Formal reconstructed template stays published');
        assert_same('completed', $row['review_status'], 'Formal reconstructed template is completed');
    } else {
        assert_same('draft', $row['status'], 'High-fidelity rebuild templates stay draft until reviewed');
        assert_same('needs_fidelity', $row['review_status'], 'High-fidelity rebuild templates are marked for reconstruction');
    }
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
assert_same(66, count($formalRows), 'Personnel, equipment, and file-control batches have 66 formal templates in the batch manifest');

$trainingRows = array_values(array_filter($manifest, fn (array $row): bool => $row['print_template_key'] === 'training_record'));
assert_same(1, count($trainingRows), 'Personnel training record is one formal template in the batch manifest');
assert_same(
    ['training_date', 'training_topic', 'trainer', 'training_content', 'attendees', 'effect_evaluation'],
    array_column($trainingRows[0]['field_schema'], 'key'),
    'Formal training record keeps its adjusted production schema'
);

$draftTemplate = array_values(array_filter(
    $manifest,
    fn (array $row): bool => !$isFormalBatchTemplate($row)
))[0];
$draftHtml = RecordFormPrintService::render($draftTemplate['print_template_key'], $draftTemplate, batch_sample_values($draftTemplate['field_schema']));
assert_contains($draftTemplate['doc_number'], $draftHtml, 'Per-source draft print includes doc number');
assert_contains($draftTemplate['name'], $draftHtml, 'Per-source draft print includes template name');
assert_contains('高保真重构草稿', $draftHtml, 'Draft print clearly marks templates that still need fidelity review');
assert_contains('break-inside: avoid', $draftHtml, 'Per-source draft print protects table rows from being split across pages');

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
