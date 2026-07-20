<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
require $root . '/app/common.php';

$app = new think\App();
$app->initialize();

use think\facade\Config;

$passes = [];
$failures = [];

function e_case(bool $ok, string $id, string $msg): void
{
    global $passes, $failures;
    if ($ok) {
        $passes[] = "{$id} {$msg}";
    } else {
        $failures[] = "{$id} {$msg}";
    }
}

$configSrc = (string)file_get_contents($root . '/config/qms.php');
$commonSrc = (string)file_get_contents($root . '/app/common.php');
e_case(str_contains($configSrc, 'simulation_uuid_prefix'), 'E01', 'config exposes simulation_uuid_prefix');
e_case(str_contains($commonSrc, 'sim-') && str_contains($commonSrc, 'QMS_SIM_UUID'), 'E02', 'qms_uuid supports sim- prefix gate');

Config::set(['simulation_uuid_prefix' => false], 'qms');
putenv('QMS_SIM_UUID=0');
$_ENV['QMS_SIM_UUID'] = '0';
$off = qms_uuid();
e_case(!str_starts_with($off, 'sim-'), 'E03', 'prefix off yields plain UUID');

Config::set(['simulation_uuid_prefix' => true], 'qms');
$on = qms_uuid();
e_case(str_starts_with($on, 'sim-'), 'E04', 'prefix on yields sim- UUID');

Config::set(['simulation_uuid_prefix' => false], 'qms');
putenv('QMS_SIM_UUID=1');
$_ENV['QMS_SIM_UUID'] = '1';
$envOn = qms_uuid();
e_case(str_starts_with($envOn, 'sim-'), 'E05', 'QMS_SIM_UUID=1 enables sim- prefix');

Config::set(['simulation_uuid_prefix' => false], 'qms');
putenv('QMS_SIM_UUID=0');
$_ENV['QMS_SIM_UUID'] = '0';

foreach ($passes as $pass) {
    echo "PASS {$pass}\n";
}
foreach ($failures as $failure) {
    fwrite(STDERR, "FAIL {$failure}\n");
}
if ($failures !== []) {
    exit(1);
}
echo "qms_wave1_e_sim_uuid_smoke passed\n";
