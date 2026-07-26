<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\service\TrialModeService;
use think\facade\Config;
use think\facade\Db;

(new think\App())->initialize();

if (!TrialModeService::isEnabled()) {
    fwrite(STDERR, "当前不是试运行环境，已停止建立更正治理验收样例。\n");
    exit(2);
}

$companyId = (string)Config::get('qms.company_id');
$userId = (string)(Db::name('users')->where('username', 'sim_preparer')->value('id') ?: '');
$now = date('Y-m-d H:i:s');
$trainingId = '8c7d2600-0000-4000-8000-000000000001';
$referenceMaterialId = '8c7d2600-0000-4000-8000-000000000002';

if (!Db::name('trainings')->where('id', $trainingId)->find()) {
    Db::name('trainings')->insert([
        'id' => $trainingId,
        'company_id' => $companyId,
        'title' => '[SIM-GOV] 字段更正闭环验收培训',
        'training_type' => 'internal',
        'trainer' => 'SIM 讲师',
        'training_date' => '2026-07-26',
        'duration_hours' => '2.0',
        'content' => '仅用于 8021 治理试运行验收',
        'status' => 'completed',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => $now,
        'modified' => $now,
        'created_by' => $userId !== '' ? $userId : null,
    ]);
}

if (!Db::name('reference_materials')->where('id', $referenceMaterialId)->find()) {
    Db::name('reference_materials')->insert([
        'id' => $referenceMaterialId,
        'company_id' => $companyId,
        'code' => 'SIM-GOV-RM-001',
        'name' => '[SIM-GOV] 状态事件验收标准物质',
        'lot_number' => 'SIM-LOT-001',
        'manufacturer' => 'SIM 厂商',
        'valid_until' => '2027-07-26',
        'storage_location' => 'SIM 柜',
        'status' => 'active',
        'remarks' => '仅用于 8021 治理试运行验收',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => $now,
        'modified' => $now,
        'created_by' => $userId !== '' ? $userId : null,
    ]);
}

fwrite(STDOUT, json_encode([
    'ok' => true,
    'training_url' => '/training/view?id=' . $trainingId,
    'reference_material_url' => '/reference_material/view?id=' . $referenceMaterialId,
    'approver_inbox_url' => '/governed_change/inbox',
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL);
