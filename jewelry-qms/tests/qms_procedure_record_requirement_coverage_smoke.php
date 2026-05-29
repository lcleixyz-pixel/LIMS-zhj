<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\service\QmsDocumentStructureService;
use think\facade\Config;
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

function cleanup_procedure_record_coverage_smoke(string $documentId, string $docNumber): void
{
    $structuredIds = Db::name('qms_structured_documents')->where('document_id', $documentId)->column('id');
    if ($structuredIds !== []) {
        $blockIds = Db::name('qms_document_blocks')->whereIn('structured_document_id', $structuredIds)->column('id');
        if ($blockIds !== []) {
            Db::name('qms_document_block_links')->whereIn('block_id', $blockIds)->delete();
            Db::name('qms_document_blocks')->whereIn('id', $blockIds)->delete();
        }
        Db::name('qms_structured_documents')->whereIn('id', $structuredIds)->delete();
    }
    Db::name('qms_document_assets')->where('document_id', $documentId)->delete();
    Db::name('qms_element_documents')->where('document_id', $documentId)->delete();
    Db::name('qms_document_change_logs')->where('document_id', $documentId)->delete();
    Db::name('documents')->where('id', $documentId)->delete();
    Db::name('qms_structured_documents')->where('doc_number', $docNumber)->delete();
}

assert_true(
    method_exists(QmsDocumentStructureService::class, 'procedureRecordRequirementCoverage'),
    'Structure service exposes procedure record requirement coverage'
);

QmsDocumentStructureService::seedAll();

$documentId = 'smoke-prc-record-coverage-doc';
$docNumber = 'QP-COVERAGE-SMOKE';
$sourcePath = '现用文件/程序文件/程序文件2022/13-2022内部沟通程序.docx';
$now = date('Y-m-d H:i:s');

try {
    cleanup_procedure_record_coverage_smoke($documentId, $docNumber);
    Db::name('documents')->insert([
        'id' => $documentId,
        'company_id' => (string)Config::get('qms.company_id'),
        'category_id' => '00000000-0000-0000-0000-000000000052',
        'template_id' => null,
        'level' => 2,
        'doc_number' => $docNumber,
        'title' => '记录要求覆盖缺口程序',
        'version' => 'A/0',
        'revision' => 0,
        'department_id' => null,
        'effective_date' => null,
        'review_date' => null,
        'status' => 'published',
        'file_path' => $sourcePath,
        'file_name' => basename($sourcePath),
        'file_type' => 'docx',
        'publish' => 1,
        'soft_delete' => 0,
        'record_status' => 1,
        'created' => $now,
        'modified' => $now,
    ]);
    Db::name('qms_structured_documents')->insert([
        'id' => 'smoke-prc-record-coverage-struct',
        'company_id' => (string)Config::get('qms.company_id'),
        'source_asset_id' => null,
        'document_id' => $documentId,
        'document_role' => 'procedure',
        'doc_number' => $docNumber,
        'title' => '记录要求覆盖缺口程序',
        'version' => 'A/0',
        'source_status' => 'current',
        'status' => 'structured',
        'markdown_path' => '',
        'rendered_file_path' => '',
        'render_status' => 'not_rendered',
        'review_note' => 'smoke',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => $now,
        'modified' => $now,
    ]);
    Db::name('qms_document_blocks')->insert([
        'id' => 'smoke-prc-record-coverage-block',
        'company_id' => (string)Config::get('qms.company_id'),
        'structured_document_id' => 'smoke-prc-record-coverage-struct',
        'document_id' => $documentId,
        'stable_key' => 'procedure:coverage_smoke:purpose',
        'section_number' => '',
        'title' => '目的',
        'block_type' => 'purpose',
        'markdown' => "### 目的\n\n用于验证记录要求覆盖缺口。\n",
        'sort_order' => 100,
        'source_locator' => $sourcePath,
        'status' => 'effective',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => $now,
        'modified' => $now,
    ]);

    $coverage = QmsDocumentStructureService::procedureRecordRequirementCoverage();
    assert_true((int)($coverage['total_procedures'] ?? 0) >= 1, 'Coverage reports total procedures');
    assert_true((int)($coverage['covered_procedures'] ?? 0) >= 1, 'Coverage reports covered procedures such as QP-26');
    assert_true((int)($coverage['gap_procedures'] ?? 0) >= 1, 'Coverage reports procedure record gaps');

    $qp26 = null;
    foreach ($coverage['rows'] ?? [] as $row) {
        if ((string)($row['doc_number'] ?? '') === 'QP-26') {
            $qp26 = $row;
            break;
        }
    }
    assert_true(is_array($qp26), 'Coverage rows include QP-26');
    assert_true((int)$qp26['record_requirement_blocks'] >= 1, 'QP-26 has record requirement blocks');
    assert_true((int)$qp26['linked_record_forms'] >= 1, 'QP-26 links record forms');
    assert_true((int)$qp26['record_form_schema_documents'] >= 1, 'QP-26 linked record forms have schema documents');
    assert_true((string)$qp26['coverage_status'] === 'covered', 'QP-26 is covered');

    $gap = null;
    foreach ($coverage['gap_rows'] ?? [] as $row) {
        if ((string)($row['doc_number'] ?? '') === $docNumber) {
            $gap = $row;
            break;
        }
    }
    assert_true(is_array($gap), 'Coverage gap rows include a structured procedure without record requirements');
    assert_true((string)$gap['coverage_status'] === 'gap', 'Gap row marks coverage gap');
    assert_contains('未识别记录要求块', (string)$gap['gap_text'], 'Gap row explains missing record requirement blocks');
    assert_true((string)$gap['structure_url'] === '/planning/structures/view?id=smoke-prc-record-coverage-struct', 'Gap row links to structured document');

    $controllerSource = file_get_contents(dirname(__DIR__) . '/app/controller/PlanningStructure.php') ?: '';
    assert_contains('procedureRecordRequirementCoverage', $controllerSource, 'Structure controller assigns procedure record coverage');
    assert_contains("'recordCoverage'", $controllerSource, 'Structure controller exposes record coverage to index view');

    $viewSource = file_get_contents(dirname(__DIR__) . '/app/view/planning_structure/index.html') ?: '';
    assert_contains('程序记录要求覆盖', $viewSource, 'Structure index shows procedure record coverage panel');
    assert_contains('记录要求缺口', $viewSource, 'Structure index labels procedure record gaps');
    assert_contains('schema文档', $viewSource, 'Structure index shows record-form schema coverage');
} finally {
    cleanup_procedure_record_coverage_smoke($documentId, $docNumber);
}

echo "qms_procedure_record_requirement_coverage_smoke passed\n";
