<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$controller = (string)file_get_contents($root . '/app/controller/EquipmentTransfer.php');
$authorization = (string)file_get_contents($root . '/app/service/ActionAuthorizationService.php');
$config = (string)file_get_contents($root . '/config/qms.php');

$checks = [
    'ET01' => str_contains($authorization, "'equipmenttransfer.write'")
        && str_contains($authorization, 'canTransferEquipment')
        && str_contains($authorization, "\$controller === 'equipmenttransfer'"),
    'ET02' => str_contains($controller, 'equipmentTransferVisibleSiteIds')
        && str_contains($controller, "allows('equipment_transfer', 'write'")
        && str_contains($controller, 'Db::transaction'),
    'ET03' => str_contains($controller, "whereIn('site_id', \$visibleSiteIds)")
        && str_contains($controller, "whereIn('id', \$visibleSiteIds)"),
    'ET04' => !str_contains(
        substr($config, strpos($config, "'department_head' => ["), strpos($config, "'staff' => [") - strpos($config, "'department_head' => [")),
        "'equipment_transfer'"
    ),
    'ET05' => str_contains($authorization, 'canViewEquipmentTransfer')
        && str_contains($controller, "allows('equipment_transfer', 'view', \$record)")
        && str_contains($authorization, "\$record = self::tableRecord('equipment_transfers'"),
];

$failed = false;
foreach ($checks as $id => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $id . "\n";
    $failed = $failed || !$passed;
}
exit($failed ? 1 : 0);
