<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\middleware\Rbac;
use app\model\QmsExternalChangeEvent;
use app\service\ExternalChangeEventService;
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

function ensure_external_change_event_tables(): void
{
    Db::execute(
        "CREATE TABLE IF NOT EXISTS `qms_external_change_events` (
          `id` varchar(36) NOT NULL,
          `company_id` varchar(36) NOT NULL,
          `event_code` varchar(80) NOT NULL,
          `source_kind` enum('cnas','samr','standard_platform','gb','internal','other') NOT NULL DEFAULT 'cnas',
          `source_name` varchar(300) NOT NULL,
          `source_url` varchar(500) DEFAULT NULL,
          `announcement_number` varchar(120) DEFAULT NULL,
          `old_source_id` varchar(36) DEFAULT NULL,
          `new_source_id` varchar(36) DEFAULT NULL,
          `old_version` varchar(80) DEFAULT NULL,
          `new_version` varchar(80) DEFAULT NULL,
          `published_date` date DEFAULT NULL,
          `effective_date` date DEFAULT NULL,
          `event_summary` text NOT NULL,
          `graph_snapshot_hash` char(64) DEFAULT NULL,
          `status` enum('registered','assessing','revising','closed','exempted') DEFAULT 'registered',
          `close_reason` text,
          `publish` tinyint(1) DEFAULT 1,
          `soft_delete` tinyint(1) DEFAULT 0,
          `created` datetime DEFAULT NULL,
          `modified` datetime DEFAULT NULL,
          `created_by` varchar(36) DEFAULT NULL,
          `modified_by` varchar(36) DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `event_code` (`event_code`),
          KEY `company_status` (`company_id`,`status`),
          KEY `source_kind` (`source_kind`),
          KEY `effective_date` (`effective_date`),
          KEY `old_source_id` (`old_source_id`),
          KEY `new_source_id` (`new_source_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
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

function cleanup_external_change_event_smoke(string $eventCode): void
{
    $ids = Db::name('qms_external_change_events')->where('event_code', $eventCode)->column('id');
    if ($ids !== []) {
        Db::name('field_change_logs')
            ->where('model_name', 'QmsExternalChangeEvent')
            ->whereIn('record_id', $ids)
            ->delete();
        try {
            Db::name('file_uploads')
                ->where('model_name', 'QmsExternalChangeEvent')
                ->whereIn('record', $ids)
                ->delete();
        } catch (\Throwable $exception) {
        }
        Db::name('qms_external_change_events')->whereIn('id', $ids)->delete();
    }
}

$root = dirname(__DIR__);
$schema = (string)file_get_contents($root . '/database/jewelry_qms.sql');
$migration = (string)file_get_contents($root . '/database/migrations/20260704_external_change_events.sql');
$candidateMigration = (string)file_get_contents($root . '/database/migrations/20260715_regulatory_candidates.sql');
$route = (string)file_get_contents($root . '/route/app.php');
$config = (string)file_get_contents($root . '/config/qms.php');
$layout = (string)file_get_contents($root . '/app/view/layout/main.html');
$controller = (string)file_get_contents($root . '/app/controller/PlanningChangeEvent.php');
$service = (string)file_get_contents($root . '/app/service/ExternalChangeEventService.php');
$fieldAudit = (string)file_get_contents($root . '/app/service/FieldAuditService.php');
$rbac = (string)file_get_contents($root . '/app/middleware/Rbac.php');
$auditLog = (string)file_get_contents($root . '/app/middleware/AuditLog.php');
$detailView = (string)file_get_contents($root . '/app/view/planning_change_event/view.html');

foreach ([$schema, $migration] as $source) {
    assert_contains('qms_external_change_events', $source, 'External change event schema declares table');
    assert_contains('graph_snapshot_hash', $source, 'External change event schema stores graph snapshot hash');
    assert_contains("enum('registered','assessing','revising','closed','exempted')", $source, 'External change event schema stores calibrated statuses');
    assert_contains('old_source_id', $source, 'External change event schema links old source');
    assert_contains('new_source_id', $source, 'External change event schema links new source');
}

foreach ([$schema, $candidateMigration] as $source) {
    assert_contains('qms_regulatory_monitor_runs', $source, 'Regulatory monitor schema declares run table');
    assert_contains('qms_external_change_candidates', $source, 'Regulatory monitor schema declares candidate table');
    assert_contains('impact_analysis', $source, 'Regulatory candidate schema stores six-category impact analysis');
    assert_contains('review_status', $source, 'Regulatory candidate schema stores manual review status');
    assert_contains("enum('pending','confirmed_applicable','confirmed_not_applicable','deferred','promoted')", $source, 'Regulatory candidate schema stores calibrated review statuses');
}

foreach ([
    'planning/change-events',
    'planning/change-events/transition',
    'planning/change-events/upload-attachment',
] as $path) {
    assert_contains($path, $route, 'Route exposes ' . $path);
}

assert_contains('/planning/change-events', $layout, 'Navigation exposes change events');
assert_contains('/planning/regulatory-candidates', $layout, 'Navigation exposes regulatory candidates');
assert_contains('planning/regulatory-candidates', $route, 'Route exposes regulatory candidate pool');
assert_contains('planningregulatorycandidate', $config, 'Quality manager permissions include PlanningRegulatoryCandidate');
assert_contains('planningchangeevent', $config, 'Quality manager permissions include PlanningChangeEvent');
assert_contains('planning_change_event', $config, 'Status labels include planning change events');
assert_contains('View::assign(\'record\', $this->emptyRecord())', $controller, 'Add view receives array data compatible with template dot access');
assert_contains('View::assign(\'record\', array_merge($this->emptyRecord(), $data))', $controller, 'Validation redisplay receives array data compatible with template dot access');
assert_contains('CREATE TABLE `system_settings`', $schema, 'Baseline schema includes system_settings required by page context middleware');
assert_contains('QmsExternalChangeEvent', $fieldAudit, 'Field audit whitelists external change event');
assert_true(Rbac::requiresWritePermission('POST', 'transition'), 'RBAC covers external change transitions');
assert_contains('transition', $auditLog, 'Audit log covers external change transitions');
assert_contains('FileAttachmentService::upload', $controller, 'Controller stores attachments through shared attachment service');
assert_contains('currentGraphSnapshotHash', $service, 'Service can stamp graph snapshot hash');
assert_contains('字段变更记录', $detailView, 'Detail view exposes field change logs');
assert_contains('公告与查新附件', $detailView, 'Detail view exposes announcement attachments');

ensure_external_change_event_tables();

$eventCode = 'QMS-CHG-SMOKE-' . date('YmdHis');
cleanup_external_change_event_smoke($eventCode);
Session::set('user.id', 'external-change-smoke-user');

try {
    $data = ExternalChangeEventService::normalizeInput([
        'event_code' => $eventCode,
        'source_kind' => 'cnas',
        'source_name' => 'CNAS 外部变更事件冒烟公告',
        'announcement_number' => 'SMOKE-2026-01',
        'published_date' => date('Y-m-d'),
        'effective_date' => date('Y-m-d'),
        'event_summary' => '用于验证外部变更事件登记、快照哈希和状态审计。',
    ], true);

    $errors = ExternalChangeEventService::validateData($data, true);
    assert_true($errors === [], 'Valid external change event input passes validation');
    assert_true(strlen((string)$data['graph_snapshot_hash']) === 64, 'Graph snapshot hash is stamped on create');

    $event = new QmsExternalChangeEvent();
    $event->save($data);
    $eventId = (string)$event->id;
    assert_true($eventId !== '', 'External change event persists with id');

    ExternalChangeEventService::transition($event, 'assess');
    $status = (string)Db::name('qms_external_change_events')->where('id', $eventId)->value('status');
    assert_true($status === 'assessing', 'External change event transitions to assessing');

    $statusLog = Db::name('field_change_logs')
        ->where('model_name', 'QmsExternalChangeEvent')
        ->where('record_id', $eventId)
        ->where('field_name', 'status')
        ->find();
    assert_true(is_array($statusLog), 'External change event status transition is field-audited');
    assert_true((string)$statusLog['changed_by'] === 'external-change-smoke-user', 'Field audit records operator');

    ExternalChangeEventService::transition($event, 'close', 'smoke impact loop closed');
    $closed = Db::name('qms_external_change_events')->where('id', $eventId)->find();
    assert_true((string)$closed['status'] === 'closed', 'External change event can be closed');
    assert_true((string)$closed['close_reason'] === 'smoke impact loop closed', 'Close reason is persisted');
} finally {
    cleanup_external_change_event_smoke($eventCode);
    Session::delete('user.id');
}

echo "qms_external_change_event_smoke passed\n";
