<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

function governed_ui_assert_contains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Missing: ' . $needle . PHP_EOL);
        exit(1);
    }
}

$root = dirname(__DIR__);
$crud = (string)file_get_contents($root . '/app/controller/CrudBase.php');
$layout = (string)file_get_contents($root . '/app/view/layout/main.html');
$panel = (string)file_get_contents($root . '/app/view/common/governed_change_panel.html');

governed_ui_assert_contains(
    'GovernedChangePolicyService::directUpdateViolation',
    $crud,
    'Generic CRUD updates must enforce the governance policy'
);
governed_ui_assert_contains(
    'GovernedChangePolicyService::deleteViolation',
    $crud,
    'Generic CRUD deletes must enforce the governance policy'
);
governed_ui_assert_contains(
    'GovernedChangeService::projectValues',
    $crud,
    'Generic detail views must show the current effective values'
);
governed_ui_assert_contains(
    'common/governed_change_panel',
    $layout,
    'The shared lifecycle panel must be mounted in the common layout'
);
governed_ui_assert_contains('原值（冻结保留）', $panel, 'Correction form must explain that the original value is retained');
governed_ui_assert_contains('拟更正值', $panel, 'Correction form must collect the proposed field value');
governed_ui_assert_contains('更正原因', $panel, 'Correction form must collect a reason');
governed_ui_assert_contains('当前有效值', $panel, 'History must distinguish the current effective value');
governed_ui_assert_contains('处理这条申请', $panel, 'Approvers must see an unambiguous decision card');

fwrite(STDOUT, "qms_governed_change_ui_smoke passed\n");
