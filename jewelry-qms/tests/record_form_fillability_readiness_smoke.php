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
use app\service\RecordFormReconstructionReviewService;
use app\service\RecordFormSchemaService;

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function sample_values_for_schema(array $schema): array
{
    $values = [];
    foreach ($schema as $field) {
        $key = (string)($field['key'] ?? '');
        if ($key === '') {
            continue;
        }
        if (($field['type'] ?? '') === 'repeatable_table') {
            $row = [];
            foreach (($field['columns'] ?? []) as $column) {
                $columnKey = (string)($column['key'] ?? '');
                if ($columnKey === '') {
                    continue;
                }
                $row[$columnKey] = sample_value_for_field($column);
            }
            $values[$key] = [$row];
            continue;
        }
        $values[$key] = sample_value_for_field($field);
    }

    return $values;
}

function sample_value_for_field(array $field): string
{
    if (isset($field['default']) && (string)$field['default'] !== '') {
        return (string)$field['default'];
    }

    return match ((string)($field['type'] ?? 'text')) {
        'date' => '2026-06-01',
        'number' => '1',
        'checkbox' => '1',
        'select' => (string)($field['options'][0] ?? '是'),
        'person' => '测试人员',
        'department' => '检测部',
        'signature' => '测试签名',
        default => trim((string)($field['label'] ?? '字段')) . '样例',
    };
}

function packets_by_identity(array $review): array
{
    $packets = [];
    foreach (($review['items'] ?? []) as $item) {
        $packets[(string)($item['identity_key'] ?? '')] = $item;
        $packets[(string)($item['doc_number'] ?? '')] = $packets[(string)($item['doc_number'] ?? '')] ?? $item;
    }
    return $packets;
}

function is_archive_only_record_form(array $row): bool
{
    return ($row['doc_number'] ?? '') === 'XZTC/BG-22-03'
        && str_contains((string)($row['name'] ?? ''), '现行有效标准清单');
}

$manifest = RecordFormBatchTemplateService::manifest();
$review = RecordFormReconstructionReviewService::reviewAll(null, null, 'both');
$packets = packets_by_identity($review);
$failures = [];

foreach ($manifest as $row) {
    $identity = $row['doc_number'] . '::' . pathinfo((string)$row['source_file_name'], PATHINFO_FILENAME);
    $label = $row['doc_number'] . ' ' . $row['name'] . ' [' . $row['source_file_name'] . ']';
    if (is_archive_only_record_form($row)) {
        if (($row['status'] ?? '') !== 'obsolete' || ($row['review_status'] ?? '') !== 'deferred') {
            $failures[] = $label . ' archive-only record form must stay obsolete/deferred';
        }
        if (!str_contains((string)($row['review_note'] ?? ''), 'qms_sources')) {
            $failures[] = $label . ' archive-only record form must document qms_sources handoff';
        }
        continue;
    }

    $packet = $packets[$identity] ?? $packets[$row['doc_number']] ?? null;
    if ($packet === null) {
        $failures[] = $label . ' missing reconstruction packet';
        continue;
    }
    if (($packet['decision'] ?? '') !== 'ready_for_rebuild') {
        $failures[] = $label . ' review decision is ' . ($packet['decision'] ?? '');
    }
    if (($row['status'] ?? '') !== 'published' || ($row['review_status'] ?? '') !== 'completed') {
        $failures[] = $label . ' is not published/completed: ' . ($row['status'] ?? '') . '/' . ($row['review_status'] ?? '');
    }
    if (str_starts_with((string)$row['doc_number'], '待定-')) {
        $failures[] = $label . ' still uses provisional doc number';
    }
    if (!is_file((string)$row['source_absolute_path'])) {
        $failures[] = $label . ' source file is missing';
    }
    $printTemplatePath = dirname(__DIR__) . '/app/record_form_print/' . $row['print_template_key'] . '.php';
    if (($row['print_template_key'] ?? '') === '' || ($row['print_template_key'] ?? '') === 'generic_record_form' || !is_file($printTemplatePath)) {
        $failures[] = $label . ' print template is unavailable';
    }

    $schema = RecordFormSchemaService::decode(RecordFormSchemaService::encode($row['field_schema']));
    if ($schema === []) {
        $failures[] = $label . ' schema is empty';
        continue;
    }
    $coverage = RecordFormReconstructionReviewService::schemaCoverage($schema, $packet);
    if (!($coverage['passes'] ?? false)) {
        $failures[] = $label . ' schema coverage missing: ' . implode(',', (array)($coverage['missing'] ?? []));
    }
    $sampleValues = sample_values_for_schema($schema);
    $validationErrors = RecordFormSchemaService::validateValues($schema, $sampleValues);
    if ($validationErrors !== []) {
        $failures[] = $label . ' sample values fail validation: ' . implode(',', $validationErrors);
    }
    try {
        $html = RecordFormPrintService::render((string)$row['print_template_key'], $row, $sampleValues);
        if (!str_contains($html, (string)$row['doc_number'])) {
            $failures[] = $label . ' print output misses doc number';
        }
    } catch (Throwable $exception) {
        $failures[] = $label . ' print render failed: ' . $exception->getMessage();
    }
}

assert_true($failures === [], "Fillability readiness failures:\n" . implode("\n", array_slice($failures, 0, 30)));

echo "record_form_fillability_readiness_smoke passed\n";
