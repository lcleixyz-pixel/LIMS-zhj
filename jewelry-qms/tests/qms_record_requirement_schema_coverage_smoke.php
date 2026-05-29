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

function cleanup_record_requirement_schema_coverage_smoke(string $documentId, string $structuredId, string $blockId, string $templateId): void
{
    Db::name('qms_document_block_links')->where('block_id', $blockId)->delete();
    Db::name('qms_document_blocks')->where('id', $blockId)->delete();
    Db::name('qms_structured_documents')->where('id', $structuredId)->delete();
    Db::name('qms_document_assets')->where('record_form_template_id', $templateId)->delete();
    Db::name('record_form_templates')->where('id', $templateId)->delete();
    Db::name('qms_document_change_logs')->where('document_id', $documentId)->delete();
    Db::name('documents')->where('id', $documentId)->delete();
}

assert_true(
    method_exists(QmsDocumentStructureService::class, 'recordRequirementSchemaCoverage'),
    'Structure service exposes block-level record requirement schema coverage'
);

QmsDocumentStructureService::seedAll();

$documentId = 'smoke-req-schema-doc';
$structuredId = 'smoke-req-schema-struct';
$blockId = 'smoke-req-schema-block';
$templateId = 'smoke-req-schema-form';
$docNumber = 'QP-SCHEMA-COVERAGE';
$formNumber = 'SMOKE/BG-SCHEMA-01';
$sourcePath = '现用文件/程序文件/程序文件2022/13-2022内部沟通程序.docx';
$now = date('Y-m-d H:i:s');

try {
    cleanup_record_requirement_schema_coverage_smoke($documentId, $structuredId, $blockId, $templateId);
    Db::name('documents')->insert([
        'id' => $documentId,
        'company_id' => (string)Config::get('qms.company_id'),
        'category_id' => '00000000-0000-0000-0000-000000000052',
        'template_id' => null,
        'level' => 2,
        'doc_number' => $docNumber,
        'title' => 'schema覆盖缺口程序',
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
    Db::name('record_form_templates')->insert([
        'id' => $templateId,
        'company_id' => (string)Config::get('qms.company_id'),
        'document_id' => null,
        'element_id' => null,
        'procedure_doc_id' => $documentId,
        'doc_number' => $formNumber,
        'name' => 'schema覆盖缺口记录表',
        'module' => 'schema覆盖缺口',
        'source_file_path' => '',
        'source_file_name' => '',
        'source_file_sha1' => null,
        'print_template_key' => 'generic',
        'field_schema' => '[]',
        'version' => 'A/0',
        'status' => 'draft',
        'review_status' => 'pending',
        'review_note' => null,
        'reviewed_at' => null,
        'publish' => 1,
        'soft_delete' => 0,
        'created' => $now,
        'modified' => $now,
    ]);
    Db::name('qms_structured_documents')->insert([
        'id' => $structuredId,
        'company_id' => (string)Config::get('qms.company_id'),
        'source_asset_id' => null,
        'document_id' => $documentId,
        'document_role' => 'procedure',
        'doc_number' => $docNumber,
        'title' => 'schema覆盖缺口程序',
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
        'id' => $blockId,
        'company_id' => (string)Config::get('qms.company_id'),
        'structured_document_id' => $structuredId,
        'document_id' => $documentId,
        'stable_key' => 'procedure:schema_coverage_smoke:records',
        'section_number' => '8',
        'title' => '记录要求',
        'block_type' => 'record_requirement',
        'markdown' => "### 记录要求\n\n- 记录表格：{$formNumber} schema覆盖缺口记录表\n- 保存期限：3年\n",
        'sort_order' => 800,
        'source_locator' => $sourcePath,
        'status' => 'effective',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => $now,
        'modified' => $now,
    ]);
    Db::name('qms_document_block_links')->insert([
        'id' => qms_uuid(),
        'company_id' => (string)Config::get('qms.company_id'),
        'block_id' => $blockId,
        'element_id' => null,
        'clause_id' => null,
        'manual_section_id' => null,
        'procedure_document_id' => $documentId,
        'record_form_template_id' => $templateId,
        'position_id' => null,
        'business_module_id' => null,
        'relation_type' => 'requires_record',
        'confidence' => 'high',
        'note' => 'smoke',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => $now,
        'modified' => $now,
    ]);

    $coverage = QmsDocumentStructureService::recordRequirementSchemaCoverage();
    assert_true((int)($coverage['total_requirement_blocks'] ?? 0) >= 1, 'Coverage counts record requirement blocks');
    assert_true((int)($coverage['gap_blocks'] ?? 0) >= 1, 'Coverage counts schema gap blocks');

    $smokeRow = null;
    foreach ($coverage['gap_rows'] ?? [] as $row) {
        if ((string)($row['block_id'] ?? '') === $blockId) {
            $smokeRow = $row;
            break;
        }
    }
    assert_true(is_array($smokeRow), 'Coverage exposes the smoke record requirement block as a schema gap');
    assert_true((string)$smokeRow['coverage_status'] === 'gap', 'Smoke row is marked as a schema gap');
    assert_true((int)$smokeRow['linked_record_forms'] === 1, 'Smoke row keeps linked record form count');
    assert_true((int)$smokeRow['schema_documents'] === 0, 'Smoke row identifies missing schema document');
    assert_true((int)$smokeRow['schema_field_count'] === 0, 'Smoke row identifies empty field schema');
    assert_contains('schema文档缺失', (string)$smokeRow['gap_text'], 'Gap explains missing schema document');
    assert_contains('字段schema为空', (string)$smokeRow['gap_text'], 'Gap explains empty schema fields');
    assert_true((string)$smokeRow['structure_url'] === '/planning/structures/view?id=' . $structuredId, 'Smoke row links to structured procedure');
    assert_true((string)$smokeRow['trace_review_url'] === '/planning/structures/links/review?block_id=' . $blockId, 'Smoke row links to block trace review');
    assert_contains($formNumber, (string)$smokeRow['record_form_labels'], 'Smoke row shows linked record form label');

    $controllerSource = file_get_contents(dirname(__DIR__) . '/app/controller/PlanningStructure.php') ?: '';
    assert_contains('recordRequirementSchemaCoverage', $controllerSource, 'Structure controller assigns block-level record schema coverage');
    assert_contains("'recordSchemaCoverage'", $controllerSource, 'Structure controller exposes record schema coverage to index view');

    $viewSource = file_get_contents(dirname(__DIR__) . '/app/view/planning_structure/index.html') ?: '';
    assert_contains('记录要求 schema 复核', $viewSource, 'Structure index shows record requirement schema review panel');
    assert_contains('字段schema', $viewSource, 'Structure index shows field schema status');
    assert_contains('复核追溯', $viewSource, 'Structure index links schema gaps back to trace review');
} finally {
    cleanup_record_requirement_schema_coverage_smoke($documentId, $structuredId, $blockId, $templateId);
}

echo "qms_record_requirement_schema_coverage_smoke passed\n";
