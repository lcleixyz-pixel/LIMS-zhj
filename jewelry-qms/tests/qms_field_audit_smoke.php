<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\model\Capa as CapaModel;
use app\model\Document as DocumentModel;
use app\service\FieldAuditService;
use think\facade\Config;
use think\facade\Db;
use think\facade\Session;

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

function ensure_field_change_logs_table(): void
{
    Db::execute(
        "CREATE TABLE IF NOT EXISTS `field_change_logs` (
          `id` varchar(36) NOT NULL,
          `model_name` varchar(100) NOT NULL,
          `record_id` varchar(36) NOT NULL,
          `field_name` varchar(100) NOT NULL,
          `old_value` text,
          `new_value` text,
          `changed_by` varchar(36) DEFAULT NULL,
          `changed_at` datetime NOT NULL,
          PRIMARY KEY (`id`),
          KEY `record_lookup` (`model_name`,`record_id`),
          KEY `changed_at` (`changed_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function cleanup_field_audit_smoke(string $documentId, string $capaId): void
{
    Db::name('field_change_logs')
        ->where(function ($query) use ($documentId, $capaId) {
            $query->where('record_id', $documentId)->whereOr('record_id', $capaId);
        })
        ->delete();
    Db::name('documents')->where('id', $documentId)->delete();
    Db::name('capas')->where('id', $capaId)->delete();
}

$root = dirname(__DIR__);
$schema = (string)file_get_contents($root . '/database/jewelry_qms.sql');
$migration = (string)file_get_contents($root . '/database/migrations/20260529_field_change_logs.sql');
$baseModelSource = (string)file_get_contents($root . '/app/Model/BaseModel.php');
$serviceSource = (string)file_get_contents($root . '/app/service/FieldAuditService.php');

foreach ([$schema, $migration] as $source) {
    assert_contains('CREATE TABLE', $source, 'Field audit schema declares table');
    assert_contains('field_change_logs', $source, 'Field audit schema includes field_change_logs');
    assert_contains('KEY `record_lookup` (`model_name`,`record_id`)', $source, 'Field audit schema indexes record lookup');
}
assert_contains('FieldAuditService::capture', $baseModelSource, 'BaseModel captures field changes on update');
assert_contains("'Document' => ['status', 'version', 'revision', 'effective_date', 'approved_by']", $serviceSource, 'Document audit whitelist is explicit');
assert_contains("'Equipment' => ['status', 'next_calibration_date', 'last_calibration_date', 'site_id']", $serviceSource, 'Equipment audit whitelist includes future site_id');
assert_contains('Log::error', $serviceSource, 'Field audit service degrades to error logs on failure');

ensure_field_change_logs_table();

$documentId = 'field-audit-doc-001';
$capaId = 'field-audit-capa-001';
$now = date('Y-m-d H:i:s');

try {
    cleanup_field_audit_smoke($documentId, $capaId);
    Session::set('user.id', 'field-audit-user');

    Db::name('documents')->insert([
        'id' => $documentId,
        'company_id' => (string)Config::get('qms.company_id'),
        'category_id' => null,
        'template_id' => null,
        'level' => 2,
        'doc_number' => 'QA-FIELD-AUDIT-SMOKE',
        'title' => '字段审计冒烟文件',
        'version' => 'A/0',
        'revision' => 0,
        'department_id' => null,
        'effective_date' => null,
        'review_date' => null,
        'status' => 'draft',
        'publish' => 1,
        'soft_delete' => 0,
        'record_status' => 0,
        'created' => $now,
        'modified' => $now,
    ]);

    $document = DocumentModel::find($documentId);
    $document->save([
        'status' => 'reviewing',
        'version' => 'A/1',
        'title' => '字段审计冒烟文件-标题改动不审计',
    ]);

    $documentLogs = Db::name('field_change_logs')
        ->where('model_name', 'Document')
        ->where('record_id', $documentId)
        ->order('field_name', 'asc')
        ->select()
        ->toArray();
    $documentFields = array_column($documentLogs, 'field_name');
    assert_true(in_array('status', $documentFields, true), 'Document status change is audited');
    assert_true(in_array('version', $documentFields, true), 'Document version change is audited');
    assert_true(!in_array('title', $documentFields, true), 'Document non-whitelist field is not audited');
    assert_true((string)$documentLogs[0]['changed_by'] === 'field-audit-user', 'Audit log stores changed_by');

    $countBeforeNoop = Db::name('field_change_logs')->where('record_id', $documentId)->count();
    $document = DocumentModel::find($documentId);
    $document->save(['status' => 'reviewing']);
    $countAfterNoop = Db::name('field_change_logs')->where('record_id', $documentId)->count();
    assert_true((int)$countBeforeNoop === (int)$countAfterNoop, 'No-op save does not create audit rows');

    Db::name('capas')->insert([
        'id' => $capaId,
        'company_id' => (string)Config::get('qms.company_id'),
        'capa_number' => 'CAPA-FIELD-AUDIT-SMOKE',
        'description' => '字段审计冒烟 CAPA',
        'root_cause' => '初始原因',
        'status' => 'open',
        'publish' => 1,
        'soft_delete' => 0,
        'record_status' => 0,
        'created' => $now,
        'modified' => $now,
    ]);

    $longCause = str_repeat('原因', 280);
    $capa = CapaModel::find($capaId);
    $capa->save([
        'root_cause' => $longCause,
        'status' => 'analyzing',
    ]);
    $rootCauseLog = Db::name('field_change_logs')
        ->where('model_name', 'Capa')
        ->where('record_id', $capaId)
        ->where('field_name', 'root_cause')
        ->find();
    assert_true(is_array($rootCauseLog), 'CAPA root cause change is audited');
    assert_true(str_contains((string)$rootCauseLog['new_value'], '[...截断]'), 'Large text values are truncated');

    assert_true(FieldAuditService::formatAuditValue('participants', ['a' => 1]) === '[已变更]', 'JSON values are summarized');
    assert_true(FieldAuditService::auditFieldsFor(\app\model\User::class) === [], 'Non-whitelist models are ignored');

    foreach ([
        '/app/controller/Document.php',
        '/app/controller/Capa.php',
        '/app/controller/Equipment.php',
        '/app/controller/AuditFinding.php',
        '/app/view/document/view.html',
        '/app/view/capa/view.html',
        '/app/view/equipment/view.html',
        '/app/view/audit_finding/view.html',
    ] as $relativePath) {
        assert_contains('fieldChangeLogs', (string)file_get_contents($root . $relativePath), $relativePath . ' exposes audit logs');
    }
} finally {
    cleanup_field_audit_smoke($documentId, $capaId);
    Session::delete('user.id');
}

echo "qms_field_audit_smoke passed\n";
