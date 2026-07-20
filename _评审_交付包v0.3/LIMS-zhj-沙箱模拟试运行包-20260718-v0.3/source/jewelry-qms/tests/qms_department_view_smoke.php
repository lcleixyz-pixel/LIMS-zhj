<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use think\facade\Db;

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function assert_contains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Missing: ' . $needle . PHP_EOL);
        exit(1);
    }
}

function assert_not_contains(string $needle, string $haystack, string $message): void
{
    if (str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Unexpected: ' . $needle . PHP_EOL);
        exit(1);
    }
}

$root = dirname(__DIR__);
$view = (string)file_get_contents($root . '/app/view/department/view.html');
$index = (string)file_get_contents($root . '/app/view/department/index.html');
$add = (string)file_get_contents($root . '/app/view/department/add.html');
$edit = (string)file_get_contents($root . '/app/view/department/edit.html');

$departmentFields = array_keys(Db::name('departments')->getFields());
foreach (['id', 'company_id', 'name', 'code', 'publish', 'soft_delete', 'created', 'modified'] as $field) {
    assert_true(in_array($field, $departmentFields, true), 'Departments schema exposes ' . $field);
}

assert_not_contains('name="department_id"', $add . $edit, 'Department forms must not post a non-existent department_id');
assert_not_contains('name="responsible_person"', $add . $edit, 'Department forms must not post a non-existent responsible_person');
assert_not_contains('name="status"', $add . $edit, 'Department forms must not post a non-existent status');
assert_not_contains('name="description"', $add . $edit, 'Department forms must not post a non-existent description');
assert_not_contains('name="remark"', $add . $edit, 'Department forms must not post a non-existent remark');

assert_not_contains('name="fields"', $view, 'Department detail must not rely on an unassigned fields variable');
assert_not_contains('volist name="fields"', $view, 'Department detail must render explicit schema-backed rows');
assert_not_contains('record.status', $view, 'Department detail must not read a non-existent status field');
assert_not_contains('record.department_name', $view, 'Department detail must not read non-existent department relationship fields');
assert_not_contains('record.responsible_person', $view, 'Department detail must not read non-existent responsible_person');
assert_not_contains('record.create_time', $view . $index, 'Department pages must use created, not create_time');
assert_not_contains('record.update_time', $view, 'Department detail must use modified, not update_time');

assert_contains('{$record.name', $view, 'Department detail shows department name');
assert_contains('{$record.code', $view, 'Department detail shows department code');
assert_contains('{$record.created', $view, 'Department detail shows created time');
assert_contains('{$record.modified', $view, 'Department detail shows modified time');
assert_contains('{$item.created', $index, 'Department list shows created time');

echo "qms_department_view_smoke passed\n";
