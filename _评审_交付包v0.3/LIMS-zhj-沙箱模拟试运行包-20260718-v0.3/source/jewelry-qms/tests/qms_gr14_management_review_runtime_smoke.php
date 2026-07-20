<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\service\ManagementReviewInputService;
use think\facade\Config;

if (
    !str_contains((string)Config::get('qms.environment_label', ''), '8011')
    && getenv('QMS_ALLOW_DESTRUCTIVE_SMOKE') !== '1'
) {
    fwrite(STDERR, "拒绝运行：管理评审快照回归仅允许在 8011 候选环境执行。\n");
    exit(2);
}

$snapshot = ManagementReviewInputService::snapshot();
if (!ManagementReviewInputService::verifySnapshot($snapshot)) {
    throw new RuntimeException('[FAIL] MRR01 管理评审输入快照完整性校验失败');
}
fwrite(STDOUT, "[PASS] MRR01 管理评审输入快照完整性校验通过\n");

$categories = (array)($snapshot['categories'] ?? []);
if (count($categories) !== 18) {
    throw new RuntimeException('[FAIL] MRR02 管理评审输入应为 18 个可独立下钻分类');
}
$labels = array_column($categories, 'label');
if (!in_array('资源充分性：设备', $labels, true) || !in_array('资源充分性：人员', $labels, true)) {
    throw new RuntimeException('[FAIL] MRR03 设备与人员资源未拆分');
}
fwrite(STDOUT, "[PASS] MRR02 管理评审 18 类输入可独立下钻\n");
fwrite(STDOUT, "[PASS] MRR03 设备与人员资源已拆分\n");

foreach ($categories as $category) {
    $count = (int)($category['count'] ?? 0);
    $ids = (array)($category['record_ids'] ?? []);
    $truncated = (bool)($category['record_ids_truncated'] ?? false);
    if (!$truncated && $count !== count($ids)) {
        throw new RuntimeException('[FAIL] MRR04 分类计数与明细 ID 数量不一致：' . (string)($category['label'] ?? ''));
    }
}
fwrite(STDOUT, "[PASS] MRR04 未截断分类的计数与明细 ID 一致\n");
fwrite(STDOUT, "qms_gr14_management_review_runtime_smoke passed\n");
