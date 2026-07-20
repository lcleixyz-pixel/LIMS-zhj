<?php
declare(strict_types=1);

/**
 * S-5：POST 默认要求 canWrite；只读 POST 白名单；业务例外保留。
 */
$root = dirname(__DIR__);
require_once $root . '/app/middleware/Rbac.php';

use app\middleware\Rbac;

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$src = (string)file_get_contents($root . '/app/middleware/Rbac.php');
assert_true(!str_contains($src, '$writeActions'), 'S-5 removes $writeActions blacklist');
assert_true(str_contains($src, 'requiresWritePermission'), 'S-5 exposes requiresWritePermission');
assert_true(str_contains($src, 'planningresponsibility') && str_contains($src, 'approve'), 'S-5 keeps responsibility approve exception');
assert_true(str_contains($src, 'confirmreceipt') && str_contains($src, 'confirmrecall'), 'S-5 keeps document recipient exceptions');

assert_true(Rbac::requiresWritePermission('POST', 'add') === true, 'POST add requires write');
assert_true(Rbac::requiresWritePermission('POST', 'edit') === true, 'POST edit requires write');
assert_true(Rbac::requiresWritePermission('POST', 'delete') === true, 'POST delete requires write');
assert_true(Rbac::requiresWritePermission('POST', 'submitreview') === true, 'POST submitReview requires write');
assert_true(Rbac::requiresWritePermission('PUT', 'edit') === true, 'PUT edit requires write');
assert_true(Rbac::requiresWritePermission('DELETE', 'delete') === true, 'DELETE requires write');

assert_true(Rbac::requiresWritePermission('POST', 'index') === false, 'POST index is read-only whitelist');
assert_true(Rbac::requiresWritePermission('POST', 'view') === false, 'POST view is read-only whitelist');
assert_true(Rbac::requiresWritePermission('POST', 'exportcsv') === false, 'POST exportCsv is read-only whitelist');
assert_true(Rbac::requiresWritePermission('POST', 'download') === false, 'POST download is read-only whitelist');
assert_true(Rbac::requiresWritePermission('GET', 'add') === false, 'GET never requires write via this gate');
assert_true(Rbac::requiresWritePermission('GET', 'edit') === false, 'GET edit does not require write via S-5 gate');

// 5 角色矩阵（对照 A-3 / rbac_controller_normalization 基线）：受限角色写 Document 应 canWrite=false
require $root . '/vendor/autoload.php';
require $root . '/app/common.php';
$app = new think\App();
$app->initialize();

use app\service\RbacService;
use think\facade\Session;

$matrix = [
    'staff' => false,
    'auditor' => false,
    'quality_manager' => true,
    'department_head' => false,
    'admin' => true,
];

foreach ($matrix as $role => $expectWrite) {
    Session::set('user', [
        'id' => 's5-' . $role,
        'role' => $role,
        'username' => $role . '_test',
    ]);
    $can = RbacService::canWrite('Document');
    assert_true(
        $can === $expectWrite,
        "S-5 role matrix Document canWrite({$role}) expected " . ($expectWrite ? 'true' : 'false')
    );
}

// 受限角色：未声明写 action 的 POST 变更动作 → 需要 write（中间件语义）
assert_true(
    Rbac::requiresWritePermission('POST', 'createcapa') === true,
    'undeclared write POST action requires canWrite'
);

Session::delete('user');

echo "qms_wave1_s5_rbac_whitelist_smoke passed\n";
