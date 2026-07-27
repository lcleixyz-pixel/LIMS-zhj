<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

function governed_controller_assert_contains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Missing: ' . $needle . PHP_EOL);
        exit(1);
    }
}

$root = dirname(__DIR__);
$controller = (string)file_get_contents($root . '/app/controller/GovernedChange.php');
$routes = (string)file_get_contents($root . '/route/app.php');
$authorization = (string)file_get_contents($root . '/app/service/ActionAuthorizationService.php');

governed_controller_assert_contains(
    'GovernedChangeService::createRequest',
    $controller,
    'Controller must create governed correction requests through the shared service'
);
governed_controller_assert_contains(
    'GovernedChangeService::decide',
    $controller,
    'Controller must decide requests through the append-only service'
);
governed_controller_assert_contains(
    'GovernedChangeService::recordEvent',
    $controller,
    'Controlled master-data state changes must use the shared event ledger'
);
governed_controller_assert_contains(
    'GovernedChangeService::inboxRequestsForDisplay',
    $controller,
    'Applicants and approvers must share a role-aware cross-module request center'
);
governed_controller_assert_contains(
    "ActionAuthorizationService::allows('governedchange', 'decide')",
    $controller,
    'The request center must separate read access from decision authority'
);
governed_controller_assert_contains(
    "Route::post('governed_change/request'",
    $routes,
    'Request route must be POST-only'
);
governed_controller_assert_contains(
    "Route::post('governed_change/decide'",
    $routes,
    'Decision route must be POST-only'
);
governed_controller_assert_contains(
    "Route::post('governed_change/event'",
    $routes,
    'Lifecycle event route must be POST-only'
);
governed_controller_assert_contains(
    "Route::get('governed_change/inbox'",
    $routes,
    'Approver inbox must have an authenticated GET route'
);
governed_controller_assert_contains(
    "'governedchange.request' => true",
    $authorization,
    'Authenticated users must be allowed to request a correction'
);
governed_controller_assert_contains(
    "'governedchange.decide'",
    $authorization,
    'Governed correction decisions must have an explicit action policy'
);

fwrite(STDOUT, "qms_governed_change_controller_smoke passed\n");
