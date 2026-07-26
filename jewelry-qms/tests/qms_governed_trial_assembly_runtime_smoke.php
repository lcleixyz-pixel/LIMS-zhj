<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\service\GovernedTrialAssemblyService;
use app\service\TrialModeService;

(new think\App())->initialize();

function governed_runtime_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

governed_runtime_assert(
    class_exists(GovernedTrialAssemblyService::class),
    '治理试运行数据库装配服务尚未实现'
);
governed_runtime_assert(TrialModeService::isEnabled(), '运行测试必须位于QMS_TRIAL_MODE=true的隔离环境');
governed_runtime_assert(
    TrialModeService::trialBatch() === 'GOV-TRIAL-20260724',
    '运行测试必须使用固定治理试运行批次'
);

$before = GovernedTrialAssemblyService::nonSimulationFingerprint();
$inspection = GovernedTrialAssemblyService::inspect();
governed_runtime_assert(($inspection['validation']['ok'] ?? false) === true, '装配前蓝图自检必须通过');

$first = GovernedTrialAssemblyService::apply(true);
$second = GovernedTrialAssemblyService::apply(true);
$verification = GovernedTrialAssemblyService::verify();
$after = GovernedTrialAssemblyService::nonSimulationFingerprint();

governed_runtime_assert(($first['counts']['trial_documents'] ?? 0) === 38, '首次装配应形成1份手册和37份程序');
governed_runtime_assert(($first['counts']['trial_templates'] ?? 0) === 104, '首次装配应形成104份活动模板');
governed_runtime_assert(($first['counts']['simulation_instances'] ?? 0) === 10, '首次装配应形成两场所10份代表性SIM记录');
governed_runtime_assert(($second['counts'] ?? []) === ($first['counts'] ?? []), '重复装配后数量必须完全一致');
governed_runtime_assert(($verification['ok'] ?? false) === true, '系统追溯链必须闭合：' . implode('；', $verification['errors'] ?? []));
governed_runtime_assert($after === $before, '装配不得改变任何非SIM文件、模板或运行记录');

echo "qms_governed_trial_assembly_runtime_smoke passed\n";
