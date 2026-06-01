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

function assert_throws(callable $callback, string $needle, string $message): void
{
    try {
        $callback();
    } catch (Throwable $exception) {
        assert_contains($needle, $exception->getMessage(), $message);
        return;
    }

    fwrite(STDERR, $message . PHP_EOL);
    fwrite(STDERR, 'Expected exception containing: ' . $needle . PHP_EOL);
    exit(1);
}

assert_true(
    method_exists(QmsDocumentStructureService::class, 'saveReferenceProcedureManualMatch'),
    'Structure service can save a manually reviewed reference procedure match'
);

QmsDocumentStructureService::seedAll();

$referenceTitle = 'CX-12 分包管理程序';
$reviewNote = 'reference-manual-match-smoke-' . date('YmdHis');
$procedure = Db::name('documents')
    ->where('doc_number', 'XZTC/CX-33-2022')
    ->where('level', 2)
    ->where('soft_delete', 0)
    ->field('id,doc_number,title')
    ->find();
assert_true(is_array($procedure), 'Manual-match target current procedure exists');

$referenceStructuredId = (string)Db::name('qms_structured_documents')
    ->where('doc_number', 'REF-2025-PROCEDURES')
    ->where('soft_delete', 0)
    ->value('id');
$referenceBlockId = (string)Db::name('qms_document_blocks')
    ->where('structured_document_id', $referenceStructuredId)
    ->where('section_number', 'CX-12')
    ->where('soft_delete', 0)
    ->value('id');
assert_true($referenceBlockId !== '', 'CX-12 reference block exists');

Db::name('qms_agent_suggestions')
    ->whereIn('title', [
        '对照参考程序：' . $referenceTitle,
        '块级对照参考程序：' . $referenceTitle,
    ])
    ->where('status', 'open')
    ->delete();

$beforeRows = QmsDocumentStructureService::referenceProcedureComparisonRows();
$beforeCx12 = array_values(array_filter(
    $beforeRows,
    fn (array $row): bool => (string)($row['reference_title'] ?? '') === $referenceTitle
))[0] ?? null;
assert_true($beforeCx12 === null, 'CX-12 starts as unmatched before manual review');

assert_throws(
    fn () => QmsDocumentStructureService::saveReferenceProcedureManualMatch($referenceTitle, (string)$procedure['id'], ''),
    '复核说明不能为空',
    'Manual reference match requires a review note'
);

$result = QmsDocumentStructureService::saveReferenceProcedureManualMatch(
    $referenceTitle,
    (string)$procedure['id'],
    $reviewNote
);
$manualMatchId = (string)($result['manual_match']['id'] ?? '');

try {
    assert_true($manualMatchId !== '', 'Manual reference match returns a match id');
    assert_true((string)$result['manual_match']['reference_title'] === $referenceTitle, 'Manual match stores the reference title');
    assert_true((string)$result['manual_match']['procedure_document_id'] === (string)$procedure['id'], 'Manual match stores the selected current procedure');
    assert_true((string)$result['manual_match']['match_source'] === 'manual', 'Manual match is labelled as manual');

    $rows = QmsDocumentStructureService::referenceProcedureComparisonRows();
    $cx12 = array_values(array_filter(
        $rows,
        fn (array $row): bool => (string)($row['reference_title'] ?? '') === $referenceTitle
    ))[0] ?? null;
    assert_true(is_array($cx12), 'Manual match creates a comparison row');
    assert_true((string)$cx12['current_procedure_number'] === 'XZTC/CX-33-2022', 'Manual comparison row uses the selected current procedure');
    assert_true((string)$cx12['match_source'] === 'manual', 'Manual comparison row is marked as manual');

    $unmatchedRows = QmsDocumentStructureService::referenceProcedureUnmatchedRows();
    $unmatchedCx12 = array_values(array_filter(
        $unmatchedRows,
        fn (array $row): bool => (string)($row['reference_title'] ?? '') === $referenceTitle
    ))[0] ?? null;
    assert_true($unmatchedCx12 === null, 'Manual match removes CX-12 from unmatched rows');

    $suggestion = Db::name('qms_agent_suggestions')
        ->where('suggestion_type', 'document')
        ->where('title', '块级对照参考程序：' . $referenceTitle)
        ->where('status', 'open')
        ->find();
    assert_true(is_array($suggestion), 'Manual match creates a block-level advisory comparison suggestion');
    assert_contains('现用程序：XZTC/CX-33-2022 内务与安全管理程序', (string)$suggestion['content'], 'Manual match suggestion names the selected current procedure');
    assert_contains('人工匹配', (string)$suggestion['evidence'], 'Manual match suggestion evidence records manual review');
    assert_contains('不自动修改正式体系数据', (string)$suggestion['evidence'], 'Manual match suggestion remains advisory');

    $unmatchedSuggestion = Db::name('qms_agent_suggestions')
        ->where('suggestion_type', 'document')
        ->where('title', '人工匹配参考程序：' . $referenceTitle)
        ->where('status', 'open')
        ->find();
    assert_true(!is_array($unmatchedSuggestion), 'Manual match closes the unmatched reference suggestion');

    $referenceLinkCount = Db::name('qms_document_block_links')
        ->where('block_id', $referenceBlockId)
        ->where('soft_delete', 0)
        ->count();
    assert_true($referenceLinkCount === 0, 'Manual reference match does not create formal block trace links');

    $detail = QmsDocumentStructureService::structuredDocumentDetail($referenceStructuredId);
    assert_true(count($detail['manual_match_options']['procedures'] ?? []) > 0, 'Reference detail exposes current procedure options for manual matching');

    $routeSource = file_get_contents(dirname(__DIR__) . '/route/app.php') ?: '';
    assert_contains('planning/structures/reference-match/save', $routeSource, 'Routes expose manual reference match save action');

    $controllerSource = file_get_contents(dirname(__DIR__) . '/app/controller/PlanningStructure.php') ?: '';
    assert_contains('saveReferenceMatch', $controllerSource, 'Structure controller exposes manual reference matching');

    $viewSource = file_get_contents(dirname(__DIR__) . '/app/view/planning_structure/view.html') ?: '';
    assert_contains('保存人工匹配', $viewSource, 'Structure detail page renders a manual matching action');
    assert_contains('procedure_document_id', $viewSource, 'Manual matching form submits the selected current procedure');
} finally {
    if ($manualMatchId !== '') {
        Db::name('qms_reference_procedure_matches')->where('id', $manualMatchId)->delete();
    }
    Db::name('qms_agent_suggestions')
        ->whereIn('title', [
            '对照参考程序：' . $referenceTitle,
            '块级对照参考程序：' . $referenceTitle,
            '人工匹配参考程序：' . $referenceTitle,
        ])
        ->where('status', 'open')
        ->delete();
    QmsDocumentStructureService::seedAll();
}

echo "qms_reference_procedure_manual_match_smoke passed\n";
