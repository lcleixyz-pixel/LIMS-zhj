<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\service\QmsCx0302GovernanceClosureService;

(new think\App())->initialize();

$apply = in_array('--apply', $argv, true);
$result = $apply
    ? QmsCx0302GovernanceClosureService::apply()
    : QmsCx0302GovernanceClosureService::preview();

echo json_encode(
    $result,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
) . PHP_EOL;
