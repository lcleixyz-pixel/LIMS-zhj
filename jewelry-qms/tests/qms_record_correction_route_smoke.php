<?php
declare(strict_types=1);

function qms_correction_assert_contains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Missing: ' . $needle . PHP_EOL);
        exit(1);
    }
}

$routeSource = file_get_contents(__DIR__ . '/../route/app.php') ?: '';
$controllerSource = file_get_contents(__DIR__ . '/../app/controller/RecordFormInstance.php') ?: '';
$viewSource = file_get_contents(__DIR__ . '/../app/view/record_form_instance/view.html') ?: '';

qms_correction_assert_contains(
    "Route::post('record_form_instance/requestCorrection'",
    $routeSource,
    'Record correction submission remains POST'
);
qms_correction_assert_contains(
    "Route::get('record_form_instance/requestCorrection'",
    $routeSource,
    'Record correction GET recovers users who land on the action URL'
);
qms_correction_assert_contains(
    'isPost()',
    $controllerSource,
    'Record correction controller detects non-POST access before doing write work'
);
qms_correction_assert_contains(
    '请在记录详情页填写更正原因后提交申请',
    $controllerSource,
    'Record correction GET explains the next action'
);
qms_correction_assert_contains(
    'correctionRequestsFor',
    $controllerSource,
    'Record detail loads existing correction requests for persistent feedback'
);
qms_correction_assert_contains(
    'correctionRequests',
    $controllerSource,
    'Record detail assigns correction request rows to the view'
);
qms_correction_assert_contains(
    '更正申请记录',
    $viewSource,
    'Record detail renders a persistent correction request section'
);
qms_correction_assert_contains(
    '待质量负责人或批准人核对',
    $viewSource,
    'Record detail explains who must verify the field-level request'
);

echo "qms_record_correction_route_smoke passed\n";
