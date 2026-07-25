<?php
declare(strict_types=1);

function qms_correction_decision_assert_contains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Missing: ' . $needle . PHP_EOL);
        exit(1);
    }
}

$root = dirname(__DIR__);
$routeSource = file_get_contents($root . '/route/app.php') ?: '';
$controllerSource = file_get_contents($root . '/app/controller/RecordFormInstance.php') ?: '';
$viewSource = file_get_contents($root . '/app/view/record_form_instance/view.html') ?: '';
$authorizationSource = file_get_contents($root . '/app/service/ActionAuthorizationService.php') ?: '';

qms_correction_decision_assert_contains(
    "Route::post('record_form_instance/decideCorrection'",
    $routeSource,
    'Record correction decision must be a POST action'
);
qms_correction_decision_assert_contains(
    'decideCorrection',
    $controllerSource,
    'Record correction controller must expose a decision action'
);
qms_correction_decision_assert_contains(
    'correctionDecisionsFor',
    $controllerSource,
    'Record detail must load correction decision history'
);
qms_correction_decision_assert_contains(
    'correctionRequestForDecision',
    $controllerSource,
    'Record correction decision must bind to one selected correction request'
);
qms_correction_decision_assert_contains(
    'correction_request_id',
    $controllerSource,
    'Record correction decision must require a selected correction request id'
);
qms_correction_decision_assert_contains(
    '请选择要处理的更正申请',
    $controllerSource,
    'Record correction decision must explain missing selected correction request'
);
qms_correction_decision_assert_contains(
    'recordCorrectionApproverUserIds',
    $controllerSource,
    'Correction requests must notify configured SIM approvers as well as quality managers'
);
qms_correction_decision_assert_contains(
    '更正申请处理',
    $viewSource,
    'Record detail must show a correction decision panel'
);
qms_correction_decision_assert_contains(
    '要处理的申请',
    $viewSource,
    'Record detail must let approvers pick the exact correction request'
);
qms_correction_decision_assert_contains(
    'name="correction_request_id"',
    $viewSource,
    'Record correction decision form must submit the selected correction request id'
);
qms_correction_decision_assert_contains(
    '批准更正',
    $viewSource,
    'Record detail must offer an approve correction action'
);
qms_correction_decision_assert_contains(
    '驳回申请',
    $viewSource,
    'Record detail must offer a reject correction action'
);
qms_correction_decision_assert_contains(
    '更正申请处理结果',
    $viewSource,
    'Record detail must keep a persistent correction decision result'
);
qms_correction_decision_assert_contains(
    '对应申请',
    $viewSource,
    'Record detail must show which request a decision handled'
);
qms_correction_decision_assert_contains(
    "'recordforminstance.decidecorrection'",
    $authorizationSource,
    'Record correction decision must be wired into action authorization'
);
qms_correction_decision_assert_contains(
    'canDecideRecordCorrection',
    $authorizationSource,
    'Record correction decision must use an explicit authorization helper'
);
qms_correction_decision_assert_contains(
    'recordFormInstanceRecord',
    $authorizationSource,
    'Record correction authorization must load record_form_instances without assuming soft_delete exists'
);
qms_correction_decision_assert_contains(
    'top_management',
    $authorizationSource,
    'SIM approver top-management position must be eligible for correction decisions'
);

echo "qms_record_correction_decision_smoke passed\n";
