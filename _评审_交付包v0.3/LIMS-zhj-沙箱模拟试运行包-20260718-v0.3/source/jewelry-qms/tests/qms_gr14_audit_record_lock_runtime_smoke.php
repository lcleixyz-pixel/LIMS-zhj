<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\service\AuditClosureService;
use think\facade\Config;
use think\facade\Db;

if (
    !str_contains((string)Config::get('qms.environment_label', ''), '8011')
    && getenv('QMS_ALLOW_DESTRUCTIVE_SMOKE') !== '1'
) {
    fwrite(STDERR, "拒绝运行：本测试会创建临时内审记录，只能在 8011 候选环境执行。\n");
    exit(2);
}

function arl_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('[FAIL] ' . $message);
    }
    fwrite(STDOUT, "[PASS] {$message}\n");
}

$planId = 'gr14-audit-lock-plan';
$scheduleId = 'gr14-audit-lock-schedule';
$checklistId = 'gr14-audit-lock-checklist';
$companyId = (string)Config::get('qms.company_id');
$siteId = (string)Db::name('sites')
    ->where('company_id', $companyId)
    ->where('soft_delete', 0)
    ->value('id');
$now = date('Y-m-d H:i:s');

$cleanup = static function () use ($planId, $scheduleId, $checklistId): void {
    Db::name('audit_findings')->where('audit_schedule_id', $scheduleId)->delete();
    Db::name('audit_checklists')->where('id', $checklistId)->delete();
    Db::name('audit_schedules')->where('id', $scheduleId)->delete();
    Db::name('audit_plans')->where('id', $planId)->delete();
};

$cleanup();
try {
    Db::name('audit_plans')->insert([
        'id' => $planId,
        'company_id' => $companyId,
        'plan_year' => (int)date('Y'),
        'title' => 'SIM-G-R14 内审锁定测试',
        'scope' => '仅用于 8011 回归',
        'criteria' => 'ISO/IEC 17025',
        'status' => 'in_progress',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => $now,
        'modified' => $now,
    ]);
    Db::name('audit_schedules')->insert([
        'id' => $scheduleId,
        'audit_plan_id' => $planId,
        'audit_date' => date('Y-m-d'),
        'site_id' => $siteId ?: null,
        'status' => 'in_progress',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => $now,
        'modified' => $now,
    ]);
    Db::name('audit_checklists')->insert([
        'id' => $checklistId,
        'audit_schedule_id' => $scheduleId,
        'clause' => '8.8',
        'check_item' => '检查证据完整性',
        'result' => null,
        'evidence' => '',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => $now,
    ]);

    $reasons = AuditClosureService::scheduleBlockingReasons($scheduleId);
    arl_assert(
        in_array('检查结果未填写', $reasons, true)
            && in_array('客观证据未填写', $reasons, true),
        'ARL01 缺结果或客观证据时禁止完成审核日程'
    );

    Db::name('audit_checklists')->where('id', $checklistId)->update([
        'result' => 'nonconform',
        'evidence' => '访谈及记录抽查',
    ]);
    arl_assert(
        in_array('不符合检查结果未登记审核发现', AuditClosureService::scheduleBlockingReasons($scheduleId), true),
        'ARL02 不符合检查项必须登记审核发现'
    );

    Db::name('audit_schedules')->where('id', $scheduleId)->update(['status' => 'completed']);
    $locked = false;
    try {
        AuditClosureService::assertScheduleWritable($scheduleId);
    } catch (DomainException $exception) {
        $locked = str_contains($exception->getMessage(), '已锁定');
    }
    arl_assert($locked, 'ARL03 已完成审核日程的检查记录和发现被服务端锁定');
} finally {
    $cleanup();
}

fwrite(STDOUT, "qms_gr14_audit_record_lock_runtime_smoke passed\n");
