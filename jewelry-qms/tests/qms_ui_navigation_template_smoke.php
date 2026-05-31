<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
require $root . '/app/common.php';

$app = new think\App();
$app->initialize();

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function assert_not_contains(string $needle, string $haystack, string $message): void
{
    if (str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Unexpected: ' . $needle . PHP_EOL);
        exit(1);
    }
}

$templates = [
    'app/view/capa/add.html',
    'app/view/audit_finding/add.html',
    'app/view/audit_checklist/add.html',
    'app/view/nonconformity/add.html',
];

foreach ($templates as $relativePath) {
    $content = (string)file_get_contents($root . '/' . $relativePath);
    assert_true(
        preg_match('/\{volist\s+name="[^"]+"\s+[^}]*key="/', $content) !== 1,
        $relativePath . ' must not use volist key on associative option arrays'
    );
}

$calibrationAdd = (string)file_get_contents($root . '/app/view/calibration/add.html');
assert_not_contains(
    "{if \$form.result|default='pass'",
    $calibrationAdd,
    'Calibration add template must not use default filter inside if expressions'
);
assert_not_contains(
    '$form.equipment_id',
    $calibrationAdd,
    'Calibration add template must not read missing form.equipment_id directly'
);
assert_true(
    str_contains($calibrationAdd, '$selectedEquipmentId'),
    'Calibration add template should compare against a precomputed selected equipment value'
);
assert_true(
    str_contains($calibrationAdd, '$selectedResult'),
    'Calibration add template should compare against a precomputed selected result value'
);

$recordFormReview = (string)file_get_contents($root . '/app/view/record_form_template/review.html');
$recordFormView = (string)file_get_contents($root . '/app/view/record_form_template/view.html');
$recordFormController = (string)file_get_contents($root . '/app/controller/RecordFormTemplate.php');
$crudBase = (string)file_get_contents($root . '/app/controller/CrudBase.php');
assert_true(
    str_contains($recordFormReview . $recordFormView, 'source_file_available'),
    'Record form template pages should show source links only when the physical source file exists'
);
assert_true(
    str_contains($recordFormController, 'sourceFileAvailable'),
    'RecordFormTemplate controller should compute physical source-file availability'
);
assert_true(
    str_contains($crudBase, 'buildViewFields') && str_contains($crudBase, "View::assign('fields'"),
    'CrudBase view should assign generic fields for shared detail templates'
);

foreach (\app\service\QmsElementService::openRecordSchemaSuggestions(50) as $suggestion) {
    $url = (string)($suggestion['record_form_edit_url'] ?? '');
    if ($url === '') {
        continue;
    }
    parse_str((string)parse_url($url, PHP_URL_QUERY), $query);
    $templateId = (string)($query['id'] ?? '');
    $blockId = (string)($query['schema_draft_block_id'] ?? '');
    assert_true(
        $templateId !== '' && \think\facade\Db::name('record_form_templates')
            ->where('id', $templateId)
            ->where('soft_delete', 0)
            ->count() === 1,
        'Record schema suggestion edit links must not point to missing record form templates'
    );
    assert_true(
        $blockId !== '' && \think\facade\Db::name('qms_document_blocks')
            ->where('id', $blockId)
            ->where('soft_delete', 0)
            ->count() === 1,
        'Record schema suggestion edit links must not point to missing schema draft blocks'
    );
}

echo "qms_ui_navigation_template_smoke passed\n";
