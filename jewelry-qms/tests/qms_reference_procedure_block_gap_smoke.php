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
    method_exists(QmsDocumentStructureService::class, 'referenceProcedureComparisonRows'),
    'Structure service exposes block-level reference procedure comparison rows'
);

QmsDocumentStructureService::seedAll();

$rows = QmsDocumentStructureService::referenceProcedureComparisonRows();
$cx32 = array_values(array_filter(
    $rows,
    fn (array $row): bool => (string)($row['reference_title'] ?? '') === 'CX-32 管理评审程序'
))[0] ?? null;
$cx12 = array_values(array_filter(
    $rows,
    fn (array $row): bool => (string)($row['reference_title'] ?? '') === 'CX-12 分包管理程序'
))[0] ?? null;

assert_true(is_array($cx32), 'CX-32 reference procedure has a block-level comparison row');
assert_true((string)$cx32['current_procedure_number'] === 'XZTC/CX-21-2022', 'CX-32 maps to the current management review procedure');
assert_true((string)$cx32['current_procedure_title'] === '管理评审程序', 'CX-32 comparison names the current procedure title');
assert_true($cx12 === null, 'CX-12 outsourcing procedure is not mapped to an unrelated current procedure by fuzzy title matching');
assert_true($cx32['missing_labels'] === [], 'Current management review procedure covers the expected source block labels');
assert_true(
    $cx32['covered_labels'] === ['目的', '范围', '职责', '工作程序', '记录要求'],
    'Comparison row reports purpose, scope, responsibilities, process, and record requirements as covered'
);

$suggestion = Db::name('qms_agent_suggestions')
    ->where('suggestion_type', 'document')
    ->where('title', '块级对照参考程序：CX-32 管理评审程序')
    ->where('status', 'open')
    ->find();
assert_true(is_array($suggestion), 'Block-level comparison creates an advisory suggestion');
assert_contains('现用程序：XZTC/CX-21-2022 管理评审程序', (string)$suggestion['content'], 'Block-level suggestion names the matched current procedure');
assert_contains('已覆盖：目的、范围、职责、工作程序、记录要求', (string)$suggestion['content'], 'Block-level suggestion lists covered sections');
assert_contains('待补齐：无', (string)$suggestion['content'], 'Block-level suggestion states there are no missing sections for CX-32');
assert_contains('不自动修改正式体系数据', (string)$suggestion['evidence'], 'Block-level suggestion keeps the advisory-only boundary');

$staleCx12Suggestion = Db::name('qms_agent_suggestions')
    ->where('suggestion_type', 'document')
    ->where('title', '块级对照参考程序：CX-12 分包管理程序')
    ->where('status', 'open')
    ->find();
assert_true(!is_array($staleCx12Suggestion), 'Unmatched reference procedure does not keep a stale block-level comparison suggestion open');

$unmatchedSuggestion = Db::name('qms_agent_suggestions')
    ->where('suggestion_type', 'document')
    ->where('title', '人工匹配参考程序：CX-12 分包管理程序')
    ->where('status', 'open')
    ->find();
assert_true(is_array($unmatchedSuggestion), 'Unmatched reference procedure creates an advisory manual-mapping suggestion');
assert_contains('未找到可信现用程序匹配', (string)$unmatchedSuggestion['content'], 'Unmatched suggestion explains why no comparison row was created');
assert_contains('不自动修改正式体系数据', (string)$unmatchedSuggestion['evidence'], 'Unmatched suggestion keeps the formal data boundary');

$referenceStructuredId = (string)Db::name('qms_structured_documents')
    ->where('doc_number', 'REF-2025-PROCEDURES')
    ->where('soft_delete', 0)
    ->value('id');
$referenceBlockId = (string)Db::name('qms_document_blocks')
    ->where('structured_document_id', $referenceStructuredId)
    ->where('section_number', 'CX-32')
    ->where('soft_delete', 0)
    ->value('id');
$referenceLinkCount = Db::name('qms_document_block_links')
    ->where('block_id', $referenceBlockId)
    ->where('soft_delete', 0)
    ->count();
assert_true($referenceLinkCount === 0, 'Reference procedure comparison does not create formal trace links');

$detail = QmsDocumentStructureService::structuredDocumentDetail($referenceStructuredId);
$detailRows = $detail['reference_procedure_comparisons'] ?? [];
$detailCx32 = array_values(array_filter(
    $detailRows,
    fn (array $row): bool => (string)($row['reference_title'] ?? '') === 'CX-32 管理评审程序'
))[0] ?? null;
assert_true(is_array($detailCx32), 'Reference structured detail exposes block-level comparison rows');
assert_true((string)$detailCx32['current_procedure_number'] === 'XZTC/CX-21-2022', 'Reference detail comparison keeps the matched current procedure');

$detailSuggestions = $detail['document_suggestions'] ?? [];
$detailSuggestion = array_values(array_filter(
    $detailSuggestions,
    fn (array $row): bool => (string)($row['title'] ?? '') === '块级对照参考程序：CX-32 管理评审程序'
))[0] ?? null;
assert_true(is_array($detailSuggestion), 'Reference structured detail exposes block-level advisory suggestions');

$detailUnmatchedRows = $detail['reference_procedure_unmatched'] ?? [];
$detailCx12 = array_values(array_filter(
    $detailUnmatchedRows,
    fn (array $row): bool => (string)($row['reference_title'] ?? '') === 'CX-12 分包管理程序'
))[0] ?? null;
assert_true(is_array($detailCx12), 'Reference structured detail exposes unmatched reference procedure rows');

$viewSource = file_get_contents(dirname(__DIR__) . '/app/view/planning_structure/view.html') ?: '';
assert_contains('参考程序对照', $viewSource, 'Structure detail view shows reference procedure comparison section');
assert_contains('reference_procedure_comparisons', $viewSource, 'Structure detail view renders comparison rows');
assert_contains('待人工匹配', $viewSource, 'Structure detail view shows unmatched reference procedures');
assert_contains('reference_procedure_unmatched', $viewSource, 'Structure detail view renders unmatched rows');
assert_contains('document_suggestions', $viewSource, 'Structure detail view renders document-level suggestions');
assert_contains('planning/suggestions/review', $viewSource, 'Structure detail view can review advisory suggestions');
assert_contains('redirect_to', $viewSource, 'Structure detail suggestion review returns to the structured document');
assert_contains('不自动修改正式体系数据', $viewSource, 'Structure detail view states advisory-only boundary');

echo "qms_reference_procedure_block_gap_smoke passed\n";
