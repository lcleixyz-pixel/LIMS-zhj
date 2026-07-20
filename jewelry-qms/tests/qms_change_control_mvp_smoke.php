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

$structured = Db::name('qms_structured_documents')
    ->where('doc_number', 'XZTC/CX-26-2022')
    ->where('soft_delete', 0)
    ->field('id,doc_number,title')
    ->find();

assert_true((bool)$structured, 'Change-control MVP has a structured procedure anchor');

$preview = QmsDocumentStructureService::changeControlImpactPreview(
    (string)$structured['id'],
    '变更单 MVP smoke：修改程序前先预检记录表格影响'
);

assert_true((string)($preview['selected_document']['doc_number'] ?? '') === 'XZTC/CX-26-2022', 'Impact preview selects the requested procedure');
assert_true(count($preview['impact_rows'] ?? []) > 0, 'Impact preview lists affected content blocks');
assert_true(count($preview['summary']['record_forms'] ?? []) > 0, 'Impact preview lists affected record forms');
assert_true(count($preview['summary']['elements'] ?? []) > 0, 'Impact preview lists affected elements');

$previewText = json_encode($preview, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
assert_contains('XZTC/BG-', $previewText, 'Impact preview names affected BG record forms');
assert_contains('/planning/structures/blocks/edit', $previewText, 'Impact preview links to structured draft editing');
assert_contains('/document/revise', $previewText, 'Impact preview links to the controlled document revision flow when available');

$changeNote = '变更单 MVP smoke：保存影响分析 ' . date('YmdHis');
$beforeCount = Db::name('qms_document_change_logs')
    ->where('structured_document_id', (string)$structured['id'])
    ->where('revision_note', $changeNote)
    ->where('soft_delete', 0)
    ->count();
$saved = QmsDocumentStructureService::saveChangeRequest(
    (string)$structured['id'],
    $changeNote,
    [
        'record_template_review' => '1',
        'training_required' => '1',
        'publish_review' => '0',
    ]
);
$afterCount = Db::name('qms_document_change_logs')
    ->where('structured_document_id', (string)$structured['id'])
    ->where('revision_note', $changeNote)
    ->where('soft_delete', 0)
    ->count();
assert_true($afterCount === $beforeCount + 1, 'Saving a change request writes one change log row');
assert_true((string)($saved['change_request']['status_to'] ?? '') === 'change_requested', 'Saved change request is marked as requested');

$requestLog = Db::name('qms_document_change_logs')
    ->where('id', (string)($saved['change_request']['id'] ?? ''))
    ->find();
assert_true((bool)$requestLog, 'Saved change request log can be queried');
assert_true((string)$requestLog['change_type'] === 'version_update', 'Saved change request reuses version_update change log type');
$snapshot = json_decode((string)($requestLog['trace_snapshot_json'] ?? ''), true);
assert_true(is_array($snapshot), 'Saved change request stores a JSON impact snapshot');
assert_true((string)($snapshot['type'] ?? '') === 'change_request', 'Impact snapshot is typed as a change request');
assert_contains('XZTC/BG-', json_encode($snapshot['impact_summary']['record_forms'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '', 'Saved impact snapshot keeps affected BG record forms');
assert_true(($snapshot['manual_tasks']['record_template_review']['checked'] ?? null) === true, 'Saved impact snapshot keeps record-template task state');
assert_true(($snapshot['manual_tasks']['training_required']['checked'] ?? null) === true, 'Saved impact snapshot keeps training task state');

$updated = QmsDocumentStructureService::updateChangeRequestTasks(
    (string)($saved['change_request']['id'] ?? ''),
    [
        'record_template_review' => '1',
        'training_required' => '0',
        'publish_review' => '1',
    ],
    false
);
assert_true((string)($updated['change_request']['status_to'] ?? '') === 'change_requested', 'Updating change request keeps it open');
assert_true(($updated['trace_snapshot']['manual_tasks']['training_required']['checked'] ?? null) === false, 'Updating change request stores unchecked training task');
assert_true(($updated['trace_snapshot']['manual_tasks']['publish_review']['checked'] ?? null) === true, 'Updating change request stores publish-review task');

$closed = QmsDocumentStructureService::updateChangeRequestTasks(
    (string)($saved['change_request']['id'] ?? ''),
    [
        'record_template_review' => '1',
        'training_required' => '1',
        'publish_review' => '1',
    ],
    true
);
assert_true((string)($closed['change_request']['status_to'] ?? '') === 'closed', 'Closing change request marks it closed');
assert_true(($closed['trace_snapshot']['manual_tasks']['training_required']['checked'] ?? null) === true, 'Closed change request keeps final task state');

$previewAfterSave = QmsDocumentStructureService::changeControlImpactPreview((string)$structured['id'], '');
assert_contains($changeNote, json_encode($previewAfterSave['change_requests'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '', 'Impact preview shows saved change requests');
assert_contains('closed', json_encode($previewAfterSave['change_requests'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '', 'Impact preview keeps closed change requests visible');

$routeSource = file_get_contents(dirname(__DIR__) . '/route/app.php') ?: '';
assert_contains('planning/structures/change-impact', $routeSource, 'Routes expose change impact precheck');
assert_contains('planning/structures/change-request/save', $routeSource, 'Routes expose change request save');

$controllerSource = file_get_contents(dirname(__DIR__) . '/app/controller/PlanningStructure.php') ?: '';
assert_contains('public function changeImpact', $controllerSource, 'PlanningStructure controller exposes change impact precheck');
assert_contains('public function saveChangeRequest', $controllerSource, 'PlanningStructure controller saves change requests');
assert_contains('changeControlImpactPreview', $controllerSource, 'Change impact page uses the structure service preview');

$indexView = file_get_contents(dirname(__DIR__) . '/app/view/planning_structure/index.html') ?: '';
assert_contains('变更影响预检', $indexView, 'Structure list links to change impact precheck');

$changeImpactView = file_get_contents(dirname(__DIR__) . '/app/view/planning_structure/change_impact.html') ?: '';
assert_contains('变更申请', $changeImpactView, 'Change impact page starts with a change request form');
assert_contains('影响分析', $changeImpactView, 'Change impact page shows impact analysis');
assert_contains('记录模板/培训待办', $changeImpactView, 'Change impact page includes manual downstream task checklist');
assert_contains('编辑内容块', $changeImpactView, 'Change impact page links to structured draft editing');
assert_contains('保存变更申请', $changeImpactView, 'Change impact page can persist the change request');
assert_contains('已保存变更申请', $changeImpactView, 'Change impact page lists saved change requests');
assert_contains('保存待办', $changeImpactView, 'Change impact page can update saved request tasks');
assert_contains('关闭申请', $changeImpactView, 'Change impact page can close saved change requests');
assert_contains('发布结构化文件', $changeImpactView, 'Change impact page links approval publish action');
assert_contains('publish_structure_from_change_impact', $changeImpactView, 'Change impact page keeps publish POST in a separate form');
assert_true(
    !str_contains($changeImpactView, '<form method="post" action="/planning/structures/publish" class="d-inline">'),
    'Change impact page must not nest the publish form inside the change-request form'
);

require_once dirname(__DIR__) . '/app/middleware/Rbac.php';
assert_true(\app\middleware\Rbac::requiresWritePermission('POST', 'savechangerequest'), 'RBAC treats change request save as a write action');
assert_true(\app\middleware\Rbac::requiresWritePermission('POST', 'updatechangerequest'), 'RBAC treats change request update as a write action');
$auditSource = file_get_contents(dirname(__DIR__) . '/app/middleware/AuditLog.php') ?: '';
assert_contains('savechangerequest', $auditSource, 'Audit log tracks change request saves');
assert_contains('updatechangerequest', $auditSource, 'Audit log tracks change request updates');

Db::name('qms_document_change_logs')
    ->where('revision_note', $changeNote)
    ->update(['soft_delete' => 1]);

echo "qms_change_control_mvp_smoke passed\n";
