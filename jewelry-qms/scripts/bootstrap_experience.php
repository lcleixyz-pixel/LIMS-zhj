<?php
declare(strict_types=1);

/**
 * 为全新体验环境装入稳定的内置记录模板。
 * 仅在模板表为空时写入；重复启动不会覆盖体验人员的修改。
 */

require dirname(__DIR__) . '/vendor/autoload.php';

$app = new think\App();
$app->initialize();

use app\model\RecordFormTemplate;
use app\service\RecordFormFixtureService;
use think\facade\Session;

Session::set('user', [
    'id' => '00000000-0000-0000-0000-000000000040',
    'username' => 'admin',
    'name' => '系统管理员',
    'role' => 'admin',
]);

$existing = RecordFormTemplate::count();
if ($existing > 0) {
    echo "[jewelry-qms] experience bootstrap skipped: {$existing} templates already exist\n";
    exit(0);
}

$seeded = RecordFormFixtureService::seed();
if ($seeded < 1) {
    fwrite(STDERR, "[jewelry-qms] experience bootstrap failed: no templates seeded\n");
    exit(1);
}

echo "[jewelry-qms] experience bootstrap completed: {$seeded} templates seeded\n";
