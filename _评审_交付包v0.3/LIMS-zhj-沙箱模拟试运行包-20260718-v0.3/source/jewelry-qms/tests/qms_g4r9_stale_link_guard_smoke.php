<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
require $root . '/app/common.php';

$app = new think\App();
$app->initialize();

use app\service\QmsDocumentStructureService;

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

$normalize = new ReflectionMethod(QmsDocumentStructureService::class, 'normalizePackageBlockTraceRows');
$normalize->setAccessible(true);
$staleRows = $normalize->invoke(null, [[
    'structured_document_id' => '',
    'document_role' => 'procedure',
    'doc_number' => 'G4R9-SMOKE',
    'document_title' => '历史组合包测试文件',
    'document_version' => 'v0.1',
    'block_id' => 'G4R9_STALE_BLOCK_DOES_NOT_EXIST',
    'block_stable_key' => 'g4r9-stale-block',
    'block_title' => '历史组合包测试内容块',
    'trace_summary' => '人员、设备、记录表格',
    'document_url' => '/planning/structures/view?id=G4R9_STALE_DOC_DOES_NOT_EXIST',
    'block_edit_url' => '/planning/structures/blocks/edit?id=G4R9_STALE_BLOCK_DOES_NOT_EXIST',
    'trace_review_url' => '/planning/structures/links/review?block_id=G4R9_STALE_BLOCK_DOES_NOT_EXIST',
]]);

foreach ($staleRows as $row) {
    assert_true(($row['block_exists'] ?? null) === false, 'Stale package block row should be marked block_exists=false');
    assert_true(($row['is_historical_snapshot'] ?? null) === true, 'Stale package block row should be marked as historical snapshot');
    assert_true((string)($row['block_edit_url'] ?? '') === '', 'Stale package block row must not expose edit URL');
    assert_true((string)($row['trace_review_url'] ?? '') === '', 'Stale package block row must not expose trace review URL');
    assert_true((string)($row['trace_summary'] ?? '') !== '', 'Stale package block row should keep trace summary evidence');
    assert_contains('仅供追溯', (string)($row['stale_reason'] ?? ''), 'Stale package block row should explain why action links are disabled in business language');
    assert_contains('不用处理', (string)($row['stale_reason'] ?? ''), 'Stale package block row should tell business users that historical rows usually do not require action');
}

$packageView = (string)file_get_contents($root . '/app/view/planning_structure/package.html');
assert_contains('is_historical_snapshot', $packageView, 'System package page should render historical snapshot state');
assert_contains('历史快照', $packageView, 'System package page should explain stale archived rows to users');

$planningController = (string)file_get_contents($root . '/app/controller/PlanningStructure.php');
assert_contains('历史组合包快照', $planningController, 'PlanningStructure controller should friendly-redirect stale block edit/review URLs');

$calendarController = (string)file_get_contents($root . '/app/controller/Calendar.php');
$calendarView = (string)file_get_contents($root . '/app/view/calendar/index.html');
assert_contains('resolveNotificationTarget', $calendarController, 'Calendar should check notification target existence before exposing action URL');
assert_contains('link_status_text', $calendarView, 'Calendar view should explain missing notification targets');

$equipmentMaintenanceController = (string)file_get_contents($root . '/app/controller/EquipmentMaintenance.php');
assert_contains('关联维护保养记录不存在', $equipmentMaintenanceController, 'Equipment maintenance stale direct detail URL should show a friendly message');

echo "qms_g4r9_stale_link_guard_smoke passed\n";
