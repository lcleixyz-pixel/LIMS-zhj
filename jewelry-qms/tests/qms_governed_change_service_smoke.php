<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\model\Training;
use app\service\GovernedChangeService;

(new think\App())->initialize();

function governed_change_assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

$record = new Training([
    'id' => 'training-test-1',
    'title' => '显微镜规范操作',
    'status' => 'completed',
    'duration_hours' => '2.0',
]);

$prepared = GovernedChangeService::prepareRequest('training', $record, [
    'field_name' => 'duration_hours',
    'field_label' => '培训学时',
    'proposed_value' => '2.5',
    'reason' => '复核签到与授课记录后发现录入错误',
]);
governed_change_assert_same('duration_hours', $prepared['field_name'] ?? null, 'Prepared request must keep field name');
governed_change_assert_same('2.0', $prepared['original_value'] ?? null, 'Prepared request must snapshot original value');
governed_change_assert_same('2.5', $prepared['proposed_value'] ?? null, 'Prepared request must snapshot proposed value');

$projected = GovernedChangeService::projectValues(
    ['duration_hours' => '2.0', 'status' => 'completed'],
    [
        ['field_name' => 'duration_hours', 'new_value' => '2.5', 'registered_at' => '2026-07-26 10:00:00'],
        ['field_name' => 'duration_hours', 'new_value' => '3.0', 'registered_at' => '2026-07-26 11:00:00'],
    ]
);
governed_change_assert_same('3.0', $projected['duration_hours'] ?? null, 'Latest approved correction must become current display value');
governed_change_assert_same('completed', $projected['status'] ?? null, 'Unchanged fields must be preserved');

$fields = GovernedChangeService::correctableFields('training', $record);
$fieldNames = array_column($fields, 'name');
governed_change_assert_same(true, in_array('duration_hours', $fieldNames, true), 'Business field must be correctable');
governed_change_assert_same(false, in_array('id', $fieldNames, true), 'Primary key must never be correctable');
governed_change_assert_same(false, in_array('soft_delete', $fieldNames, true), 'Deletion marker must never be correctable');
$fieldsByName = array_column($fields, null, 'name');
governed_change_assert_same(
    '所属培训计划',
    $fieldsByName['training_plan_id']['label'] ?? null,
    'Technical relation fields must use a clear business label'
);

fwrite(STDOUT, "qms_governed_change_service_smoke passed\n");
