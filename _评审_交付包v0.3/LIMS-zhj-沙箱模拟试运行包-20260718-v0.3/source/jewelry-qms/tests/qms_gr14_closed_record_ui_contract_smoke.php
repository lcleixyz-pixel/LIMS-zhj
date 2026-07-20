<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$audit = (string)file_get_contents($root . '/app/controller/AuditPlan.php');
$auditIndex = (string)file_get_contents($root . '/app/view/audit_plan/index.html');
$document = (string)file_get_contents($root . '/app/controller/Document.php');
$documentIndex = (string)file_get_contents($root . '/app/view/document/index.html');
$templateIndex = (string)file_get_contents($root . '/app/view/record_form_template/index.html');

$checks = [
    'CRU01' => str_contains($audit, '只有草稿内审计划可直接编辑')
        && str_contains($audit, '内审计划不得通过 GET 删除')
        && str_contains($auditIndex, "\$item.status == 'draft'"),
    'CRU02' => str_contains($document, "status !== 'draft'")
        && str_contains($document, '只有草稿文件可直接编辑')
        && str_contains($documentIndex, "\$doc.status == 'draft'"),
    'CRU03' => str_contains($templateIndex, "\$item.status == 'trial_ready'")
        && str_contains($templateIndex, '试运行就绪（非正式发布）')
        && str_contains($templateIndex, "\$item.status == 'draft' && qms_can_action"),
];

$failed = false;
foreach ($checks as $id => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $id . "\n";
    $failed = $failed || !$passed;
}
exit($failed ? 1 : 0);
