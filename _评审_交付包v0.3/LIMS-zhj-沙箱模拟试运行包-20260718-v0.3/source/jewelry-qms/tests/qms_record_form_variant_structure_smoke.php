<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\service\QmsDocumentStructureService;
use think\facade\Db;

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

QmsDocumentStructureService::seedAll();

$activeTemplateCount = (int)Db::name('record_form_templates')->where('soft_delete', 0)->count();
$structuredRows = Db::name('qms_structured_documents')
    ->alias('sd')
    ->join('qms_document_assets a', 'a.id = sd.source_asset_id')
    ->where('sd.document_role', 'record_form')
    ->where('sd.soft_delete', 0)
    ->where('a.source_kind', 'record_form')
    ->where('a.soft_delete', 0)
    ->whereNotNull('a.record_form_template_id')
    ->field('sd.id,sd.doc_number,sd.title,sd.version,sd.rendered_file_path,a.record_form_template_id,a.original_path')
    ->select()
    ->toArray();

$structuredTemplateIds = array_values(array_unique(array_map(
    static fn (array $row): string => (string)$row['record_form_template_id'],
    $structuredRows
)));
assert_same(
    $activeTemplateCount,
    count($structuredTemplateIds),
    'Every active record form template must have an independent structured markdown document'
);

$variantTemplates = Db::name('record_form_templates')
    ->where('doc_number', 'XZTC/BG-04-03')
    ->where('soft_delete', 0)
    ->field('id,doc_number,name,source_file_path')
    ->order('source_file_path', 'asc')
    ->select()
    ->toArray();
assert_true(count($variantTemplates) > 5, 'The period-check record form has multiple source-file variants');

$variantTemplateIds = array_map(static fn (array $row): string => (string)$row['id'], $variantTemplates);
$variantRows = array_values(array_filter(
    $structuredRows,
    static fn (array $row): bool => in_array((string)$row['record_form_template_id'], $variantTemplateIds, true)
));
assert_same(
    count($variantTemplates),
    count($variantRows),
    'Each same-number record form source variant must keep its own structured document'
);

$renderedPaths = [];
foreach ($variantRows as $row) {
    $path = (string)$row['rendered_file_path'];
    assert_true($path !== '', 'Record form variant stores a rendered markdown path');
    assert_true(is_file(dirname(__DIR__) . '/' . $path), 'Record form variant rendered markdown file exists');
    $renderedPaths[] = $path;

    $template = null;
    foreach ($variantTemplates as $candidate) {
        if ((string)$candidate['id'] === (string)$row['record_form_template_id']) {
            $template = $candidate;
            break;
        }
    }
    assert_true(is_array($template), 'Variant structured document can be traced back to its template');
    assert_contains((string)$template['name'], (string)$row['title'], 'Variant structured document title keeps the source-specific form name');
}
assert_same(
    count($variantRows),
    count(array_unique($renderedPaths)),
    'Same-number record form variants must not overwrite the same rendered markdown path'
);

$oldUniqueIndexRows = Db::query(
    "SELECT INDEX_NAME
     FROM information_schema.statistics
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'qms_structured_documents'
       AND INDEX_NAME = 'structured_document'
       AND NON_UNIQUE = 0"
);
assert_true(
    $oldUniqueIndexRows === [],
    'Structured documents must not be uniquely constrained by role/doc_number/version only'
);

echo "qms_record_form_variant_structure_smoke passed\n";
