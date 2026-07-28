<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\service\G2ExpansionBatch3BlueprintService;
use app\service\RecordFormFixtureService;

function internal_picker_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function internal_picker_contains(string $needle, string $haystack, string $message): void
{
    internal_picker_assert(str_contains($haystack, $needle), $message . ' (missing: ' . $needle . ')');
}

$templates = G2ExpansionBatch3BlueprintService::templates();
$trainingPlan = null;
foreach ($templates as $template) {
    if (($template['doc_number'] ?? '') === 'XZTC/BG-01-01') {
        $trainingPlan = $template;
        break;
    }
}
internal_picker_assert(is_array($trainingPlan), 'Annual training plan blueprint exists');

$columns = [];
foreach (($trainingPlan['field_schema'][0]['columns'] ?? []) as $column) {
    $columns[(string)$column['label']] = $column;
}

internal_picker_assert(($columns['培训时间']['type'] ?? '') === 'month', 'Training time uses a month picker');
internal_picker_assert(($columns['培训对象']['type'] ?? '') === 'person', 'Training target uses the personnel roster');
internal_picker_assert(($columns['培训对象']['multiple'] ?? false) === true, 'Training target supports direct multiple checkboxes');
internal_picker_assert(($columns['培训部门']['type'] ?? '') === 'department', 'Training department uses the department roster');
internal_picker_assert(($columns['培训部门']['multiple'] ?? false) === true, 'Training department supports direct multiple checkboxes');

$fixtureTrainingPlan = null;
$fixtureTrainingRecord = null;
foreach (RecordFormFixtureService::templates() as $template) {
    if (($template['doc_number'] ?? '') === 'XZTC/BG-01-01') {
        $fixtureTrainingPlan = $template;
    }
    if (($template['doc_number'] ?? '') === 'XZTC/BG-01-02') {
        $fixtureTrainingRecord = $template;
    }
}
internal_picker_assert(is_array($fixtureTrainingPlan), 'Personnel fixture keeps the annual training plan');
internal_picker_assert(is_array($fixtureTrainingRecord), 'Personnel fixture keeps the training record');

$fixtureColumns = [];
foreach (($fixtureTrainingPlan['field_schema'][1]['columns'] ?? []) as $column) {
    $fixtureColumns[(string)$column['label']] = $column;
}
internal_picker_assert(($fixtureColumns['培训时间']['type'] ?? '') === 'month', 'Canonical personnel fixture uses a month picker');
internal_picker_assert(($fixtureColumns['培训对象']['type'] ?? '') === 'person', 'Canonical personnel fixture uses personnel choices');
internal_picker_assert(($fixtureColumns['培训对象']['multiple'] ?? false) === true, 'Canonical personnel fixture allows multiple trainees');
internal_picker_assert(($fixtureColumns['培训部门']['multiple'] ?? false) === true, 'Canonical personnel fixture allows multiple departments');

$attendeePosition = null;
foreach (($fixtureTrainingRecord['field_schema'] ?? []) as $field) {
    if (($field['key'] ?? '') !== 'attendees') {
        continue;
    }
    foreach (($field['columns'] ?? []) as $column) {
        if (($column['key'] ?? '') === 'position') {
            $attendeePosition = $column;
        }
    }
}
internal_picker_assert(($attendeePosition['type'] ?? '') === 'position', 'Training record attendee position uses governed position choices');

$controller = file_get_contents(__DIR__ . '/../app/controller/RecordFormInstance.php');
$createView = file_get_contents(__DIR__ . '/../app/view/record_form_instance/create.html');
$editView = file_get_contents(__DIR__ . '/../app/view/record_form_instance/edit.html');
$templateView = file_get_contents(__DIR__ . '/../app/view/record_form_template/view.html');
$editorScript = file_get_contents(__DIR__ . '/../public/static/js/record-form-editor.js');

internal_picker_contains("View::assign('positionOptions'", $controller, 'Editor receives governed position options');
internal_picker_contains('private function positionOptions()', $controller, 'Controller exposes active governed positions');
internal_picker_contains("&& !(\$column['multiple'] ?? false)", $controller, 'Multi-person cells do not also create one whole row per person');

foreach ([$createView, $editView] as $viewSource) {
    internal_picker_contains("\$field.type == 'position'", $viewSource, 'Top-level position fields render as governed choices');
    internal_picker_contains("\$column.type == 'position'", $viewSource, 'Repeatable position columns render as governed choices');
    internal_picker_contains('data-multi-picker-option', $viewSource, 'Multiple internal choices render as direct checkboxes');
    internal_picker_contains('data-fill-current-period', $viewSource, 'Date and month controls provide a Chinese quick-fill action');
}

internal_picker_contains('[data-multi-picker-option]', $editorScript, 'Editor synchronizes direct checkbox choices');
internal_picker_contains('[data-fill-current-period]', $editorScript, 'Editor fills today or the current month on request');
internal_picker_contains('{if !empty($column.multiple)}（多选）{/if}', $templateView, 'Template details identify multiple-choice internal columns');

echo "qms_record_form_internal_picker_smoke passed\n";
