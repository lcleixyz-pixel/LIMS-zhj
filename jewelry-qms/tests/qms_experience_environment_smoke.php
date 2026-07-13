<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

putenv('QMS_ENV_LABEL=体验测试环境');
putenv('QMS_ENV_NOTICE=禁止录入正式业务数据、客户资料或其他敏感信息');

$config = require __DIR__ . '/../config/qms.php';
assert_true(($config['environment_label'] ?? '') === '体验测试环境', 'environment label comes from env');
assert_true(
    ($config['environment_notice'] ?? '') === '禁止录入正式业务数据、客户资料或其他敏感信息',
    'environment notice comes from env'
);

$auth = file_get_contents(__DIR__ . '/../app/middleware/Auth.php');
$login = file_get_contents(__DIR__ . '/../app/controller/Login.php');
$main = file_get_contents(__DIR__ . '/../app/view/layout/main.html');
$loginView = file_get_contents(__DIR__ . '/../app/view/login/index.html');

assert_true(str_contains($auth, "'environmentLabel'"), 'auth assigns environment label');
assert_true(str_contains($auth, "'environmentNotice'"), 'auth assigns environment notice');
assert_true(str_contains($login, "'environmentLabel'"), 'login assigns environment label');
assert_true(str_contains($login, "'environmentNotice'"), 'login assigns environment notice');
assert_true(str_contains($main, 'environmentLabel'), 'main layout renders environment label');
assert_true(str_contains($main, 'environmentNotice'), 'main layout renders environment notice');
assert_true(str_contains($loginView, 'environmentLabel'), 'login view renders environment label');
assert_true(str_contains($loginView, '禁止录入'), 'login view includes the no-real-data warning');

putenv('QMS_ENV_LABEL');
putenv('QMS_ENV_NOTICE');
$defaultConfig = require __DIR__ . '/../config/qms.php';
assert_true(($defaultConfig['environment_label'] ?? '') === '', 'default label is empty');
assert_true(($defaultConfig['environment_notice'] ?? '') === '', 'default notice is empty');

echo "qms_experience_environment_smoke passed\n";
