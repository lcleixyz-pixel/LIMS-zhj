<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\service\FinalCandidateAssemblyService;

(new think\App())->initialize();

function final_candidate_guard_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$case = trim((string)getenv('QMS_FINAL_CANDIDATE_GUARD_CASE'));
$errors = FinalCandidateAssemblyService::writableEnvironmentErrors();
if ($case === 'disabled') {
    final_candidate_guard_assert(in_array('QMS_TRIAL_MODE 未启用', $errors, true), '试运行关闭时必须拒绝写入');
} elseif ($case === 'wrong_batch') {
    final_candidate_guard_assert(count(array_filter($errors, static fn(string $error): bool => str_contains($error, 'QMS_TRIAL_BATCH'))) === 1, '批次错误时必须拒绝写入');
} elseif ($case === 'enabled') {
    final_candidate_guard_assert($errors === [], '8021正确试运行配置不应产生环境门禁错误');
} else {
    final_candidate_guard_assert(false, '未知门禁测试场景');
}

echo "qms_final_candidate_guard_smoke {$case} passed\n";
