<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

function assert_contains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Missing: ' . $needle . PHP_EOL);
        exit(1);
    }
}

function assert_file_contains(string $path, string $needle, string $message): void
{
    if (!is_file($path)) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Missing file: ' . $path . PHP_EOL);
        exit(1);
    }

    assert_contains($needle, file_get_contents($path) ?: '', $message);
}

$root = dirname(__DIR__);

$schemaSql = file_get_contents($root . '/database/jewelry_qms.sql') ?: '';
assert_contains('`review_status`', $schemaSql, 'Template schema includes review status');
assert_contains("enum('pending','field_confirmed','needs_fidelity','deferred','completed')", $schemaSql, 'Review status uses controlled states');
assert_contains('`review_note` text', $schemaSql, 'Template schema includes review note');
assert_contains('`source_file_sha1` char(40)', $schemaSql, 'Template schema stores source attachment hash');
assert_contains('KEY `review_status` (`review_status`)', $schemaSql, 'Template schema indexes review status');
assert_contains('KEY `source_file_sha1` (`source_file_sha1`)', $schemaSql, 'Template schema indexes source attachment hash');

$migration = $root . '/database/migrations/20260523_record_form_template_review.sql';
assert_file_contains($migration, 'information_schema.COLUMNS', 'Review migration is idempotent');
assert_file_contains($migration, 'ADD COLUMN `review_status`', 'Review migration adds status column');
assert_file_contains($migration, 'ADD COLUMN `review_note`', 'Review migration adds note column');

$sourceHashMigration = $root . '/database/migrations/20260523_record_form_template_source_hash.sql';
assert_file_contains($sourceHashMigration, 'ADD COLUMN `source_file_sha1`', 'Source hash migration adds hash column');
assert_file_contains($sourceHashMigration, 'ADD KEY `source_file_sha1`', 'Source hash migration indexes hash column');

$routeSource = file_get_contents($root . '/route/app.php') ?: '';
assert_contains("record_form_template/review", $routeSource, 'Routes expose template review list');
assert_contains("record_form_template/updateReview", $routeSource, 'Routes expose template review update');
assert_contains("record_form_template/sourcePreview", $routeSource, 'Routes expose source attachment inline preview');

$controllerSource = file_get_contents($root . '/app/controller/RecordFormTemplate.php') ?: '';
assert_contains('public function review', $controllerSource, 'Template controller has review action');
assert_contains('public function updateReview', $controllerSource, 'Template controller can update review state');
assert_contains('public function sourcePreview', $controllerSource, 'Template controller can preview source attachments');
assert_contains('reviewStatusOptions', $controllerSource, 'Template controller centralizes review status labels');
assert_contains('field_count', $controllerSource, 'Template review rows include field counts');
assert_contains('repeatable_count', $controllerSource, 'Template review rows include repeatable table counts');
assert_contains('isTemplateFillable', $controllerSource, 'Template list gates fill links on completed high-fidelity templates');
assert_contains('generic_record_form', $controllerSource, 'Template list refuses generic print templates as fillable');

$reviewView = $root . '/app/view/record_form_template/review.html';
assert_file_contains($reviewView, '模板复核清单', 'Review view has clear title');
assert_file_contains($reviewView, '复核状态', 'Review view shows review status');
assert_file_contains($reviewView, '字段数', 'Review view shows field count');
assert_file_contains($reviewView, '动态明细', 'Review view shows repeatable table count');
assert_file_contains($reviewView, '原始附件', 'Review view links source attachment');
assert_file_contains($reviewView, '原始预览', 'Review view links source attachment preview');
assert_file_contains($reviewView, 'data-review-status', 'Review view marks update controls');
assert_file_contains($reviewView, '待完成高保真复核后可试填', 'Review view disables trial filling for draft rebuild templates');

$indexView = file_get_contents($root . '/app/view/record_form_template/index.html') ?: '';
assert_contains('复核清单', $indexView, 'Template index links to review list');
assert_contains('item.fillable', $indexView, 'Template index only exposes fill action for fillable templates');

$createView = file_get_contents($root . '/app/view/record_form_instance/create.html') ?: '';
$editView = file_get_contents($root . '/app/view/record_form_instance/edit.html') ?: '';
foreach (['create' => $createView, 'edit' => $editView] as $viewName => $viewSource) {
    assert_contains("\$field.type == 'person'", $viewSource, $viewName . ' view renders top-level person fields as selectable controls');
    assert_contains("\$field.type == 'department'", $viewSource, $viewName . ' view renders top-level department fields as selectable controls');
    assert_contains("\$column.type == 'person'", $viewSource, $viewName . ' view renders repeatable person columns as selectable controls');
    assert_contains("\$column.type == 'department'", $viewSource, $viewName . ' view renders repeatable department columns as selectable controls');
    assert_contains('employeeOptions', $viewSource, $viewName . ' view consumes employee options');
    assert_contains('departmentOptions', $viewSource, $viewName . ' view consumes department options');
    assert_contains('从人员台账勾选添加', $viewSource, $viewName . ' view uses generic personnel picker wording');
}

$instanceController = file_get_contents($root . '/app/controller/RecordFormInstance.php') ?: '';
assert_contains('departmentOptions', $instanceController, 'Record editor exposes department options');
assert_contains('firstPersonColumn', $instanceController, 'Record editor can attach picker to any person column');
assert_contains('firstDepartmentColumn', $instanceController, 'Record editor detects department columns for picker fill');

$rbacSource = file_get_contents($root . '/app/middleware/Rbac.php') ?: '';
assert_contains('updatereview', $rbacSource, 'RBAC treats review update as a write action');

$auditSource = file_get_contents($root . '/app/middleware/AuditLog.php') ?: '';
assert_contains('updatereview', $auditSource, 'Audit log tracks template review updates');

echo "record_forms_template_review_smoke passed\n";
