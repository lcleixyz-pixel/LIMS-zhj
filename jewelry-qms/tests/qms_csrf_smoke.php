<?php
declare(strict_types=1);

$root = dirname(__DIR__);

function assert_contains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Missing: ' . $needle . PHP_EOL);
        exit(1);
    }
}

$route = (string)file_get_contents($root . '/route/app.php');
$layout = (string)file_get_contents($root . '/app/view/layout/main.html');
$script = (string)file_get_contents($root . '/public/static/js/csrf.js');
$spike = (string)file_get_contents($root . '/docs/CSRF_SPIKE.md');

assert_contains('\\think\\middleware\\FormTokenCheck::class', $route, 'Authenticated route group enables ThinkPHP form token middleware');
assert_contains('token_meta()', $layout, 'Main layout renders CSRF token meta');
assert_contains('/static/js/csrf.js', $layout, 'Main layout loads CSRF helper script');

foreach ([
    'meta[name="csrf-token"]',
    'input[name="__token__"]',
    "input.name = '__token__'",
    'X-CSRF-TOKEN',
    'ajaxSetup',
] as $needle) {
    assert_contains($needle, $script, 'CSRF helper supports ' . $needle);
}

foreach ([
    'token_meta()',
    'FormTokenCheck',
    'X-CSRF-TOKEN',
    'single-use',
    'authenticated QMS route group',
] as $needle) {
    assert_contains($needle, $spike, 'Spike notes record ' . $needle);
}

echo "qms_csrf_smoke passed\n";
