<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\service\GovernedChangeService;
use think\facade\Db;

(new think\App())->initialize();

function governed_runtime_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

governed_runtime_assert(GovernedChangeService::tablesReady(), 'Governed change tables are not initialized');

$trainingId = '8c7d2600-0000-4000-8000-000000000001';
$training = Db::name('trainings')->where('id', $trainingId)->find();
governed_runtime_assert(is_array($training), 'Governed correction acceptance training is missing');

$changes = GovernedChangeService::approvedChanges('training', $trainingId);
governed_runtime_assert($changes !== [], 'Approved append-only correction is missing');
$projected = GovernedChangeService::projectValues($training, $changes);
governed_runtime_assert(
    (string)($training['duration_hours'] ?? '') === '2.0',
    'Original business row was overwritten instead of retained'
);
governed_runtime_assert(
    (string)($projected['duration_hours'] ?? '') !== (string)($training['duration_hours'] ?? ''),
    'Approved correction was not projected as the current effective value'
);
governed_runtime_assert(
    Db::name('qms_governed_change_requests')
        ->where('subject_id', $trainingId)
        ->where('status', 'approved')
        ->count() >= 1,
    'Approved request decision is missing'
);

$referenceMaterialId = '8c7d2600-0000-4000-8000-000000000002';
$reference = Db::name('reference_materials')->where('id', $referenceMaterialId)->find();
governed_runtime_assert(is_array($reference), 'Governed event acceptance reference material is missing');
$events = array_values(array_filter(
    GovernedChangeService::approvedChanges('reference_material', $referenceMaterialId),
    static fn (array $row): bool => (string)($row['change_kind'] ?? '') === 'event'
));
governed_runtime_assert($events !== [], 'Master-data lifecycle event is missing');
$latestEvent = $events[array_key_last($events)];
governed_runtime_assert(
    (string)($reference['status'] ?? '') === (string)($latestEvent['new_value'] ?? ''),
    'Master-data current status does not match the latest lifecycle event'
);

fwrite(STDOUT, "qms_governed_change_runtime_smoke passed\n");
