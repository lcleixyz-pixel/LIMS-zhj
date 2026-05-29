<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\model\Notification;
use app\service\NotificationService;
use think\facade\Config;
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

function ensure_column(string $table, string $column, string $ddl): void
{
    $exists = (int)Db::query(
        'SELECT COUNT(*) AS total FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        [$table, $column]
    )[0]['total'];
    if ($exists === 0) {
        Db::execute($ddl);
    }
}

function ensure_index(string $table, string $indexName, string $ddl): void
{
    $exists = (int)Db::query(
        'SELECT COUNT(*) AS total FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
        [$table, $indexName]
    )[0]['total'];
    if ($exists === 0) {
        Db::execute($ddl);
    }
}

$root = dirname(__DIR__);
$schema = (string)file_get_contents($root . '/database/jewelry_qms.sql');
$migration = (string)file_get_contents($root . '/database/migrations/20260529_reminder_notifications.sql');
$console = (string)file_get_contents($root . '/config/console.php');
$dashboard = (string)file_get_contents($root . '/app/controller/Dashboard.php');
$command = (string)file_get_contents($root . '/app/command/CheckReminders.php');
$service = (string)file_get_contents($root . '/app/service/NotificationService.php');

assert_contains('`notification_key` varchar(200) DEFAULT NULL', $schema, 'Base schema stores notification keys');
assert_contains('UNIQUE KEY `company_notification_key` (`company_id`,`notification_key`)', $schema, 'Base schema deduplicates notifications by company and key');
assert_contains('UNIQUE KEY `notification_user` (`notification_id`,`user_id`)', $schema, 'Base schema deduplicates notification recipients');
assert_contains('information_schema.COLUMNS', $migration, 'Migration checks notification_key column idempotently');
assert_contains('information_schema.STATISTICS', $migration, 'Migration checks indexes idempotently');
assert_contains('company_notification_key', $migration, 'Migration creates company notification key index');
assert_contains('notification_user', $migration, 'Migration creates notification user unique index');
assert_contains('app\\command\\CheckReminders', $console, 'Console registers CheckReminders command');
assert_contains("setName('check:reminders')", $command, 'CheckReminders exposes the expected command name');
assert_contains('checkDocumentReviewDue', $service, 'NotificationService checks document review reminders');
assert_contains('checkCompetencyDue', $service, 'NotificationService checks competency reminders');
assert_not_contains('NotificationService::checkCalibrationDue();', $dashboard, 'Dashboard does not create calibration reminders on page load');
assert_not_contains('NotificationService::checkCapaOverdue();', $dashboard, 'Dashboard does not create CAPA reminders on page load');

ensure_column('notifications', 'notification_key', 'ALTER TABLE `notifications` ADD COLUMN `notification_key` varchar(200) DEFAULT NULL AFTER `due_date`');
ensure_index('notifications', 'company_notification_key', 'ALTER TABLE `notifications` ADD UNIQUE KEY `company_notification_key` (`company_id`,`notification_key`)');
Db::execute(
    'DELETE nu FROM `notification_users` nu
     JOIN `notification_users` keep_nu
       ON keep_nu.notification_id = nu.notification_id
      AND keep_nu.user_id = nu.user_id
      AND keep_nu.id < nu.id'
);
ensure_index('notification_users', 'notification_user', 'ALTER TABLE `notification_users` ADD UNIQUE KEY `notification_user` (`notification_id`,`user_id`)');

$companyId = (string)Config::get('qms.company_id');
$key = 'smoke_reminder:' . qms_uuid() . ':2026-05';
$userA = '00000000-0000-0000-0000-000000000040';
$userB = '00000000-0000-0000-0000-000000000041';

try {
    NotificationService::notifyUsers(
        'Smoke提醒',
        '第一次提醒',
        'general',
        [$userA],
        'dashboard',
        'index',
        null,
        '2026-05-29',
        $key
    );
    NotificationService::notifyUsers(
        'Smoke提醒',
        '第二次提醒',
        'general',
        [$userA, $userB],
        'dashboard',
        'index',
        null,
        '2026-05-29',
        $key
    );

    $notifications = Db::name('notifications')
        ->where('company_id', $companyId)
        ->where('notification_key', $key)
        ->select()
        ->toArray();
    assert_true(count($notifications) === 1, 'Notification key creates one notification only');

    $notificationId = (string)$notifications[0]['id'];
    $recipients = Db::name('notification_users')
        ->where('notification_id', $notificationId)
        ->column('user_id');
    sort($recipients);
    assert_true($recipients === [$userA, $userB], 'Repeated keyed notification supplements new recipients without duplicates');
} finally {
    $ids = Db::name('notifications')->where('notification_key', $key)->column('id');
    if ($ids !== []) {
        Db::name('notification_users')->whereIn('notification_id', $ids)->delete();
        Db::name('notifications')->whereIn('id', $ids)->delete();
    }
}

$summary = NotificationService::runReminderChecks('all');
assert_true(array_key_exists('calibration', $summary), 'Reminder runner returns calibration count');
assert_true(array_key_exists('capa', $summary), 'Reminder runner returns CAPA count');
assert_true(array_key_exists('doc_review', $summary), 'Reminder runner returns document review count');
assert_true(array_key_exists('competency', $summary), 'Reminder runner returns competency count');

echo "qms_reminder_command_smoke passed\n";
