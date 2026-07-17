<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

function audit_check(bool $condition, string $id, string $message): void
{
    global $failures;
    if ($condition) {
        echo "[PASS] {$id} {$message}\n";
        return;
    }
    $failures[] = "{$id} {$message}";
    fwrite(STDERR, "[FAIL] {$id} {$message}\n");
}

$closure = (string)file_get_contents($root . '/app/service/AuditClosureService.php');
$workflow = (string)file_get_contents($root . '/app/service/WorkflowService.php');
$finding = (string)file_get_contents($root . '/app/controller/AuditFinding.php');
$checklist = (string)file_get_contents($root . '/app/controller/AuditChecklist.php');
$authorization = (string)file_get_contents($root . '/app/service/ActionAuthorizationService.php');
$scheduleView = (string)file_get_contents($root . '/app/view/audit_schedule/view.html');

audit_check(
    str_contains($closure, 'findingCapaBlockingReasons')
    && str_contains($closure, '未关联 CAPA')
    && str_contains($closure, 'CAPA 验证记录不完整')
    && str_contains($closure, 'CAPA 有效性评价不完整'),
    'AC01',
    '非观察项必须经匹配 CAPA、验证和有效性评价后才能关闭审核链'
);
audit_check(
    str_contains($workflow, "->update(['status' => 'closed'])")
    && str_contains($workflow, 'effectiveness_result'),
    'AC02',
    '审核发现关闭只能由完成验证和有效性评价的 CAPA 服务驱动'
);
audit_check(
    str_contains($finding, 'public function delete()')
    && str_contains($finding, '审核发现不得直接删除'),
    'AC03',
    '审核发现不得通过通用删除动作绕过关闭链'
);
audit_check(
    str_contains($closure, '检查结果未填写')
    && str_contains($closure, '客观证据未填写')
    && str_contains($closure, '不符合检查结果未登记审核发现'),
    'AC04',
    '检查结果、客观证据及不符合项发现均完整后才能关闭日程'
);
audit_check(
    str_contains($closure, 'assertScheduleWritable')
    && substr_count($checklist, 'assertScheduleWritable') >= 3
    && str_contains($scheduleView, "\$record.status != 'completed'")
    && str_contains($scheduleView, '添加检查项')
    && str_contains($scheduleView, '登记发现'),
    'AC05',
    '已完成日程从服务端锁定检查记录并从页面移除新增入口'
);
audit_check(
    str_contains($finding, "where('s.status', '<>', 'completed')")
    && str_contains($closure, 'scheduleBelongsToCompany')
    && str_contains($authorization, 'authorizationDeniedRecord')
    && str_contains($authorization, "'audit_schedules'")
    && str_contains($authorization, "'audit_findings'"),
    'AC06',
    '审核日程、检查记录和发现按机构隔离，完成日程从新增表单移除'
);

if ($failures !== []) {
    exit(1);
}
echo "G-R14 内审关闭契约通过。\n";
