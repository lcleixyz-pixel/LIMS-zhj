<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require dirname(__DIR__, 2) . '/app/common.php';

$app = new think\App();
$app->initialize();

use think\facade\Config;
use think\facade\Db;

function catalog_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function responsibility_assert_throws(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (DomainException) {
        return;
    }

    catalog_assert(false, $message);
}

function responsibility_fixture_row(string $table, array $fields): string
{
    $now = date('Y-m-d H:i:s');
    $id = qms_uuid();
    Db::name($table)->insert(array_merge([
        'id' => $id,
        'publish' => 1,
        'soft_delete' => 0,
        'created' => $now,
        'modified' => $now,
    ], $fields));

    return $id;
}

function catalog_company_id(): string
{
    return (string)Config::get('qms.company_id');
}

function catalog_in_transaction(callable $callback): void
{
    Db::startTrans();
    try {
        $callback();
    } finally {
        Db::rollback();
    }
}
