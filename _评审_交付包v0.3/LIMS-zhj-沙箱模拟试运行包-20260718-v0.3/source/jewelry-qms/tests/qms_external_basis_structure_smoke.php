<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\service\QmsDocumentStructureService;
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

QmsDocumentStructureService::seedAll();

$source = Db::name('qms_sources')
    ->where('source_code', 'GB/T 27025-2019')
    ->where('soft_delete', 0)
    ->find();
assert_true(is_array($source), 'GB/T 27025 source exists');

$structured = Db::name('qms_structured_documents')
    ->where('document_role', 'external_basis')
    ->where('doc_number', 'GB/T 27025-2019')
    ->where('version', (string)$source['version'])
    ->where('soft_delete', 0)
    ->find();
assert_true(is_array($structured), 'External basis source is structured as a markdown document');
assert_true((string)$structured['render_status'] === 'rendered', 'External basis markdown is rendered');
assert_true(is_file(dirname(__DIR__) . '/' . (string)$structured['rendered_file_path']), 'Rendered external basis markdown file exists');

$asset = Db::name('qms_document_assets')
    ->where('source_kind', 'external_basis')
    ->where('source_id', (string)$source['id'])
    ->where('soft_delete', 0)
    ->find();
assert_true(is_array($asset), 'External basis source file is archived as an asset');
assert_true((string)$asset['archive_status'] === 'archived', 'External basis asset is archived');

$clause = Db::name('qms_clauses')
    ->where('source_id', (string)$source['id'])
    ->where('clause_number', '6.2')
    ->where('soft_delete', 0)
    ->find();
assert_true(is_array($clause), 'GB/T 27025 clause 6.2 exists');

$block = Db::name('qms_document_blocks')
    ->alias('b')
    ->join('qms_document_block_links l', 'l.block_id = b.id AND l.soft_delete = 0')
    ->where('b.structured_document_id', (string)$structured['id'])
    ->where('b.block_type', 'clause_trace')
    ->where('b.section_number', '6.2')
    ->where('l.clause_id', (string)$clause['id'])
    ->where('b.soft_delete', 0)
    ->field('b.id,b.title,b.markdown,b.section_number,b.block_type,l.relation_type,l.confidence,l.note')
    ->find();
assert_true(is_array($block), 'External basis clause 6.2 has a traceable markdown block');
assert_contains('人员', (string)$block['title'], 'Clause block keeps the clause title');
assert_contains('条款编号：6.2', (string)$block['markdown'], 'Clause block markdown keeps the clause number');
assert_contains('GB/T 27025-2019', (string)$block['markdown'], 'Clause block markdown keeps the source code');
assert_true((string)$block['relation_type'] === 'basis', 'Clause block link uses basis relation');
assert_true((string)$block['confidence'] === 'high', 'Clause block link is high-confidence extracted evidence');

$package = QmsDocumentStructureService::renderSystemPackage();
$packageMarkdown = file_get_contents(dirname(__DIR__) . '/' . $package['output_path']) ?: '';
assert_contains('## 外部依据', $packageMarkdown, 'System package groups external basis documents');
assert_contains('GB/T 27025-2019 检测和校准实验室能力的通用要求', $packageMarkdown, 'System package includes the external basis markdown');

echo "qms_external_basis_structure_smoke passed\n";
