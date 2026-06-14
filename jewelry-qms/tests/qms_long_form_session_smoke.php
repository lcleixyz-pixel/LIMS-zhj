<?php
declare(strict_types=1);

$root = dirname(__DIR__);

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

$sessionConfig = require $root . '/config/session.php';
$route = (string)file_get_contents($root . '/route/app.php');
$dashboard = (string)file_get_contents($root . '/app/controller/Dashboard.php');
$createView = (string)file_get_contents($root . '/app/view/record_form_instance/create.html');
$editView = (string)file_get_contents($root . '/app/view/record_form_instance/edit.html');

assert_true((int)($sessionConfig['expire'] ?? 0) >= 28800, 'Session lifetime supports long record-form editing');
assert_contains("dashboard/keepAlive", $route, 'Authenticated keep-alive route is registered');
assert_contains('function keepAlive', $dashboard, 'Dashboard exposes an authenticated keep-alive endpoint');
assert_contains("Session::set('last_keep_alive_at'", $dashboard, 'Keep-alive endpoint touches session state');
assert_contains('record-form-session-keepalive.js', $createView, 'Record create page loads session keep-alive helper');
assert_contains('record-form-session-keepalive.js', $editView, 'Record edit page loads session keep-alive helper');

$scriptPath = $root . '/public/static/js/record-form-session-keepalive.js';
assert_true(is_file($scriptPath), 'Record form session keep-alive helper exists');
$script = (string)file_get_contents($scriptPath);
assert_contains('/dashboard/keepAlive', $script, 'Keep-alive helper pings the authenticated endpoint');
assert_contains('setInterval', $script, 'Keep-alive helper runs while the form remains open');
assert_contains('qms-session-keepalive-status', $script, 'Keep-alive helper can warn when the session is lost');

echo "qms_long_form_session_smoke passed\n";
