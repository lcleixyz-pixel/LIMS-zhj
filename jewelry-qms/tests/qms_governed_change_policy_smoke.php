<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\model\Training;
use app\service\GovernedChangePolicyService;

(new think\App())->initialize();

function governed_policy_assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function governed_policy_assert_contains(string $needle, array $haystack, string $message): void
{
    if (!in_array($needle, $haystack, true)) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Missing: ' . $needle . PHP_EOL);
        exit(1);
    }
}

governed_policy_assert_same(
    'revision',
    GovernedChangePolicyService::policy('document')['strategy'] ?? null,
    'Controlled documents must stay on the revision workflow'
);
governed_policy_assert_same(
    'specialized',
    GovernedChangePolicyService::policy('record_form_instance')['strategy'] ?? null,
    'Record form instances must retain their specialized field-correction workflow'
);

$plannedTraining = new Training();
$plannedTraining->status = 'planned';
governed_policy_assert_same(
    false,
    GovernedChangePolicyService::isFrozen('training', $plannedTraining),
    'Planned training remains directly editable'
);

$completedTraining = new Training();
$completedTraining->status = 'completed';
governed_policy_assert_same(
    true,
    GovernedChangePolicyService::isFrozen('training', $completedTraining),
    'Completed training becomes frozen evidence'
);

$trainingRecordPolicy = GovernedChangePolicyService::policy('training_record');
governed_policy_assert_same(
    'trainings',
    $trainingRecordPolicy['parent']['table'] ?? null,
    'Training records inherit the parent training lifecycle'
);
governed_policy_assert_same(
    'training_id',
    $trainingRecordPolicy['parent']['foreign_key'] ?? null,
    'Training records resolve their parent through training_id'
);
governed_policy_assert_contains(
    'completed',
    $trainingRecordPolicy['parent']['frozen_statuses'] ?? [],
    'Completed parent training freezes attendee records'
);

$equipmentPolicy = GovernedChangePolicyService::policy('equipment');
foreach (['status', 'site_id', 'last_calibration_date', 'next_calibration_date'] as $field) {
    governed_policy_assert_contains(
        $field,
        $equipmentPolicy['protected_fields'] ?? [],
        'Equipment lifecycle fields must be protected from ordinary edit: ' . $field
    );
}

$coveredSubjects = [
    'training',
    'training_record',
    'competency_record',
    'calibration',
    'equipment_maintenance',
    'reference_material',
    'supplier_evaluation',
    'employee_certificate',
    'equipment_authorization',
    'complaint',
    'nonconformity',
    'capa',
    'audit_schedule',
    'audit_checklist',
    'audit_finding',
    'management_review',
    'review_action',
];
foreach ($coveredSubjects as $subjectType) {
    governed_policy_assert_same(
        true,
        GovernedChangePolicyService::supports($subjectType),
        'Governed-change policy must cover ' . $subjectType
    );
}

fwrite(STDOUT, "qms_governed_change_policy_smoke passed\n");
