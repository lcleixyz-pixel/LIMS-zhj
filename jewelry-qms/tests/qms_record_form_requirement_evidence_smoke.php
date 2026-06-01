<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\service\QmsDocumentStructureService;
use app\service\QmsElementService;
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

QmsElementService::seedAll();
QmsDocumentStructureService::seedAll();

$templateId = (string)Db::name('record_form_templates')
    ->where('doc_number', 'XZTC/BG-26-01')
    ->where('soft_delete', 0)
    ->value('id');

assert_true($templateId !== '', 'Computer software record form template exists');
assert_true(method_exists(QmsDocumentStructureService::class, 'recordFormRequirementEvidence'), 'Structure service exposes record form requirement evidence');

$rows = QmsDocumentStructureService::recordFormRequirementEvidence($templateId);
assert_true(count($rows) >= 1, 'Record form detail can read procedure requirement evidence');

$evidence = null;
foreach ($rows as $row) {
    if ((string)($row['procedure_number'] ?? '') === 'XZTC/CX-26-2022'
        && (string)($row['block_type'] ?? '') === 'record_requirement'
        && (string)($row['relation_type'] ?? '') === 'requires_record'
        && str_contains((string)($row['block_title'] ?? ''), '记录要求')) {
        $evidence = $row;
        break;
    }
}

assert_true(is_array($evidence), 'Record form evidence includes the XZTC/CX-26-2022 record requirement block');
assert_true((string)$evidence['record_form_template_id'] === $templateId, 'Evidence keeps the record form template id');
assert_true((string)$evidence['doc_number'] === 'XZTC/CX-26-2022', 'Evidence names the structured procedure document');
assert_true((string)$evidence['document_role'] === 'procedure', 'Evidence comes from procedure structured document');
assert_true((string)$evidence['procedure_title'] !== '', 'Evidence names the source procedure title');
assert_true(str_contains((string)$evidence['block_title'], '记录要求'), 'Evidence points to a record requirement content block');
assert_true(str_contains((string)$evidence['markdown'], 'schema来源'), 'Requirement block explains schema source');
assert_true((string)$evidence['document_url'] === '/planning/structures/view?id=' . (string)$evidence['structured_document_id'], 'Evidence links back to structured procedure');
assert_true((string)$evidence['review_url'] === '/planning/structures/links/review?block_id=' . (string)$evidence['block_id'], 'Evidence links back to trace review');

$controllerSource = file_get_contents(dirname(__DIR__) . '/app/controller/RecordFormTemplate.php') ?: '';
assert_contains('recordFormRequirementEvidence', $controllerSource, 'Record form template controller assigns procedure requirement evidence');
assert_contains('requirementEvidence', $controllerSource, 'Record form template controller exposes evidence to the view');

$viewSource = file_get_contents(dirname(__DIR__) . '/app/view/record_form_template/view.html') ?: '';
assert_contains('程序要求追溯', $viewSource, 'Record form detail page shows procedure requirement evidence section');
assert_contains('requirementEvidence', $viewSource, 'Record form detail page renders evidence rows');
assert_contains('复核追溯', $viewSource, 'Record form detail page links back to block trace review');

echo "qms_record_form_requirement_evidence_smoke passed\n";
