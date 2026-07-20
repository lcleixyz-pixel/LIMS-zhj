<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$route = (string)file_get_contents($root . '/route/app.php');

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$writePaths = [
    'notification/read',
    'notification/markAllRead',
    'capa/advance',
    'nonconformity/createCapa',
    'complaint/createCapa',
    'review_action/createCapa',
    'training/complete',
    'training_plan/approve',
    'training_plan/complete',
    'supplier/qualified',
];

foreach ($writePaths as $path) {
    $getPattern = "Route::get('" . $path . "'";
    assert_true(
        !str_contains($route, $getPattern),
        "S-1: {$path} must not use Route::get"
    );
    $postPattern = "Route::post('" . $path . "'";
    assert_true(
        str_contains($route, $postPattern),
        "S-1: {$path} must use Route::post"
    );
}

// capa/advance 不得残留 Route::get；允许仅有一条 post
assert_true(
    substr_count($route, "Route::get('capa/advance'") === 0,
    'S-1: capa/advance must not keep Route::get'
);

echo "qms_wave1_s1_no_get_writes_smoke passed\n";
