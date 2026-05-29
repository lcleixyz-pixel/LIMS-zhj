<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\service\QmsDocumentStructureService;
use app\service\QmsElementService;
use app\service\RecordFormSchemaService;
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

function cleanup_record_schema_gap_suggestion_smoke(
    string $documentId,
    string $structuredId,
    string $blockId,
    string $templateId,
    string $suggestionTitle
): void {
    Db::name('qms_document_block_links')->where('block_id', $blockId)->delete();
    Db::name('qms_document_blocks')->where('id', $blockId)->delete();
    Db::name('qms_structured_documents')->where('id', $structuredId)->delete();
    Db::name('qms_document_assets')->where('record_form_template_id', $templateId)->delete();
    Db::name('record_form_templates')->where('id', $templateId)->delete();
    Db::name('qms_document_block_links')->where('block_id', 'smoke-schema-field-block')->delete();
    Db::name('qms_document_blocks')->where('id', 'smoke-schema-field-block')->delete();
    Db::name('qms_document_assets')->where('record_form_template_id', 'smoke-schema-field-form')->delete();
    Db::name('record_form_templates')->where('id', 'smoke-schema-field-form')->delete();
    Db::name('qms_agent_suggestions')->where('id', 'smoke-schema-field-sugg')->delete();
    Db::name('qms_document_change_logs')->where('document_id', $documentId)->delete();
    Db::name('documents')->where('id', $documentId)->delete();
    Db::name('qms_agent_suggestions')->where('title', $suggestionTitle)->delete();
}

QmsDocumentStructureService::seedAll();

$documentId = 'smoke-schema-suggest-doc';
$structuredId = 'smoke-schema-suggest-struct';
$blockId = 'smoke-schema-suggest-block';
$templateId = 'smoke-schema-suggest-form';
$docNumber = 'QP-SCHEMA-SUGGEST';
$formNumber = 'SMOKE/BG-SCHEMA-02';
$formName = 'schema建议缺口记录表';
$suggestionTitle = '复核记录表格schema：' . $docNumber . ' 8 记录要求';
$sourcePath = '现用文件/程序文件/程序文件2022/13-2022内部沟通程序.docx';
$now = date('Y-m-d H:i:s');

try {
    cleanup_record_schema_gap_suggestion_smoke($documentId, $structuredId, $blockId, $templateId, $suggestionTitle);
    Db::name('documents')->insert([
        'id' => $documentId,
        'company_id' => (string)Config::get('qms.company_id'),
        'category_id' => '00000000-0000-0000-0000-000000000052',
        'template_id' => null,
        'level' => 2,
        'doc_number' => $docNumber,
        'title' => 'schema建议缺口程序',
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
        'name' => $formName,
        'module' => 'schema建议缺口',
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
        'title' => 'schema建议缺口程序',
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
        'stable_key' => 'procedure:schema_suggest_smoke:records',
        'section_number' => '8',
        'title' => '记录要求',
        'block_type' => 'record_requirement',
        'markdown' => "### 记录要求\n\n- 记录表格：{$formNumber} {$formName}\n- 责任人：质量负责人\n- 保存期限：3年\n",
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

    assert_true(
        method_exists(QmsDocumentStructureService::class, 'recordRequirementSchemaDraftForBlock'),
        'Structure service can draft a record form schema from a record requirement block'
    );
    $draft = QmsDocumentStructureService::recordRequirementSchemaDraftForBlock($blockId);
    RecordFormSchemaService::normalize($draft);
    $draftKeys = array_column($draft, 'key');
    assert_true(in_array('record_date', $draftKeys, true), 'Draft schema includes a record date field');
    assert_true(in_array('responsible_person', $draftKeys, true), 'Draft schema includes a responsible person field');
    assert_true(in_array('retention_period', $draftKeys, true), 'Draft schema includes a retention period field');
    assert_true(
        method_exists(QmsDocumentStructureService::class, 'recordRequirementSchemaFieldChecklistForBlock'),
        'Structure service exposes a human-review field checklist for schema drafts'
    );
    $fieldChecklist = QmsDocumentStructureService::recordRequirementSchemaFieldChecklistForBlock($blockId);
    $checklistByKey = [];
    foreach ($fieldChecklist as $row) {
        $checklistByKey[(string)($row['field_key'] ?? '')] = $row;
    }
    assert_true(isset($checklistByKey['responsible_person']), 'Field checklist includes responsible person');
    assert_contains('质量负责人', (string)($checklistByKey['responsible_person']['source_text'] ?? ''), 'Field checklist keeps responsible person source text');
    assert_true(isset($checklistByKey['retention_period']), 'Field checklist includes retention period');
    assert_contains('3年', (string)($checklistByKey['retention_period']['source_text'] ?? ''), 'Field checklist keeps retention source text');

    $blockLinksBefore = Db::name('qms_document_block_links')->count();
    $recordFormsBefore = Db::name('record_form_templates')->count();
    $fieldSchemaBefore = (string)Db::name('record_form_templates')->where('id', $templateId)->value('field_schema');

    $summary = QmsElementService::refreshAgentSuggestions();
    assert_true(
        (int)($summary['record_schema_gap_suggestions'] ?? 0) >= 1,
        'Agent refresh creates record schema gap suggestions'
    );

    $suggestion = Db::name('qms_agent_suggestions')
        ->where('title', $suggestionTitle)
        ->where('suggestion_type', 'record')
        ->where('status', 'open')
        ->find();
    assert_true(is_array($suggestion), 'Open record suggestion is created for schema gap');
    assert_true(($suggestion['element_id'] ?? null) === null, 'Record schema suggestion is not bound to an element');
    assert_contains('schema文档缺失', (string)$suggestion['content'], 'Suggestion content explains missing schema document');
    assert_contains('字段schema为空', (string)$suggestion['content'], 'Suggestion content explains empty field schema');
    assert_contains('候选schema草稿', (string)$suggestion['content'], 'Suggestion content includes a human-review schema draft');
    assert_contains('"key": "record_date"', (string)$suggestion['content'], 'Suggestion draft includes record date key');
    assert_contains('"key": "responsible_person"', (string)$suggestion['content'], 'Suggestion draft includes responsible person key');
    assert_contains('"key": "retention_period"', (string)$suggestion['content'], 'Suggestion draft includes retention period key');
    assert_contains($formNumber, (string)$suggestion['evidence'], 'Suggestion evidence names linked record form');
    assert_contains(
        '/record_form_template/edit?id=' . $templateId . '&schema_draft_block_id=' . $blockId,
        (string)$suggestion['evidence'],
        'Suggestion evidence links the target record form editor with the draft source block'
    );
    assert_contains('/planning/structures/view?id=' . $structuredId, (string)$suggestion['evidence'], 'Suggestion evidence links structured procedure');
    assert_contains('/planning/structures/links/review?block_id=' . $blockId, (string)$suggestion['evidence'], 'Suggestion evidence links block trace review');
    assert_contains('智能体只记录建议/缺口，不自动修改正式体系数据', (string)$suggestion['evidence'], 'Suggestion preserves agent boundary');

    $openSchemaSuggestions = QmsElementService::openRecordSchemaSuggestions(50);
    $openSchemaSuggestion = null;
    foreach ($openSchemaSuggestions as $row) {
        if ((string)($row['title'] ?? '') === $suggestionTitle) {
            $openSchemaSuggestion = $row;
            break;
        }
    }
    assert_true(is_array($openSchemaSuggestion), 'Open schema suggestions include the new schema gap');
    assert_true(
        (string)($openSchemaSuggestion['record_form_edit_url'] ?? '') === '/record_form_template/edit?id=' . $templateId . '&schema_draft_block_id=' . $blockId . '&schema_suggestion_id=' . (string)$suggestion['id'],
        'Dashboard edit action carries the candidate schema draft source block and source suggestion id'
    );

    assert_true(Db::name('qms_document_block_links')->count() === $blockLinksBefore, 'Refreshing schema suggestions does not mutate block links');
    assert_true(Db::name('record_form_templates')->count() === $recordFormsBefore, 'Refreshing schema suggestions does not create record forms');
    assert_true(
        (string)Db::name('record_form_templates')->where('id', $templateId)->value('field_schema') === $fieldSchemaBefore,
        'Refreshing schema suggestions does not alter field schema'
    );

    assert_true(
        method_exists(QmsElementService::class, 'recordSchemaDraftSaved'),
        'Element service records source trace after a human saves a candidate schema draft'
    );
    $savedTrace = QmsElementService::recordSchemaDraftSaved($templateId, $blockId, (string)$suggestion['id']);
    assert_true((int)($savedTrace['updated_links'] ?? 0) >= 1, 'Saving a candidate schema draft updates the existing record requirement trace link');
    $linkNote = (string)Db::name('qms_document_block_links')
        ->where('block_id', $blockId)
        ->where('record_form_template_id', $templateId)
        ->value('note');
    assert_contains('候选schema草稿已人工保存', $linkNote, 'Trace link note records the saved schema draft source');
    assert_contains($blockId, $linkNote, 'Trace link note keeps the source record requirement block id');
    assert_contains((string)$suggestion['id'], $linkNote, 'Trace link note keeps the source suggestion id');
    $savedEvidenceRows = QmsDocumentStructureService::recordFormRequirementEvidence($templateId);
    $savedEvidence = null;
    foreach ($savedEvidenceRows as $row) {
        if ((string)($row['block_id'] ?? '') === $blockId) {
            $savedEvidence = $row;
            break;
        }
    }
    assert_true(is_array($savedEvidence), 'Record form requirement evidence includes the saved schema source block');
    assert_contains('候选schema草稿已人工保存', (string)($savedEvidence['schema_source_note'] ?? ''), 'Record form requirement evidence exposes a dedicated schema source note');
    $acceptedSuggestion = Db::name('qms_agent_suggestions')->where('id', (string)$suggestion['id'])->find();
    assert_true((string)($acceptedSuggestion['status'] ?? '') === 'accepted', 'Saving the candidate schema draft accepts the matching record schema suggestion');
    assert_contains('保存记录表格字段配置', (string)($acceptedSuggestion['review_note'] ?? ''), 'Accepted suggestion explains the human schema save action');

    QmsElementService::refreshAgentSuggestions();
    $openReviewedSuggestion = Db::name('qms_agent_suggestions')
        ->where('title', $suggestionTitle)
        ->where('suggestion_type', 'record')
        ->where('status', 'open')
        ->find();
    assert_true(!$openReviewedSuggestion, 'Accepted record schema suggestion is not reopened automatically');

    $fieldReviewBlockId = 'smoke-schema-field-block';
    $fieldReviewTemplateId = 'smoke-schema-field-form';
    $fieldReviewSuggestionId = 'smoke-schema-field-sugg';
    Db::name('record_form_templates')->where('id', $fieldReviewTemplateId)->delete();
    Db::name('qms_document_block_links')->where('block_id', $fieldReviewBlockId)->delete();
    Db::name('qms_document_blocks')->where('id', $fieldReviewBlockId)->delete();
    Db::name('qms_agent_suggestions')->where('id', $fieldReviewSuggestionId)->delete();
    Db::name('record_form_templates')->insert([
        'id' => $fieldReviewTemplateId,
        'company_id' => (string)Config::get('qms.company_id'),
        'document_id' => null,
        'element_id' => null,
        'procedure_doc_id' => $documentId,
        'doc_number' => 'SMOKE/BG-SCHEMA-03',
        'name' => '字段复核记录表',
        'module' => 'schema字段复核',
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
    Db::name('qms_document_blocks')->insert([
        'id' => $fieldReviewBlockId,
        'company_id' => (string)Config::get('qms.company_id'),
        'structured_document_id' => $structuredId,
        'document_id' => $documentId,
        'stable_key' => 'procedure:schema_suggest_smoke:field_review_records',
        'section_number' => '8.1',
        'title' => '字段复核记录要求',
        'block_type' => 'record_requirement',
        'markdown' => "### 字段复核记录要求\n\n- 记录表格：SMOKE/BG-SCHEMA-03 字段复核记录表\n- 责任人：技术负责人\n- 保存期限：5年\n",
        'sort_order' => 810,
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
        'block_id' => $fieldReviewBlockId,
        'element_id' => null,
        'clause_id' => null,
        'manual_section_id' => null,
        'procedure_document_id' => $documentId,
        'record_form_template_id' => $fieldReviewTemplateId,
        'position_id' => null,
        'business_module_id' => null,
        'relation_type' => 'requires_record',
        'confidence' => 'high',
        'note' => 'field review smoke',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => $now,
        'modified' => $now,
    ]);
    Db::name('qms_agent_suggestions')->insert([
        'id' => $fieldReviewSuggestionId,
        'company_id' => (string)Config::get('qms.company_id'),
        'element_id' => null,
        'suggestion_type' => 'record',
        'title' => '复核记录表格schema：字段复核 smoke',
        'content' => '字段复核 smoke',
        'evidence' => '字段复核 smoke',
        'status' => 'open',
        'review_note' => null,
        'created' => $now,
        'modified' => $now,
    ]);
    $fieldSchemaBeforeReview = (string)Db::name('record_form_templates')->where('id', $fieldReviewTemplateId)->value('field_schema');
    assert_true(
        method_exists(QmsElementService::class, 'reviewRecordSchemaDraftFields'),
        'Element service records human field review decisions without saving schema'
    );
    $fieldReview = QmsElementService::reviewRecordSchemaDraftFields($fieldReviewTemplateId, $fieldReviewBlockId, [
        'record_date' => ['status' => 'accepted', 'note' => '记录日期字段可保留'],
        'responsible_person' => ['status' => 'rejected', 'note' => '改为技术负责人和审核人两个字段'],
        'retention_period' => ['status' => 'pending', 'note' => '待确认保存期限是否按5年执行'],
    ], $fieldReviewSuggestionId);
    assert_true((int)($fieldReview['reviewed_fields'] ?? 0) === 3, 'Field review records all submitted field decisions');
    $fieldReviewNote = (string)Db::name('qms_document_block_links')
        ->where('block_id', $fieldReviewBlockId)
        ->where('record_form_template_id', $fieldReviewTemplateId)
        ->value('note');
    assert_contains('字段schema复核', $fieldReviewNote, 'Trace link note records the field-level schema review');
    assert_contains('record_date=采纳', $fieldReviewNote, 'Field review note records accepted fields');
    assert_contains('responsible_person=不采用', $fieldReviewNote, 'Field review note records rejected fields');
    assert_contains('retention_period=暂缓', $fieldReviewNote, 'Field review note records pending fields');
    assert_true(
        (string)Db::name('record_form_templates')->where('id', $fieldReviewTemplateId)->value('field_schema') === $fieldSchemaBeforeReview,
        'Field review does not alter the formal record form schema'
    );
    assert_true(
        (string)Db::name('qms_agent_suggestions')->where('id', $fieldReviewSuggestionId)->value('status') === 'open',
        'Field review does not automatically accept the schema suggestion'
    );

    $dashboardSource = file_get_contents(dirname(__DIR__) . '/app/controller/PlanningDashboard.php') ?: '';
    assert_contains('openRecordSchemaSuggestions', $dashboardSource, 'Dashboard loads record schema suggestions');

    $viewSource = file_get_contents(dirname(__DIR__) . '/app/view/planning_dashboard/index.html') ?: '';
    assert_contains('记录表格schema建议', $viewSource, 'Dashboard shows record schema suggestions');
    assert_contains('record_form_edit_url', $viewSource, 'Dashboard exposes target record form edit link for schema suggestions');
    assert_contains('编辑记录表格', $viewSource, 'Dashboard labels target record form editor action');
    assert_contains('采纳建议', $viewSource, 'Dashboard allows accepting record schema suggestions');
    assert_contains('驳回建议', $viewSource, 'Dashboard allows rejecting record schema suggestions');

    $templateControllerSource = file_get_contents(dirname(__DIR__) . '/app/controller/RecordFormTemplate.php') ?: '';
    assert_contains('schema_draft_block_id', $templateControllerSource, 'Record form editor accepts a schema draft source block');
    assert_contains('schema_suggestion_id', $templateControllerSource, 'Record form editor accepts the source schema suggestion id');
    assert_contains('recordRequirementSchemaDraftForBlock', $templateControllerSource, 'Record form editor can build a candidate schema draft for preview');
    assert_contains('recordRequirementSchemaFieldChecklistForBlock', $templateControllerSource, 'Record form editor loads a human-review field checklist');
    assert_contains('recordSchemaDraftSaved', $templateControllerSource, 'Record form editor records source trace after saving a candidate schema draft');
    assert_contains('schemaDraftNotice', $templateControllerSource, 'Record form editor exposes a draft notice without saving automatically');
    assert_contains('schemaDraftChecklist', $templateControllerSource, 'Record form editor exposes the candidate field checklist');
    assert_contains('reviewSchemaDraftFields', $templateControllerSource, 'Record form editor handles field-level schema review');

    $templateEditSource = file_get_contents(dirname(__DIR__) . '/app/view/record_form_template/edit.html') ?: '';
    assert_contains('schemaDraftNotice', $templateEditSource, 'Record form edit page renders the schema draft notice');
    assert_contains('name="schema_draft_block_id"', $templateEditSource, 'Record form edit page posts the schema draft source block');
    assert_contains('name="schema_suggestion_id"', $templateEditSource, 'Record form edit page posts the schema suggestion id');
    assert_contains('候选schema草稿', $templateEditSource, 'Record form edit page labels the schema draft as a candidate');
    assert_contains('候选字段清单', $templateEditSource, 'Record form edit page shows the candidate field checklist');
    assert_contains('schemaDraftChecklist', $templateEditSource, 'Record form edit page renders checklist rows');
    assert_contains('来源文本', $templateEditSource, 'Record form edit page labels field source text');
    assert_contains('字段复核', $templateEditSource, 'Record form edit page exposes field-level review controls');
    assert_contains('field_reviews', $templateEditSource, 'Record form edit page posts field review decisions');

    $routeSource = file_get_contents(dirname(__DIR__) . '/route/app.php') ?: '';
    assert_contains('record_form_template/reviewSchemaDraftFields', $routeSource, 'Routes expose field-level schema draft review');

    $templateViewSource = file_get_contents(dirname(__DIR__) . '/app/view/record_form_template/view.html') ?: '';
    assert_contains('字段schema来源', $templateViewSource, 'Record form detail page labels schema source trace separately');
    assert_contains('schema_source_note', $templateViewSource, 'Record form detail page renders the dedicated schema source note');
} finally {
    cleanup_record_schema_gap_suggestion_smoke($documentId, $structuredId, $blockId, $templateId, $suggestionTitle);
}

echo "qms_record_schema_gap_suggestion_smoke passed\n";
