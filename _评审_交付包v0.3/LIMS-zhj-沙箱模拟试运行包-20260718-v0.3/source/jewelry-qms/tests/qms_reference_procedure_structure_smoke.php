<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\service\QmsDocumentStructureService;
use app\service\QmsPlanningImportService;
use think\facade\Db;

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

function assert_not_contains(string $needle, string $haystack, string $message): void
{
    if (str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Unexpected: ' . $needle . PHP_EOL);
        exit(1);
    }
}

assert_true(
    method_exists(QmsPlanningImportService::class, 'referenceProcedureDocumentBaselines'),
    'Planning import service exposes reference procedure baselines'
);

$baselines = QmsPlanningImportService::referenceProcedureDocumentBaselines();
$baseline = array_values(array_filter(
    $baselines,
    fn (array $row): bool => (string)($row['doc_number'] ?? '') === 'REF-2025-PROCEDURES'
))[0] ?? null;
assert_true(is_array($baseline), 'Reference 2025 procedure package is listed as a baseline');
assert_true((string)$baseline['source_status'] === 'reference', 'Reference procedure baseline is not a current controlled document');
assert_true((string)$baseline['source_kind'] === 'reference_file', 'Reference procedure baseline uses reference_file asset kind');

QmsDocumentStructureService::seedAll();

$structured = Db::name('qms_structured_documents')
    ->where('document_role', 'procedure')
    ->where('doc_number', 'REF-2025-PROCEDURES')
    ->where('soft_delete', 0)
    ->find();
assert_true(is_array($structured), 'Reference procedure package is structured');
assert_true((string)$structured['source_status'] === 'reference', 'Structured reference procedure keeps reference source status');
assert_true((string)$structured['status'] === 'draft', 'Structured reference procedure remains draft and advisory');
assert_true((string)($structured['document_id'] ?? '') === '', 'Reference procedure does not create or overwrite a controlled document row');
assert_true((string)$structured['render_status'] === 'rendered', 'Reference procedure markdown is rendered');
assert_true(is_file(dirname(__DIR__) . '/' . (string)$structured['rendered_file_path']), 'Rendered reference procedure markdown file exists');

$asset = Db::name('qms_document_assets')
    ->where('source_kind', 'reference_file')
    ->where('original_path', '参考/2025年最新版CMA和CNAS质量体系/02-2025年程序文件（CMA和CNAS）(1).docx')
    ->where('soft_delete', 0)
    ->find();
assert_true(is_array($asset), 'Reference procedure source file is archived as a reference asset');
assert_true((string)$asset['archive_status'] === 'archived', 'Reference procedure asset is archived');

$blocks = Db::name('qms_document_blocks')
    ->where('structured_document_id', (string)$structured['id'])
    ->where('soft_delete', 0)
    ->order('sort_order', 'asc')
    ->select()
    ->toArray();
assert_true(count($blocks) >= 2, 'Reference procedure package has modular markdown blocks');
assert_contains('参考程序文件', (string)$blocks[0]['markdown'], 'Reference procedure overview declares its advisory role');
assert_contains('不进入正式体系文件组合包', (string)$blocks[0]['markdown'], 'Reference procedure overview keeps controlled-package boundary');

$rendered = file_get_contents(dirname(__DIR__) . '/' . (string)$structured['rendered_file_path']) ?: '';
assert_contains('REF-2025-PROCEDURES', $rendered, 'Rendered reference procedure markdown keeps the reference number');
assert_contains('公正性保证程序', $rendered, 'Rendered reference procedure markdown keeps source procedure content');

$package = QmsDocumentStructureService::renderSystemPackage();
$packageMarkdown = file_get_contents(dirname(__DIR__) . '/' . $package['output_path']) ?: '';
assert_not_contains('REF-2025-PROCEDURES', $packageMarkdown, 'Reference procedure draft is excluded from the formal system package');

echo "qms_reference_procedure_structure_smoke passed\n";
