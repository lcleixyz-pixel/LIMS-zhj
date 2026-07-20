<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\service\QmsElementService;
use think\facade\Config;
use think\facade\Db;

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function assert_contains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Missing: ' . $needle . PHP_EOL);
        exit(1);
    }
}

function matrix_row_for_element(array $rows, string $elementId): ?array
{
    foreach ($rows as $row) {
        if ((string)($row['element']->id ?? '') === $elementId) {
            return $row;
        }
    }

    return null;
}

function detail_module_by_code(array $modules, string $code): ?array
{
    foreach ($modules as $module) {
        if ((string)($module['code'] ?? '') === $code) {
            return $module;
        }
    }

    return null;
}

QmsElementService::seedAll();

$root = dirname(__DIR__);
$route = (string)file_get_contents($root . '/route/app.php');
$controller = (string)file_get_contents($root . '/app/controller/PlanningElement.php');
$view = (string)file_get_contents($root . '/app/view/planning_element/view.html');
$service = (string)file_get_contents($root . '/app/service/QmsElementService.php');

assert_true(method_exists(QmsElementService::class, 'mapBusinessModuleToElement'), 'Service exposes human-reviewed business module mapping');
assert_true(method_exists(QmsElementService::class, 'businessModuleOptionsForElement'), 'Service exposes business module options for element detail');
assert_contains('planning/elements/modules/map', $route, 'Route exposes element business module mapping action');
assert_contains('mapBusinessModule', $controller, 'PlanningElement controller handles business module mapping');
assert_contains('补充运行模块', $view, 'Element detail page exposes supplemental business module mapping form');
assert_contains('businessModuleOptions', $view . $controller, 'Element detail page receives module options');
assert_contains('主归属', $view . $service, 'Module relation labels include primary ownership');
assert_contains('补充映射', $view . $service, 'Module relation labels include supplemental mapping');

$module = Db::name('qms_business_modules')
    ->where('code', 'record_form_templates')
    ->where('soft_delete', 0)
    ->find();
$primaryElementId = (string)($module['primary_element_id'] ?? '');
$supportingElementKey = 'smoke_business_module_mapping';
$supportingElementId = qms_uuid();
$moduleId = (string)($module['id'] ?? '');

assert_true($moduleId !== '', 'Record form template module exists');
assert_true($primaryElementId !== '', 'Record form template module keeps a primary element');
assert_true($primaryElementId !== $supportingElementId, 'Smoke uses a distinct supplemental element');

$note = '运行模块补充映射 smoke：记录模板模块同时支撑人员培训记录要求';

try {
    Db::name('qms_business_module_elements')
        ->where('module_id', $moduleId)
        ->where('element_id', $supportingElementId)
        ->delete();
    Db::name('qms_elements')->where('key', $supportingElementKey)->delete();
    Db::name('qms_elements')->insert([
        'id' => $supportingElementId,
        'company_id' => (string)Config::get('qms.company_id'),
        'key' => $supportingElementKey,
        'name' => '运行模块补充映射 smoke 要素',
        'element_type' => 'management',
        'applicability' => 'applicable',
        'summary' => '用于验证运行模块可补充映射到非主归属要素。',
        'status' => 'draft',
        'sort_order' => 9998,
        'publish' => 1,
        'soft_delete' => 0,
        'created' => date('Y-m-d H:i:s'),
        'modified' => date('Y-m-d H:i:s'),
    ]);

    $beforeRow = matrix_row_for_element(QmsElementService::traceabilityMatrix(), $supportingElementId);
    $beforeModuleCount = (int)($beforeRow['module_count'] ?? 0);

    $mapped = QmsElementService::mapBusinessModuleToElement($moduleId, $supportingElementId, 'supporting', $note);

    assert_true((string)$mapped['module_id'] === $moduleId, 'Mapping result keeps module id');
    assert_true((string)$mapped['element_id'] === $supportingElementId, 'Mapping result keeps element id');
    assert_true((string)$mapped['relation_type'] === 'supporting', 'Mapping result marks supplemental relation');
    assert_true(str_contains((string)($mapped['note'] ?? ''), 'smoke'), 'Mapping result keeps review note');

    $moduleAfter = Db::name('qms_business_modules')->where('id', $moduleId)->find();
    assert_true((string)$moduleAfter['primary_element_id'] === $primaryElementId, 'Supplemental mapping does not change module primary element');

    $primaryLink = Db::name('qms_business_module_elements')
        ->where('module_id', $moduleId)
        ->where('element_id', $primaryElementId)
        ->where('soft_delete', 0)
        ->find();
    assert_true((string)($primaryLink['relation_type'] ?? '') === 'primary', 'Original primary module mapping remains primary');

    $supportingLink = Db::name('qms_business_module_elements')
        ->where('module_id', $moduleId)
        ->where('element_id', $supportingElementId)
        ->where('soft_delete', 0)
        ->find();
    assert_true((string)($supportingLink['relation_type'] ?? '') === 'supporting', 'Supplemental module mapping is persisted');

    $supportingDetail = QmsElementService::elementDetail($supportingElementId);
    $supportingModule = detail_module_by_code($supportingDetail['business_modules'] ?? [], 'record_form_templates');
    assert_true(is_array($supportingModule), 'Supporting element detail includes supplemental module');
    assert_true((string)$supportingModule['relation_type'] === 'supporting', 'Supporting element detail keeps supplemental relation');

    $primaryDetail = QmsElementService::elementDetail($primaryElementId);
    $primaryModule = detail_module_by_code($primaryDetail['business_modules'] ?? [], 'record_form_templates');
    assert_true(is_array($primaryModule), 'Primary element detail still includes primary module');
    assert_true((string)$primaryModule['relation_type'] === 'primary', 'Primary element detail keeps primary relation');

    $afterRow = matrix_row_for_element(QmsElementService::traceabilityMatrix(), $supportingElementId);
    assert_true((int)($afterRow['module_count'] ?? 0) === $beforeModuleCount + 1, 'Traceability matrix counts supplemental business module mapping');
} finally {
    Db::name('qms_business_module_elements')
        ->where('module_id', $moduleId)
        ->where('element_id', $supportingElementId)
        ->delete();
    Db::name('qms_elements')->where('id', $supportingElementId)->delete();
}

echo "qms_business_module_mapping_smoke passed\n";
