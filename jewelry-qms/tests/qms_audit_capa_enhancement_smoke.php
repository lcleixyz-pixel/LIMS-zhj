<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\service\FileAttachmentService;
use app\service\WorkflowService;
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

function ensure_audit_capa_enhancement_schema(): void
{
    $migration = dirname(__DIR__) . '/database/migrations/20260529_audit_capa_enhancement.sql';
    assert_true(is_file($migration), 'Phase 2.2 migration exists');
    $sql = (string)file_get_contents($migration);
    assert_contains('effectiveness_review_date', $sql, 'Migration adds CAPA effectiveness review date');
    assert_contains('effectiveness_result', $sql, 'Migration adds CAPA effectiveness result');

    foreach (['effectiveness_review_date' => 'date DEFAULT NULL', 'effectiveness_result' => 'text'] as $column => $definition) {
        $exists = (int)Db::query(
            "SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'capas' AND COLUMN_NAME = ?",
            [$column]
        )[0]['c'];
        if ($exists === 0) {
            Db::execute("ALTER TABLE `capas` ADD COLUMN `{$column}` {$definition}");
        }
    }
}

function cleanup_audit_capa_smoke(string $findingId, string $capaId, string $fileId): void
{
    Db::name('file_uploads')->where('id', $fileId)->delete();
    Db::name('audit_findings')->where('id', $findingId)->delete();
    Db::name('capas')->where('id', $capaId)->delete();
}

$root = dirname(__DIR__);
$schema = (string)file_get_contents($root . '/database/jewelry_qms.sql');
$route = (string)file_get_contents($root . '/route/app.php');
$rbac = (string)file_get_contents($root . '/app/middleware/Rbac.php');
$auditFindingController = (string)file_get_contents($root . '/app/controller/AuditFinding.php');
$auditFindingView = (string)file_get_contents($root . '/app/view/audit_finding/view.html');
$capaController = (string)file_get_contents($root . '/app/controller/Capa.php');
$capaAdvance = (string)file_get_contents($root . '/app/view/capa/advance.html');
$capaView = (string)file_get_contents($root . '/app/view/capa/view.html');

assert_contains('effectiveness_review_date', $schema, 'Schema documents CAPA effectiveness review date');
assert_contains('effectiveness_result', $schema, 'Schema documents CAPA effectiveness result');
assert_contains('audit_finding/uploadEvidence', $route, 'Route exposes audit finding evidence upload');
assert_contains('audit_finding/downloadEvidence', $route, 'Route exposes audit finding evidence download');
assert_contains('capa/reviewEffectiveness', $route, 'Route exposes CAPA effectiveness review');
assert_contains('uploadevidence', $rbac, 'RBAC protects evidence upload as write action');
assert_contains('revieweffectiveness', $rbac, 'RBAC protects CAPA effectiveness review as write action');
assert_contains('FileAttachmentService', $auditFindingController, 'Audit finding controller delegates file attachment handling');
assert_contains('整改证据附件', $auditFindingView, 'Audit finding detail shows evidence attachment panel');
assert_contains('effectiveness_review_date', $capaController, 'CAPA controller handles effectiveness review date');
assert_contains('有效性复查日期', $capaAdvance, 'CAPA close form captures effectiveness review date');
assert_contains('有效性复查结果', $capaView, 'CAPA detail shows effectiveness result');

ensure_audit_capa_enhancement_schema();

assert_true(class_exists(FileAttachmentService::class), 'FileAttachmentService exists');
foreach (['upload', 'registerExistingFile', 'attachmentsFor'] as $method) {
    assert_true(method_exists(FileAttachmentService::class, $method), 'FileAttachmentService supports ' . $method);
}
assert_true(method_exists(WorkflowService::class, 'formatManagementReviewInputs'), 'Management review input formatter exists');
assert_true(method_exists(WorkflowService::class, 'recordCapaEffectiveness'), 'CAPA effectiveness recorder exists');

$summary = WorkflowService::formatManagementReviewInputs([
    'capa_total' => 4,
    'capa_open' => 1,
    'capa_analyzing' => 1,
    'capa_implementing' => 1,
    'capa_verifying' => 0,
    'capa_closed' => 1,
    'capa_effectiveness_due' => 1,
    'complaints_total' => 2,
    'complaints_open' => 1,
    'complaints_closed' => 1,
    'nonconformities_open' => 1,
    'calibrations_total' => 5,
    'calibrations_pass' => 4,
    'calibration_pass_rate' => 80.0,
    'trainings_total' => 3,
    'trainings_completed' => 2,
    'training_completion_rate' => 66.7,
    'audit_findings_total' => 3,
    'audit_findings_open' => 1,
    'audit_findings_correcting' => 1,
    'audit_findings_verified' => 0,
    'audit_findings_closed' => 1,
    'overdue_actions' => 2,
    'planning_elements_total' => 10,
    'planning_elements_complete' => 8,
    'planning_traceability_gaps' => 3,
    'planning_sources_due' => 1,
]);
assert_contains('CAPA状态分布', $summary, 'Management review summary includes CAPA status distribution');
assert_contains('校准合格率：80%', $summary, 'Management review summary includes calibration pass rate');
assert_contains('培训完成率：66.7%', $summary, 'Management review summary includes training completion rate');
assert_contains('内审发现统计', $summary, 'Management review summary includes audit finding stats');

$companyId = (string)Config::get('qms.company_id');
$findingId = 'audit-capa-smoke-finding';
$capaId = 'audit-capa-smoke-capa';
$fileId = 'audit-capa-smoke-file';
$now = date('Y-m-d H:i:s');

try {
    cleanup_audit_capa_smoke($findingId, $capaId, $fileId);
    Db::name('audit_findings')->insert([
        'id' => $findingId,
        'audit_schedule_id' => 'audit-capa-smoke-schedule',
        'finding_number' => 'AF-SMOKE-001',
        'finding_type' => 'minor',
        'description' => '整改证据附件冒烟',
        'due_date' => date('Y-m-d', strtotime('+7 days')),
        'status' => 'open',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => $now,
        'modified' => $now,
    ]);
    Db::name('capas')->insert([
        'id' => $capaId,
        'company_id' => $companyId,
        'capa_number' => 'CAPA-SMOKE-001',
        'source_type' => 'audit',
        'source_record_id' => $findingId,
        'description' => 'CAPA 有效性追踪冒烟',
        'status' => 'closed',
        'effectiveness_review_date' => date('Y-m-d'),
        'publish' => 1,
        'soft_delete' => 0,
        'record_status' => 0,
        'created' => $now,
        'modified' => $now,
    ]);

    $registered = FileAttachmentService::registerExistingFile(
        'AuditFinding',
        $findingId,
        'uploads/audit-findings/' . $findingId . '/evidence.pdf',
        '整改证据.pdf',
        '整改完成照片和记录'
    );
    assert_true($registered->record === $findingId, 'Attachment links to audit finding record');
    assert_true($registered->model_name === 'AuditFinding', 'Attachment uses AuditFinding model name');
    $fileId = (string)$registered->id;
    $attachments = FileAttachmentService::attachmentsFor('AuditFinding', $findingId);
    assert_true(count($attachments) === 1, 'Attachment listing returns registered evidence file');

    WorkflowService::recordCapaEffectiveness($capaId, '复查有效，未再发生同类问题', date('Y-m-d'));
    $result = Db::name('capas')->where('id', $capaId)->value('effectiveness_result');
    assert_true((string)$result === '复查有效，未再发生同类问题', 'CAPA effectiveness result is stored');
} finally {
    cleanup_audit_capa_smoke($findingId, $capaId, $fileId);
}

echo "qms_audit_capa_enhancement_smoke passed\n";
