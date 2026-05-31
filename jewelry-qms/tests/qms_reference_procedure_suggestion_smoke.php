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

assert_true(
    method_exists(QmsDocumentStructureService::class, 'referenceProcedurePackageBlocks'),
    'Structure service can split a reference procedure package into individual procedure blocks'
);

$blocks = QmsDocumentStructureService::referenceProcedurePackageBlocks([
    'doc_number' => 'REF-2025-PROCEDURES',
    'title' => '2025年程序文件（CMA和CNAS）参考稿',
    'file_path' => '参考/2025年最新版CMA和CNAS质量体系/02-2025年程序文件（CMA和CNAS）(1).docx',
]);
$titles = array_column($blocks, 'title');
assert_true(count($blocks) >= 30, 'Reference procedure package is split into individual reference procedure blocks');
assert_true(in_array('CX-01 公正性保证程序', $titles, true), 'Reference package includes CX-01 impartiality procedure block');
assert_true(in_array('CX-32 管理评审程序', $titles, true), 'Reference package includes CX-32 management review procedure block');

$cx32 = array_values(array_filter($blocks, fn (array $block): bool => (string)$block['title'] === 'CX-32 管理评审程序'))[0] ?? null;
assert_true(is_array($cx32), 'CX-32 reference procedure block is addressable');
assert_true((string)$cx32['block_type'] === 'control_requirement', 'Reference procedure block uses a structured control requirement type');
assert_true((string)$cx32['section_number'] === 'CX-32', 'Reference procedure block keeps the CX number as section number');
assert_contains('## CX-32 管理评审程序', (string)$cx32['markdown'], 'Reference procedure block markdown has a stable heading');
assert_contains('管理评审', (string)$cx32['markdown'], 'Reference procedure block keeps management review content');

$formalCountBefore = Db::name('documents')
    ->whereIn('doc_number', ['REF-2025-PROCEDURES', 'CX-32'])
    ->where('soft_delete', 0)
    ->count();

QmsDocumentStructureService::seedAll();

$formalCountAfter = Db::name('documents')
    ->whereIn('doc_number', ['REF-2025-PROCEDURES', 'CX-32'])
    ->where('soft_delete', 0)
    ->count();
assert_true($formalCountAfter === $formalCountBefore, 'Reference procedure structuring does not create formal controlled documents');

$structuredId = (string)Db::name('qms_structured_documents')
    ->where('doc_number', 'REF-2025-PROCEDURES')
    ->where('soft_delete', 0)
    ->value('id');
assert_true($structuredId !== '', 'Reference procedure structured document exists after seeding');

$cx32Block = Db::name('qms_document_blocks')
    ->where('structured_document_id', $structuredId)
    ->where('section_number', 'CX-32')
    ->where('soft_delete', 0)
    ->find();
assert_true(is_array($cx32Block), 'Seeded structure keeps CX-32 as its own block');
assert_true((string)$cx32Block['block_type'] === 'control_requirement', 'Seeded CX-32 block keeps control requirement type');
assert_contains('## CX-32 管理评审程序', (string)$cx32Block['markdown'], 'Seeded CX-32 block keeps stable markdown heading');

$suggestion = Db::name('qms_agent_suggestions')
    ->where('suggestion_type', 'document')
    ->where('title', '对照参考程序：CX-32 管理评审程序')
    ->where('status', 'open')
    ->find();
assert_true(is_array($suggestion), 'Reference procedure block creates an advisory comparison suggestion');
assert_contains('现用程序：XZTC/CX-21-2022 管理评审程序', (string)$suggestion['content'], 'Reference procedure suggestion names the matched current procedure');
assert_contains('仅供人工复核', (string)$suggestion['content'], 'Reference procedure suggestion is advisory');
assert_contains('不自动修改正式体系数据', (string)$suggestion['evidence'], 'Reference procedure suggestion evidence keeps the formal data boundary');

echo "qms_reference_procedure_suggestion_smoke passed\n";
