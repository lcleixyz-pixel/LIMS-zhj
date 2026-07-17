<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use think\facade\Config;
use think\facade\Db;

function preflight_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL L10 {$message}\n");
        exit(1);
    }
}

$root = dirname(__DIR__);
$serviceClass = 'app\\service\\P0PreflightService';
$commandClass = 'app\\command\\P0Preflight';
$consoleSource = (string)file_get_contents($root . '/config/console.php');
$migrationPath = $root . '/database/migrations/20260717_p0_record_integrity.sql';

preflight_assert(class_exists($serviceClass), '只读预检服务存在');
preflight_assert(class_exists($commandClass), 'qms:p0-preflight 命令存在');
preflight_assert(str_contains($consoleSource, 'P0Preflight::class'), '预检命令已注册');
preflight_assert(is_file($migrationPath), '受控唯一约束迁移存在');

$companyId = (string)Config::get('qms.company_id');
$sourceId = 'b2000000-0000-4000-8000-000000001001';
$orphanCapaId = 'b2000000-0000-4000-8000-000000001002';
$before = [
    'complaints' => (int)Db::name('customer_complaints')->count(),
    'capas' => (int)Db::name('capas')->count(),
];

try {
    Db::name('customer_complaints')->insert([
        'id' => $sourceId,
        'company_id' => $companyId,
        'complaint_number' => 'CP20262026002',
        'customer_name' => '预检测试客户',
        'received_date' => '2026-07-17',
        'description' => '预检测试',
        'status' => 'received',
        'capa_id' => '123',
        'publish' => 1,
        'soft_delete' => 0,
        'record_status' => 0,
        'created' => '2026-07-17 00:00:00',
        'modified' => '2026-07-17 00:00:00',
    ]);
    Db::name('capas')->insert([
        'id' => $orphanCapaId,
        'company_id' => $companyId,
        'capa_number' => 'P0R13B2-PREFLIGHT-CAPA',
        'source_type' => 'complaint',
        'source_record_id' => 'b2000000-0000-4000-8000-000000009999',
        'description' => '孤儿来源预检',
        'status' => 'open',
        'publish' => 1,
        'soft_delete' => 0,
        'record_status' => 0,
        'created' => '2026-07-17 00:00:00',
        'modified' => '2026-07-17 00:00:00',
    ]);

    /** @var array<string,mixed> $report */
    $report = $serviceClass::scan();
    preflight_assert(($report['mode'] ?? '') === 'read_only', '报告明确标记只读');
    preflight_assert((int)($report['counts']['invalid_source_capa_links'] ?? 0) >= 1, '识别数字型 capa_id');
    preflight_assert((int)($report['counts']['malformed_business_numbers'] ?? 0) >= 1, '识别异常旧编号');
    preflight_assert((int)($report['counts']['orphan_source_capas'] ?? 0) >= 1, '识别孤儿 CAPA 来源');
    preflight_assert((int)($report['counts']['reverse_link_mismatches'] ?? 0) >= 1, '识别反向不一致');
    preflight_assert(($report['blocked'] ?? false) === true, '存在问题时报告阻断');
    preflight_assert(
        !str_contains(json_encode($report, JSON_UNESCAPED_UNICODE) ?: '', '预检测试客户'),
        '报告不泄露客户名称'
    );

    $afterScan = [
        'complaints' => (int)Db::name('customer_complaints')->count(),
        'capas' => (int)Db::name('capas')->count(),
    ];
    preflight_assert(
        $afterScan['complaints'] === $before['complaints'] + 1
        && $afterScan['capas'] === $before['capas'] + 1,
        '扫描不修改业务记录'
    );
} finally {
    Db::name('customer_complaints')->where('id', $sourceId)->delete();
    Db::name('capas')->where('id', $orphanCapaId)->delete();
}

echo "PASS L10 只读预检输出重复、无效关联、孤儿和反向不一致，且不写库\n";
echo "qms_p0_preflight_smoke passed: L10\n";
