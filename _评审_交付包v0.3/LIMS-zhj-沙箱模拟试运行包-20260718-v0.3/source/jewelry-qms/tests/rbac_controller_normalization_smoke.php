<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
require $root . '/app/common.php';

$app = new think\App();
$app->initialize();

use app\service\RbacService;
use think\facade\Config;
use think\facade\Session;

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function assert_false(bool $condition, string $message): void
{
    assert_true(!$condition, $message);
}

Session::set('user.role', 'staff');
assert_true(RbacService::canAccess('RecordFormInstance'), 'staff can access record instances');
assert_true(RbacService::canWrite('RecordFormInstance'), 'staff can create record instances');

$permissions = Config::get('qms.permissions', []);
$permissions['auditor'][] = 'doc_category';
$permissions['auditor'][] = 'doc_template';
Config::set($permissions, 'qms.permissions');

Session::set('user.role', 'auditor');
assert_false(RbacService::canWrite('Document'), 'auditor cannot write documents');
assert_false(RbacService::canWrite('DocCategory'), 'auditor cannot write document categories');
assert_false(RbacService::canWrite('DocTemplate'), 'auditor cannot write document templates');

Session::set('user.role', 'quality_manager');
assert_true(RbacService::canWrite('Document'), 'quality manager can write documents');
assert_true(RbacService::canWrite('DocCategory'), 'quality manager can write document categories');
assert_true(RbacService::canWrite('DocTemplate'), 'quality manager can write document templates');

Session::delete('user.role');

echo "rbac_controller_normalization_smoke passed\n";
