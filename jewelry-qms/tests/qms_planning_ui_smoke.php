<?php
declare(strict_types=1);

$root = dirname(__DIR__);

function assert_contains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Missing: ' . $needle . PHP_EOL);
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

$route = (string)file_get_contents($root . '/route/app.php');
$config = (string)file_get_contents($root . '/config/qms.php');
$layout = (string)file_get_contents($root . '/app/view/layout/main.html');
$dashboard = (string)file_get_contents($root . '/app/controller/PlanningDashboard.php');
$element = (string)file_get_contents($root . '/app/controller/PlanningElement.php');
$source = (string)file_get_contents($root . '/app/controller/PlanningSource.php');
$clause = (string)file_get_contents($root . '/app/controller/PlanningClause.php');
$structure = (string)file_get_contents($root . '/app/controller/PlanningStructure.php');
$traceability = (string)file_get_contents($root . '/app/controller/PlanningTraceability.php');
$objective = (string)file_get_contents($root . '/app/controller/PlanningObjective.php');
$structureView = (string)file_get_contents($root . '/app/view/planning_structure/view.html');
$service = (string)file_get_contents($root . '/app/service/QmsElementService.php');
$structureService = (string)file_get_contents($root . '/app/service/QmsDocumentStructureService.php');

foreach ([
    'planning/index',
    'planning/elements',
    'planning/elements/view',
    'planning/elements/edit',
    'planning/elements/modules/map',
    'planning/elements/seed',
    'planning/sources',
    'planning/sources/extract-clauses',
    'planning/clauses',
    'planning/clauses/view',
    'planning/structures',
    'planning/structures/view',
    'planning/structures/seed',
    'planning/traceability',
    'planning/objectives',
] as $path) {
    assert_contains($path, $route, 'Route exposes new planning path ' . $path);
}

foreach ([
    '/planning/index' => '策划中心',
    '/planning/elements' => '要素管理',
    '/planning/sources' => '外部依据',
    '/planning/clauses' => '条款库',
    '/planning/structures' => '文件结构化',
    '/planning/traceability' => '追溯矩阵',
    '/planning/objectives' => '质量方针目标',
] as $path => $label) {
    assert_contains($path, $layout, 'Navigation exposes ' . $path);
    assert_contains($label, $layout, 'Navigation labels ' . $label);
}

foreach ([
    'planningdashboard',
    'planningelement',
    'planningsource',
    'planningclause',
    'planningstructure',
    'planningtraceability',
    'planningobjective',
] as $permission) {
    assert_contains($permission, $config, 'Quality manager permissions include ' . $permission);
}

foreach ([
    'QmsElementService::coverageStats',
    'QmsElementService::elementDetail',
    'QmsElementService::traceabilityMatrix',
    'QmsElementService::seedAll',
    'QmsElementService::upsertExternalClauses',
    'QmsDocumentStructureService::seedAll',
    'QmsDocumentStructureService::structuredDocumentRows',
] as $needle) {
    assert_contains($needle, $dashboard . $element . $source . $clause . $structure . $traceability . $service . $structureService, 'New planning flow uses ' . $needle);
}

foreach ([
    '要素名称',
    '外部条款',
    '手册章节',
    '程序文件',
    '记录表格',
    '运行模块',
    '岗位职责',
    '智能体建议',
] as $label) {
    assert_contains($label, $element . $traceability . $service . $structure, 'Element UI/service exposes ' . $label);
}

foreach ([
    '原始文件',
    'Markdown结构',
    '内容块',
    '块级追溯',
    '渲染输出',
] as $label) {
    assert_contains($label, $structure . $structureService, 'Structure UI exposes ' . $label);
}

assert_contains('运行模块', $structureView, 'Structure detail exposes running module links');
assert_contains('module_name', $structureView, 'Structure detail renders linked running module names');
assert_contains('说明', $structureView, 'Structure detail exposes link evidence notes');
assert_contains('link.note', $structureView, 'Structure detail renders link evidence notes');
assert_contains('business_module_id', $structureService, 'Structure service writes block-level running module links');
assert_contains('module_code', $structureService, 'Structure service reads block-level running module codes for trace display');
assert_contains('record_form_instances', $structureService, 'Record form schema blocks link to runtime evidence module');

foreach ([
    'Planning/sources',
    'planning/import-batches',
    'createSourceCandidate',
    'uploadSourceCandidate',
    'publishCandidateBatch',
    'QmsImportBatch',
    'QmsImportCandidate',
    'QmsRequirementElement',
    'QmsTraceLink',
    'QmsDocumentSection',
    'QmsResponsibilityMatrix',
] as $legacyNeedle) {
    assert_not_contains($legacyNeedle, $route . $layout . $dashboard . $element . $source . $clause . $structure . $traceability . $objective, 'New planning UI removes legacy candidate or numbered-element flow');
}

echo "qms_planning_ui_smoke passed\n";
