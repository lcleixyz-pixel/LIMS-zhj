<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$authorization = (string)file_get_contents($root . '/app/service/ActionAuthorizationService.php');
$schedule = (string)file_get_contents($root . '/app/controller/AuditSchedule.php');
$checklist = (string)file_get_contents($root . '/app/controller/AuditChecklist.php');

$checks = [
    'AA01' => str_contains($authorization, "'auditschedule.organize'"),
    'AA02' => str_contains($authorization, "'auditchecklist.write'")
        && str_contains($authorization, "'auditfinding.write'"),
    'AA03' => str_contains($authorization, 'canExecuteAudit'),
    'AA04' => str_contains($authorization, "'document.controlledprint'")
        && str_contains($authorization, 'canManageDocument($employeeId, $record)'),
    'AA05' => str_contains($schedule, 'writableFields')
        && !str_contains($schedule, '$data = $this->request->post();'),
    'AA06' => str_contains($checklist, "'check_item'")
        && str_contains($checklist, "'check_item' => 'require'")
        && !str_contains($checklist, "'requirement'")
        && !str_contains($checklist, "'remarks'"),
];

$failed = false;
foreach ($checks as $id => $passed) {
    if ($passed) {
        echo "[PASS] {$id}\n";
    } else {
        fwrite(STDERR, "[FAIL] {$id}\n");
        $failed = true;
    }
}
exit($failed ? 1 : 0);
