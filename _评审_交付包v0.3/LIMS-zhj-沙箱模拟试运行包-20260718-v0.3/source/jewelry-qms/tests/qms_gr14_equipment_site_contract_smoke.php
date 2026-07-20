<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$equipment = (string)file_get_contents($root . '/app/controller/Equipment.php');
$edit = (string)file_get_contents($root . '/app/view/equipment/edit.html');

$checks = [
    'ESC01' => str_contains($equipment, 'protected array $writableFields')
        && str_contains($equipment, 'protected array $createWritableFields'),
    'ESC02' => preg_match(
        '/protected array \$writableFields\s*=\s*\[(?:(?!site_id).)*\];/s',
        $equipment
    ) === 1,
    'ESC03' => preg_match(
        '/protected array \$createWritableFields\s*=\s*\[(?:(?!\];).)*[\'"]site_id[\'"](?:(?!\];).)*\];/s',
        $equipment
    ) === 1,
    'ESC04' => !str_contains($edit, 'name="site_id"')
        && str_contains($edit, '场所变更须通过设备调拨流程'),
];

$failed = false;
foreach ($checks as $id => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $id . "\n";
    $failed = $failed || !$passed;
}
exit($failed ? 1 : 0);
