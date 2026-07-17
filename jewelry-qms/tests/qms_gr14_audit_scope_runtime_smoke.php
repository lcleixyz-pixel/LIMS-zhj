<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\service\ActionAuthorizationService;
use app\service\AuditClosureService;
use think\facade\Config;
use think\facade\Db;
use think\facade\Session;

if (
    !str_contains((string)Config::get('qms.environment_label', ''), '8011')
    && getenv('QMS_ALLOW_DESTRUCTIVE_SMOKE') !== '1'
) {
    fwrite(STDERR, "拒绝运行：内审机构隔离回归仅允许在 8011 候选环境执行。\n");
    exit(2);
}

final class Gr14AuditRequestStub
{
    public function __construct(private array $postData)
    {
    }

    public function post(string $name, mixed $default = ''): mixed
    {
        return $this->postData[$name] ?? $default;
    }

    public function param(string $name, mixed $default = ''): mixed
    {
        return $this->postData[$name] ?? $default;
    }

    public function isPost(): bool
    {
        return true;
    }
}

function asr_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }
    fwrite(STDOUT, "[PASS] {$message}\n");
}

$otherCompanyId = 'gr14-audit-scope-company';
$otherPlanId = 'gr14-audit-scope-plan';
$otherScheduleId = 'gr14-audit-scope-schedule';
$otherFindingId = 'gr14-audit-scope-finding';
$now = date('Y-m-d H:i:s');
$cleanup = static function () use ($otherCompanyId, $otherPlanId, $otherScheduleId, $otherFindingId): void {
    Db::name('audit_findings')->where('id', $otherFindingId)->delete();
    Db::name('audit_schedules')->where('id', $otherScheduleId)->delete();
    Db::name('audit_plans')->where('id', $otherPlanId)->delete();
    Db::name('companies')->where('id', $otherCompanyId)->delete();
    Session::clear();
};

$cleanup();
try {
    Db::name('companies')->insert([
        'id' => $otherCompanyId,
        'name' => 'SIM-G-R14 跨机构隔离测试',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => $now,
        'modified' => $now,
    ]);
    Db::name('audit_plans')->insert([
        'id' => $otherPlanId,
        'company_id' => $otherCompanyId,
        'plan_year' => (int)date('Y'),
        'title' => 'SIM-G-R14 跨机构内审计划',
        'status' => 'in_progress',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => $now,
        'modified' => $now,
    ]);
    Db::name('audit_schedules')->insert([
        'id' => $otherScheduleId,
        'audit_plan_id' => $otherPlanId,
        'audit_date' => date('Y-m-d'),
        'status' => 'in_progress',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => $now,
        'modified' => $now,
    ]);
    Db::name('audit_findings')->insert([
        'id' => $otherFindingId,
        'audit_schedule_id' => $otherScheduleId,
        'finding_number' => 'SIM-ASR-OTHER-001',
        'finding_type' => 'observation',
        'description' => '跨机构读取边界测试',
        'status' => 'open',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => $now,
        'modified' => $now,
    ]);

    $qualityEmployee = Db::name('employees')->where('employee_number', 'E002')->find();
    $qualityUser = Db::name('users')
        ->where('employee_id', (string)$qualityEmployee['id'])
        ->where('publish', 1)
        ->where('soft_delete', 0)
        ->find();
    Session::set('user', [
        'id' => (string)$qualityUser['id'],
        'employee_id' => (string)$qualityEmployee['id'],
        'role' => (string)$qualityUser['role'],
    ]);

    $decision = ActionAuthorizationService::requestDecision(
        'AuditFinding',
        'add',
        new Gr14AuditRequestStub(['audit_schedule_id' => $otherScheduleId])
    );
    asr_assert($decision === false, 'ASR01 质量负责人也不能向其他机构日程写入审核发现');

    foreach ([
        ['AuditPlan', 'edit', $otherPlanId],
        ['AuditPlan', 'approve', $otherPlanId],
        ['AuditPlan', 'delete', $otherPlanId],
        ['AuditSchedule', 'edit', $otherScheduleId],
        ['AuditSchedule', 'complete', $otherScheduleId],
        ['AuditSchedule', 'delete', $otherScheduleId],
    ] as [$controller, $action, $id]) {
        $decision = ActionAuthorizationService::requestDecision(
            $controller,
            $action,
            new Gr14AuditRequestStub(['id' => $id])
        );
        asr_assert(
            $decision === false,
            "ASR03 {$controller}.{$action} 拒绝其他机构对象"
        );
    }

    $downloadDecision = ActionAuthorizationService::requestDecision(
        'AuditFinding',
        'downloadEvidence',
        new Gr14AuditRequestStub(['id' => $otherFindingId, 'file_id' => 'missing-file'])
    );
    asr_assert($downloadDecision === false, 'ASR04 质量负责人不能下载其他机构审核发现证据');

    $blocked = false;
    try {
        AuditClosureService::assertScheduleWritable($otherScheduleId);
    } catch (DomainException $exception) {
        $blocked = str_contains($exception->getMessage(), '不存在');
    }
    asr_assert($blocked, 'ASR02 内审写服务拒绝其他机构日程');
} finally {
    $cleanup();
}

fwrite(STDOUT, "qms_gr14_audit_scope_runtime_smoke passed\n");
