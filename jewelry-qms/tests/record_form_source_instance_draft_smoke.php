<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\model\RecordFormTemplate;
use app\service\PdfRenderService;
use app\service\RecordFormBatchReviewService;
use app\service\RecordFormBusinessContentService;
use app\service\RecordFormCandidateCompletionService;
use app\service\RecordFormLayoutConfirmationService;
use app\service\RecordFormPdfLayoutAuditService;
use app\service\RecordFormSchemaService;
use app\service\RecordFormSourceInstanceDraftService;
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

function source_draft_template(string $docNumber): RecordFormTemplate
{
    $template = RecordFormTemplate::where('doc_number', $docNumber)
        ->where('soft_delete', 0)
        ->where('status', 'published')
        ->order('created', 'asc')
        ->find();
    assert_true((bool)$template, 'Template exists: ' . $docNumber);

    return $template;
}

$trainingTemplate = source_draft_template('XZTC/BG-01-02');
$training = RecordFormSourceInstanceDraftService::prepareTemplate($trainingTemplate, true);
assert_true($training['decision'] === 'ready', 'Training source draft is ready');
assert_true(($training['values']['training_date'] ?? '') === '2025-11-20', 'Training date is parsed from source');
assert_true(($training['values']['training_topic'] ?? '') === '质检驳回留证要求规范', 'Training topic is parsed from source');
assert_true(($training['values']['trainer'] ?? '') === '张晓磊', 'Trainer is parsed from source');
assert_contains('驳回标签选择规范', (string)($training['values']['training_content'] ?? ''), 'Training content is parsed from source');
assert_true(in_array('attendees', $training['low_confidence_fields'] ?? [], true), 'Unclear attendee table is marked low-confidence');
assert_true(
    !str_contains(implode(' ', $training['evidence'] ?? []), 'AI辅助候选'),
    'AI assistance is optional and should not be required for deterministic training extraction'
);

$equipmentTemplate = source_draft_template('XZTC/BG-03-01');
$equipment = RecordFormSourceInstanceDraftService::prepareTemplate($equipmentTemplate, false);
assert_true($equipment['decision'] === 'ready', 'Equipment ledger source draft is ready');
$equipmentRows = $equipment['values']['equipment_items'] ?? [];
assert_true(is_array($equipmentRows) && count($equipmentRows) >= 10, 'Equipment ledger parses multiple rows');
assert_true(($equipmentRows[0]['equipment_code'] ?? '') === 'XZTC-CJY01', 'Equipment row keeps equipment code');
assert_contains('X射线荧光光谱仪', (string)($equipmentRows[0]['equipment_name'] ?? ''), 'Equipment row keeps equipment name');

$authorizedTemplate = source_draft_template('XZTC/BG-20-04');
$authorized = RecordFormSourceInstanceDraftService::prepareTemplate($authorizedTemplate, false, true, 2025);
assert_true(($authorized['values']['person_name'] ?? '') !== '', 'Application profile fills authorized signatory candidate');
assert_true(($authorized['values']['standards_methods'] ?? '') === '是', 'Application profile fills authorized signatory yes/no review items');
assert_true(($authorized['values']['review_result'] ?? '') === '授权签字人评审合格', 'Application profile fills authorized signatory review result');

$methodTemplate = source_draft_template('XZTC/BG-22-02');
$method = RecordFormSourceInstanceDraftService::prepareTemplate($methodTemplate, false, true, 2025);
assert_contains('GB/T 16553-2017', (string)($method['values']['method_name'] ?? ''), 'Application profile fills method standard candidate');
assert_contains('红外光谱仪', (string)($method['values']['equipment_name'] ?? ''), 'Application profile fills equipment candidates for method confirmation');
assert_true(($method['values']['confirm_equipment'] ?? '') === '1', 'Application profile checks equipment confirmation candidate');

$standardCheckTemplate = source_draft_template('XZTC/BG-24-03');
$standardCheck = RecordFormSourceInstanceDraftService::prepareTemplate($standardCheckTemplate, false, true, 2025);
$standardRows = $standardCheck['values']['standards'] ?? [];
assert_true(is_array($standardRows) && count($standardRows) >= 3, 'Application profile fills standard check repeatable rows');
assert_contains('GB/T 16552-2017', (string)($standardRows[0]['standard_code'] ?? ''), 'Application profile keeps standard code in standard check rows');

$readonlySchema = RecordFormSchemaService::decode(RecordFormSchemaService::encode([
    ['key' => 'equipment_name', 'label' => '设备名称', 'type' => 'text', 'readonly' => true, 'default' => '电子天平'],
    ['key' => 'check_date', 'label' => '核查日期', 'type' => 'date', 'required' => true],
]));
$enforced = RecordFormSchemaService::enforceReadonly($readonlySchema, [
    'equipment_name' => '源文件篡改值',
    'check_date' => '2026-06-03',
]);
assert_true($enforced['equipment_name'] === '电子天平', 'Readonly fields still fall back to schema defaults');

$before = (int)Db::name('record_form_instances')
    ->where('record_title', 'like', '基础运行记录-XZTC/BG-01-02-%')
    ->count();
$dryRun = RecordFormSourceInstanceDraftService::seed([
    'doc_number' => 'XZTC/BG-01-02',
    'limit' => 1,
    'apply' => false,
    'preview_pdf' => false,
    'ai' => false,
]);
$afterDryRun = (int)Db::name('record_form_instances')
    ->where('record_title', 'like', '基础运行记录-XZTC/BG-01-02-%')
    ->count();
assert_true($dryRun['dry_run'] === 1 && $before === $afterDryRun, 'Dry-run does not create instances');

$moduleDryRun = RecordFormSourceInstanceDraftService::seed([
    'module' => '人员培训程序',
    'limit' => 1,
    'apply' => false,
    'preview_pdf' => false,
    'ai' => false,
]);
assert_true($moduleDryRun['dry_run'] === 1, 'Module limit counts ready drafts, not the first manual-input template');
assert_true($moduleDryRun['needs_manual_input'] >= 1, 'Module dry-run reports skipped manual-input templates before the first ready draft');
assert_true((string)($moduleDryRun['rows'][array_key_last($moduleDryRun['rows'])]['doc_number'] ?? '') === 'XZTC/BG-01-02', 'Module dry-run reaches the first ready training record');

$createdIds = [];
try {
    foreach ([1, 2] as $run) {
        $summary = RecordFormSourceInstanceDraftService::seed([
            'doc_number' => 'XZTC/BG-01-02',
            'limit' => 1,
            'apply' => true,
            'preview_pdf' => $run === 1,
            'ai' => false,
        ]);
        assert_true($summary['created'] === 1, 'Apply creates one draft instance');
        $row = $summary['rows'][0] ?? [];
        $createdIds[] = (string)($row['instance_id'] ?? '');
        assert_true((string)($row['instance_id'] ?? '') !== '', 'Created summary exposes instance id');
        if ($run === 1) {
            assert_true((string)($row['preview_pdf']['file_path'] ?? '') !== '', 'Preview PDF path is returned');
            assert_contains('/record_form_instance/downloadPreviewPdf', (string)($row['preview_pdf']['download_url'] ?? ''), 'Preview PDF download URL is returned');
            assert_true(is_file(root_path() . (string)$row['preview_pdf']['file_path']), 'Preview PDF file exists under runtime');
        }
    }
    assert_true(count(array_unique($createdIds)) === 2, 'Repeated apply creates new drafts instead of overwriting');
    $records = Db::name('record_form_instances')->whereIn('id', $createdIds)->select()->toArray();
    foreach ($records as $record) {
        assert_true((string)$record['status'] === 'draft', 'Source-filled instances remain draft');
        assert_true((string)($record['generated_pdf_path'] ?? '') === '', 'Preview PDF does not write formal generated PDF path');
    }
} finally {
    if ($createdIds !== []) {
        Db::name('record_form_instances')->whereIn('id', array_filter($createdIds))->delete();
        foreach ($createdIds as $id) {
            $dir = root_path() . 'runtime' . DIRECTORY_SEPARATOR . 'record-form-preview-pdf' . DIRECTORY_SEPARATOR . $id;
            if (is_dir($dir)) {
                $files = glob($dir . DIRECTORY_SEPARATOR . '*') ?: [];
                foreach ($files as $file) {
                    @unlink($file);
                }
                @rmdir($dir);
            }
        }
    }
}

assert_true(method_exists(PdfRenderService::class, 'renderHtmlPreview'), 'PDF service exposes non-mutating preview rendering');

$fileServiceSource = file_get_contents(dirname(__DIR__) . '/app/service/FileService.php') ?: '';
assert_contains('downloadAbsolute', $fileServiceSource, 'File service can stream validated runtime preview PDFs');

$controllerSource = file_get_contents(dirname(__DIR__) . '/app/controller/RecordFormInstance.php') ?: '';
assert_contains('downloadPreviewPdf', $controllerSource, 'Instance controller exposes preview PDF download action');
assert_contains('previewPdfFiles', $controllerSource, 'Instance controller assigns preview PDF files to detail view');
assert_contains('updateLayoutStatus', $controllerSource, 'Instance controller exposes manual layout confirmation action');

$routeSource = file_get_contents(dirname(__DIR__) . '/route/app.php') ?: '';
assert_contains('record_form_instance/downloadPreviewPdf', $routeSource, 'Route table exposes preview PDF download URL');
assert_contains('record_form_instance/updateLayoutStatus', $routeSource, 'Route table exposes manual layout confirmation URL');
assert_contains('record_form_instance/reviewArtifact', $routeSource, 'Route table exposes review artifact URL');

$rbacSource = file_get_contents(dirname(__DIR__) . '/app/middleware/Rbac.php') ?: '';
assert_contains('updatelayoutstatus', $rbacSource, 'RBAC treats manual layout confirmation as a write action');

$viewSource = file_get_contents(dirname(__DIR__) . '/app/view/record_form_instance/view.html') ?: '';
assert_contains('下载临时预览PDF', $viewSource, 'Instance detail view renders preview PDF download link');

$reviewDashboard = RecordFormBatchReviewService::build(2025);
assert_true(isset($reviewDashboard['summary']['total']), 'Review dashboard exposes summary totals');
assert_true(is_file(root_path() . (string)($reviewDashboard['report']['json_path'] ?? '')), 'Review dashboard JSON report is written');
assert_true(is_file(root_path() . (string)($reviewDashboard['report']['markdown_path'] ?? '')), 'Review dashboard markdown report is written');
assert_true(!str_contains(implode('|', $reviewDashboard['reports'] ?? []), 'review-dashboard/report.json'), 'Review dashboard does not consume its own generated report as source input');
$attentionRows = RecordFormBatchReviewService::filteredRows($reviewDashboard['rows'] ?? [], '', 'low_confidence');
assert_true(is_array($attentionRows), 'Review dashboard can filter low-confidence rows');

$layoutTemplate = source_draft_template('XZTC/BG-01-05');
$layoutInstanceId = qms_uuid();
try {
    Db::name('record_form_instances')->insert([
        'id' => $layoutInstanceId,
        'company_id' => (string)think\facade\Config::get('qms.company_id'),
        'template_id' => (string)$layoutTemplate->id,
        'template_name' => (string)$layoutTemplate->name,
        'template_module' => (string)$layoutTemplate->module,
        'template_version' => (string)$layoutTemplate->version,
        'template_print_template_key' => (string)$layoutTemplate->print_template_key,
        'template_field_schema' => (string)$layoutTemplate->field_schema,
        'doc_number' => (string)$layoutTemplate->doc_number,
        'record_title' => '2025运行记录-XZTC/BG-01-05-版式确认smoke',
        'field_values' => '{}',
        'status' => 'draft',
        'created' => date('Y-m-d H:i:s'),
        'modified' => date('Y-m-d H:i:s'),
    ]);
    RecordFormLayoutConfirmationService::set(2025, $layoutInstanceId, 'accepted', 'smoke确认', 'smoke');
    $confirmedDashboard = RecordFormBatchReviewService::build(2025, false);
    $confirmedRows = array_values(array_filter($confirmedDashboard['rows'], static fn (array $row): bool => ($row['id'] ?? '') === $layoutInstanceId));
    assert_true(count($confirmedRows) === 1, 'Review dashboard includes the layout confirmation smoke instance');
    assert_true(($confirmedRows[0]['manual_layout_status'] ?? '') === 'accepted', 'Review dashboard reads persisted layout confirmation status');
    assert_true(($confirmedRows[0]['manual_layout_note'] ?? '') === 'smoke确认', 'Review dashboard reads persisted layout confirmation note');
} finally {
    RecordFormLayoutConfirmationService::delete(2025, $layoutInstanceId);
    Db::name('record_form_instances')->where('id', $layoutInstanceId)->delete();
}

$reviewDashboardView = file_get_contents(dirname(__DIR__) . '/app/view/record_form_instance/review_dashboard.html') ?: '';
assert_contains('运行记录版式确认', $reviewDashboardView, 'Review dashboard view exposes the layout confirmation entry');
assert_contains('PDF巡检', $reviewDashboardView, 'Review dashboard view exposes PDF layout audit summary');
assert_contains('PDF视觉索引', $reviewDashboardView, 'Review dashboard view exposes PDF visual review index');
assert_contains('record_form_instance/updateLayoutStatus', $reviewDashboardView, 'Review dashboard renders layout confirmation POST forms');
assert_contains('需调整', $reviewDashboardView, 'Review dashboard can mark layouts needing adjustment');
assert_contains('通过', $reviewDashboardView, 'Review dashboard can accept layouts');

$pdfAudit = RecordFormPdfLayoutAuditService::audit([
    'year' => 2025,
    'limit' => 1,
    'batch_id' => 'smoke-pdf-layout-audit',
]);
assert_true(($pdfAudit['summary']['total'] ?? 0) === 1, 'PDF layout audit can scan one preview PDF');
assert_true(is_file(root_path() . (string)($pdfAudit['report']['json_path'] ?? '')), 'PDF layout audit JSON report is written');
assert_true(is_file(root_path() . (string)($pdfAudit['report']['markdown_path'] ?? '')), 'PDF layout audit markdown report is written');
$pdfAuditSmokeDir = root_path() . 'runtime' . DIRECTORY_SEPARATOR . 'record-form-batches' . DIRECTORY_SEPARATOR . '2025' . DIRECTORY_SEPARATOR . 'smoke-pdf-layout-audit';
if (is_dir($pdfAuditSmokeDir)) {
    foreach (glob($pdfAuditSmokeDir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($pdfAuditSmokeDir);
}

$visualReviewSmokeDir = root_path() . 'runtime' . DIRECTORY_SEPARATOR . 'record-form-batches' . DIRECTORY_SEPARATOR . '2025' . DIRECTORY_SEPARATOR . 'smoke-pdf-visual-review';
$visualReviewIds = [];
try {
    $visualSeed = RecordFormSourceInstanceDraftService::seed([
        'year' => 2025,
        'doc_number' => 'XZTC/BG-01-02',
        'limit' => 1,
        'apply' => true,
        'preview_pdf' => true,
        'ai' => false,
    ]);
    $visualReviewIds[] = (string)($visualSeed['rows'][0]['instance_id'] ?? '');

    $visualReview = RecordFormPdfLayoutAuditService::buildVisualReview(2025, [
        'limit' => 1,
        'batch_id' => 'smoke-pdf-visual-review',
    ]);
    assert_true(($visualReview['summary']['total'] ?? 0) === 1, 'PDF visual review can scan one preview PDF');
    assert_true(($visualReview['summary']['with_thumbnail'] ?? 0) === 1, 'PDF visual review renders a first-page thumbnail');
    assert_true(is_file(root_path() . (string)($visualReview['report']['html_path'] ?? '')), 'PDF visual review HTML index is written');
    assert_true(str_contains((string)($visualReview['report']['html_url'] ?? ''), 'record_form_instance/reviewArtifact'), 'PDF visual review HTML is exposed through review artifact route');
} finally {
    if ($visualReviewIds !== []) {
        Db::name('record_form_instances')->whereIn('id', array_filter($visualReviewIds))->delete();
        foreach ($visualReviewIds as $id) {
            $dir = root_path() . 'runtime' . DIRECTORY_SEPARATOR . 'record-form-preview-pdf' . DIRECTORY_SEPARATOR . $id;
            if (is_dir($dir)) {
                foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
                    @unlink($file);
                }
                @rmdir($dir);
            }
        }
    }
    if (is_dir($visualReviewSmokeDir)) {
        foreach (glob($visualReviewSmokeDir . DIRECTORY_SEPARATOR . 'thumbs' . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($visualReviewSmokeDir . DIRECTORY_SEPARATOR . 'thumbs');
        foreach (glob($visualReviewSmokeDir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($visualReviewSmokeDir);
    }
}

$businessDryRun = RecordFormBusinessContentService::completeBg02ToBg04([
    'apply' => false,
    'preview_pdf' => false,
    'batch_id' => 'smoke-bg02-bg04-business-content',
]);
assert_true(($businessDryRun['total'] ?? 0) >= 51, 'BG-02 to BG-04 business content service covers the expected draft records');
assert_true(($businessDryRun['errors'] ?? 0) === 0, 'BG-02 to BG-04 business content candidates validate against schemas');
$bg0201 = Db::name('record_form_instances')
    ->where('doc_number', 'XZTC/BG-02-01')
    ->whereLike('record_title', '2025运行记录-%')
    ->find();
assert_true((bool)$bg0201, 'BG-02-01 running record exists');
$bg0201Values = json_decode((string)($bg0201['field_values'] ?? '{}'), true) ?: [];
assert_true(count((array)($bg0201Values['check_items'] ?? [])) > 0, 'BG-02-01 running record has environment check rows');
$bg0403 = Db::name('record_form_instances')
    ->where('doc_number', 'XZTC/BG-04-03')
    ->whereLike('record_title', '2025运行记录-%')
    ->find();
assert_true((bool)$bg0403, 'BG-04-03 running record exists');
$bg0403Values = json_decode((string)($bg0403['field_values'] ?? '{}'), true) ?: [];
assert_true(count((array)($bg0403Values['measurement_data'] ?? [])) > 0, 'BG-04-03 running record has measurement data rows');
$businessSmokeDir = root_path() . 'runtime' . DIRECTORY_SEPARATOR . 'record-form-batches' . DIRECTORY_SEPARATOR . '2025' . DIRECTORY_SEPARATOR . 'smoke-bg02-bg04-business-content';
if (is_dir($businessSmokeDir)) {
    foreach (glob($businessSmokeDir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($businessSmokeDir);
}

$candidateTemplate = source_draft_template('XZTC/BG-01-05');
$candidateInstanceId = qms_uuid();
$candidateBatchId = 'smoke-candidate-' . substr(str_replace('-', '', qms_uuid()), 0, 8);
try {
    Db::name('record_form_instances')->insert([
        'id' => $candidateInstanceId,
        'company_id' => (string)think\facade\Config::get('qms.company_id'),
        'template_id' => (string)$candidateTemplate->id,
        'template_name' => (string)$candidateTemplate->name,
        'template_module' => (string)$candidateTemplate->module,
        'template_version' => (string)$candidateTemplate->version,
        'template_print_template_key' => (string)$candidateTemplate->print_template_key,
        'template_field_schema' => (string)$candidateTemplate->field_schema,
        'doc_number' => (string)$candidateTemplate->doc_number,
        'record_title' => '2025运行记录-XZTC/BG-01-05-候选补全smoke',
        'field_values' => '{}',
        'status' => 'draft',
        'created' => date('Y-m-d H:i:s'),
        'modified' => date('Y-m-d H:i:s'),
    ]);
    $candidateSummary = RecordFormCandidateCompletionService::complete([
        'year' => 2025,
        'apply' => false,
        'preview_pdf' => false,
        'batch_id' => $candidateBatchId,
    ]);
    $candidateRows = array_values(array_filter($candidateSummary['rows'], static fn (array $row): bool => ($row['instance_id'] ?? '') === $candidateInstanceId));
    assert_true(count($candidateRows) === 1, 'Candidate completion reports the smoke instance');
    assert_true(($candidateRows[0]['decision'] ?? '') === 'updated', 'Candidate completion can fill blank required fields without applying');
    assert_true(in_array('trainee_name', $candidateRows[0]['ai_candidate_fields'] ?? [], true), 'Candidate completion marks generated values as AI candidates');
    assert_true(($candidateRows[0]['blank_required_fields_after'] ?? []) === [], 'Candidate completion resolves required blanks in dry-run result');
} finally {
    Db::name('record_form_instances')->where('id', $candidateInstanceId)->delete();
    foreach (glob(root_path() . 'runtime' . DIRECTORY_SEPARATOR . 'record-form-batches' . DIRECTORY_SEPARATOR . '2025' . DIRECTORY_SEPARATOR . $candidateBatchId . '*') ?: [] as $dir) {
        if (is_dir($dir)) {
            foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }
    }
}

$commandSource = file_get_contents(dirname(__DIR__) . '/app/command/RecordFormSeedSourceInstances.php') ?: '';
assert_contains('record_form:seed_source_instances', $commandSource, 'CLI command is registered with the expected name');
assert_contains('preview-pdf', $commandSource, 'CLI exposes preview PDF mode');

$consoleSource = file_get_contents(dirname(__DIR__) . '/config/console.php') ?: '';
assert_contains('RecordFormSeedSourceInstances', $consoleSource, 'Console config registers source instance draft command');

$existingTemplate = source_draft_template('XZTC/BG-01-03');
$existingId = qms_uuid();
$batchId = 'smoke-2025-' . substr(str_replace('-', '', qms_uuid()), 0, 8);
$createdBatchIds = [];
$incompleteReportMarkdownPath = '';
try {
    Db::name('record_form_instances')->insert([
        'id' => $existingId,
        'company_id' => (string)think\facade\Config::get('qms.company_id'),
        'template_id' => (string)$existingTemplate->id,
        'template_name' => (string)$existingTemplate->name,
        'template_module' => (string)$existingTemplate->module,
        'template_version' => (string)$existingTemplate->version,
        'template_print_template_key' => (string)$existingTemplate->print_template_key,
        'template_field_schema' => (string)$existingTemplate->field_schema,
        'doc_number' => (string)$existingTemplate->doc_number,
        'record_title' => '2025运行记录-XZTC/BG-01-03-检测人员持证登记表-smoke-existing',
        'field_values' => '{}',
        'status' => 'draft',
        'created' => date('Y-m-d H:i:s'),
        'modified' => date('Y-m-d H:i:s'),
    ]);

    $skipSummary = RecordFormSourceInstanceDraftService::seed([
        'doc_number' => 'XZTC/BG-01-03',
        'year' => 2025,
        'apply' => true,
        'skip_existing' => true,
        'preview_pdf' => false,
        'ai' => false,
        'batch_id' => $batchId,
    ]);
    assert_true($skipSummary['created'] === 0, 'Existing 2025 instance is skipped when skip_existing is enabled');
    assert_true(($skipSummary['skipped_existing'] ?? 0) === 1, 'Skip summary counts existing 2025 instances');
    assert_true(($skipSummary['rows'][0]['decision'] ?? '') === 'skipped_existing', 'Skipped row is reported explicitly');

    $incompleteSummary = RecordFormSourceInstanceDraftService::seed([
        'doc_number' => 'XZTC/BG-01-05',
        'year' => 2025,
        'apply' => true,
        'preview_pdf' => true,
        'create_incomplete' => true,
        'ai' => false,
        'batch_id' => $batchId . '-incomplete',
    ]);
    $createdBatchIds[] = (string)($incompleteSummary['rows'][0]['instance_id'] ?? '');
    assert_true($incompleteSummary['created'] === 1, 'Incomplete records can still create draft instances when requested');
    assert_true(($incompleteSummary['rows'][0]['decision'] ?? '') === 'ready_with_gaps', 'Incomplete records are marked ready_with_gaps');
    assert_true(in_array('trainee_name', $incompleteSummary['rows'][0]['blank_required_fields'] ?? [], true), 'Missing required fields are reported');
    assert_true((string)($incompleteSummary['rows'][0]['manual_layout_status'] ?? '') === 'pending', 'Manual layout confirmation starts pending');
    assert_true((string)($incompleteSummary['rows'][0]['preview_pdf']['download_url'] ?? '') !== '', 'Incomplete draft still exposes preview PDF download URL');
    assert_true(is_file(root_path() . (string)($incompleteSummary['report']['json_path'] ?? '')), 'Batch JSON report is written');
    assert_true(is_file(root_path() . (string)($incompleteSummary['report']['markdown_path'] ?? '')), 'Batch markdown report is written');
    $incompleteReportMarkdownPath = root_path() . (string)$incompleteSummary['report']['markdown_path'];

    $report = json_decode((string)file_get_contents(root_path() . (string)$incompleteSummary['report']['json_path']), true);
    assert_true(($report['year'] ?? null) === 2025, 'Batch report records the target year');
    assert_true(($report['rows'][0]['manual_layout_status'] ?? '') === 'pending', 'Batch report includes manual layout status');
    assert_true(array_key_exists('ai_candidate_fields', $report['rows'][0]), 'Batch report includes AI candidate fields');
    assert_contains('AI候选字段', (string)file_get_contents($incompleteReportMarkdownPath), 'Batch report exposes AI candidate fields as a separate review column');
} finally {
    Db::name('record_form_instances')->where('id', $existingId)->delete();
    foreach (array_filter($createdBatchIds) as $id) {
        Db::name('record_form_instances')->where('id', $id)->delete();
        $dir = root_path() . 'runtime' . DIRECTORY_SEPARATOR . 'record-form-preview-pdf' . DIRECTORY_SEPARATOR . $id;
        if (is_dir($dir)) {
            foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }
    }
    foreach (glob(root_path() . 'runtime' . DIRECTORY_SEPARATOR . 'record-form-batches' . DIRECTORY_SEPARATOR . '2025' . DIRECTORY_SEPARATOR . $batchId . '*') ?: [] as $dir) {
        if (is_dir($dir)) {
            foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }
    }
}

assert_contains('year', $commandSource, 'CLI exposes target year option');
assert_contains('doc-prefix', $commandSource, 'CLI exposes doc-prefix option');
assert_contains('skip-existing', $commandSource, 'CLI exposes skip-existing option');
assert_contains('create-incomplete', $commandSource, 'CLI exposes incomplete draft mode');

$parseAi = new ReflectionMethod(RecordFormSourceInstanceDraftService::class, 'parseAiJsonObject');
$parsedAi = $parseAi->invoke(null, "```json\n{\"employee_name\":\"张三\",\"signed_date\":\"2025-01-02\"}\n```");
assert_true(($parsedAi['employee_name'] ?? '') === '张三', 'AI JSON parser accepts fenced JSON output');
assert_true(($parsedAi['signed_date'] ?? '') === '2025-01-02', 'AI JSON parser keeps candidate values');

echo "record_form_source_instance_draft_smoke passed\n";
