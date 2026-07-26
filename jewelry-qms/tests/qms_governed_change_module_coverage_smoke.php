<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\service\GovernedChangePolicyService;

(new think\App())->initialize();

function governed_coverage_fail(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

foreach (GovernedChangePolicyService::subjects() as $subjectType => $policy) {
    if (!in_array(($policy['strategy'] ?? ''), ['correction', 'event'], true)) {
        continue;
    }
    $model = $policy['model'] ?? null;
    if (!is_string($model) || !class_exists($model)) {
        governed_coverage_fail('Correction subject has no resolvable model: ' . $subjectType);
    }
}

$auditChecklist = (string)file_get_contents(dirname(__DIR__) . '/app/controller/AuditChecklist.php');
if (substr_count($auditChecklist, 'return parent::edit();') !== 1
    || substr_count($auditChecklist, 'return parent::delete();') !== 1) {
    governed_coverage_fail('Audit checklist custom actions must return to the governed CrudBase guard');
}
if (str_contains($auditChecklist, '$this->assertScheduleWritable((string)$record->audit_schedule_id);')) {
    governed_coverage_fail('Audit checklist must not throw a raw closure error before the governed correction redirect');
}

$panel = (string)file_get_contents(dirname(__DIR__) . '/app/view/common/governed_change_panel.html');
foreach (['data-governed-field', 'data-governed-old', 'data-governed-new', 'qms-annotated-current'] as $needle) {
    if (!str_contains($panel, $needle)) {
        governed_coverage_fail('Rendered tables must receive a visible approved-correction annotation: ' . $needle);
    }
}

fwrite(STDOUT, "qms_governed_change_module_coverage_smoke passed\n");
