<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\service\NotificationService;

function qms_notification_assert_same(string $expected, string $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . $expected . PHP_EOL);
        fwrite(STDERR, 'Actual: ' . $actual . PHP_EOL);
        exit(1);
    }
}

$reflection = new ReflectionMethod(NotificationService::class, 'normalizeTypeForStorage');

qms_notification_assert_same(
    'document',
    $reflection->invoke(null, 'document'),
    'Keeps supported notification type'
);
qms_notification_assert_same(
    'general',
    $reflection->invoke(null, 'record_form_instance'),
    'Falls back to general for record correction notifications'
);
qms_notification_assert_same(
    'general',
    $reflection->invoke(null, ''),
    'Falls back to general for blank notification type'
);

echo "qms_notification_type_normalization_smoke passed\n";
