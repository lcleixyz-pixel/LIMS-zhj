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
$printViewSource = file_get_contents($root . '/app/view/record_form_instance/correction_print.html') ?: '';
$authorizationSource = file_get_contents($root . '/app/service/ActionAuthorizationService.php') ?: '';
$migrationSource = file_get_contents($root . '/database/migrations/20260725_record_form_corrections.sql') ?: '';
$fieldTargetMigrationSource = file_get_contents($root . '/database/migrations/20260726_record_form_correction_field_targets.sql') ?: '';

qms_correction_decision_assert_contains(
    "Route::post('record_form_instance/decideCorrection'",
    $routeSource,
    'Record correction decision must be a POST action'
);
qms_correction_decision_assert_contains(
    "Route::post('record_form_instance/registerCorrection'",
    $routeSource,
    'Approved record correction content registration must be a POST action'
);
qms_correction_decision_assert_contains(
    "Route::get('record_form_instance/printCorrections'",
    $routeSource,
    'Record correction appendix must have a printable page'
);
qms_correction_decision_assert_contains(
    'decideCorrection',
    $controllerSource,
    'Record correction controller must expose a decision action'
);
qms_correction_decision_assert_contains(
    'registerCorrection',
    $controllerSource,
    'Record correction controller must expose append-only correction registration'
);
qms_correction_decision_assert_contains(
    'RecordFormCorrectionService::prepare',
    $controllerSource,
    'New correction requests must be validated against the frozen field schema and values'
);
qms_correction_decision_assert_contains(
    "Db::name('record_form_correction_requests')",
    $controllerSource,
    'Structured correction requests must be persisted outside transient notifications'
);
qms_correction_decision_assert_contains(
    'appendApprovedCorrection',
    $controllerSource,
    'Approving a structured request must automatically append the correction entry'
);
qms_correction_decision_assert_contains(
    'Db::transaction',
    $controllerSource,
    'Decision state and append-only correction entry must be committed atomically'
);
qms_correction_decision_assert_contains(
    'printCorrections',
    $controllerSource,
    'Record correction controller must expose correction appendix printing'
);
qms_correction_decision_assert_contains(
    'correctionDecisionsFor',
    $controllerSource,
    'Record detail must load correction decision history'
);
qms_correction_decision_assert_contains(
    'recordCorrectionsFor',
    $controllerSource,
    'Record detail must load append-only correction entries'
);
qms_correction_decision_assert_contains(
    'approvedCorrectionRequestsFor',
    $controllerSource,
    'Record detail must only allow registration against approved correction requests'
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
    '更正记录链',
    $viewSource,
    'Record detail must show append-only correction chain'
);
qms_correction_decision_assert_contains(
    '历史自由文本申请补录',
    $viewSource,
    'Record detail must keep a clearly labelled compatibility entry for pre-field-level approvals'
);
qms_correction_decision_assert_contains(
    '打印更正说明页',
    $viewSource,
    'Record detail must offer a printable correction appendix'
);
qms_correction_decision_assert_contains(
    '更正位置',
    $viewSource,
    'New correction requests must select a concrete field or table location'
);
qms_correction_decision_assert_contains(
    '系统读取的原值',
    $viewSource,
    'Original values must be read-only server-side values instead of user transcription'
);
qms_correction_decision_assert_contains(
    '新增一行',
    $viewSource,
    'Repeatable tables must offer structured whole-row append'
);
qms_correction_decision_assert_contains(
    '批准后系统会自动追加',
    $viewSource,
    'The UI must explain that structured approvals no longer require duplicate registration'
);
qms_correction_decision_assert_contains(
    '字段位置',
    $printViewSource,
    'Printable correction appendix must identify the corrected field location'
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
    "'recordforminstance.registercorrection'",
    $authorizationSource,
    'Record correction registration must be wired into action authorization'
);
qms_correction_decision_assert_contains(
    'top_management',
    $authorizationSource,
    'SIM approver top-management position must be eligible for correction decisions'
);
qms_correction_decision_assert_contains(
    'CREATE TABLE IF NOT EXISTS `record_form_corrections`',
    $migrationSource,
    'Record correction append-only table must be declared in migration'
);
qms_correction_decision_assert_contains(
    '`id` varchar(40)',
    $migrationSource,
    'Correction table ids must fit SIM-prefixed UUIDs in trial mode'
);
qms_correction_decision_assert_contains(
    '`original_content` text',
    $migrationSource,
    'Correction table must preserve original content'
);
qms_correction_decision_assert_contains(
    '`corrected_content` text',
    $migrationSource,
    'Correction table must preserve corrected or supplemental content'
);
qms_correction_decision_assert_contains(
    '`registered_at` datetime',
    $migrationSource,
    'Correction table must record correction registration time'
);
qms_correction_decision_assert_contains(
    'CREATE TABLE IF NOT EXISTS `record_form_correction_requests`',
    $fieldTargetMigrationSource,
    'Structured field correction requests must have a durable table'
);
qms_correction_decision_assert_contains(
    '`target_kind` varchar(30)',
    $fieldTargetMigrationSource,
    'Correction requests and entries must distinguish field, cell, row and legacy targets'
);
qms_correction_decision_assert_contains(
    '`field_path` varchar(255)',
    $fieldTargetMigrationSource,
    'Correction requests and entries must retain the stable field path'
);
qms_correction_decision_assert_contains(
    '`field_label` varchar(255)',
    $fieldTargetMigrationSource,
    'Correction requests and entries must retain a human-readable field label snapshot'
);
qms_correction_decision_assert_contains(
    '`row_payload_json` longtext',
    $fieldTargetMigrationSource,
    'Append-row corrections must retain structured row values'
);

echo "qms_record_correction_decision_smoke passed\n";
