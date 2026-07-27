<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\service\G2ExpansionBatch2BlueprintService;
use app\service\RecordFormPrintService;

function g2b2_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function g2b2_contains(string $needle, string $haystack, string $message): void
{
    g2b2_assert(str_contains($haystack, $needle), $message . ' (missing: ' . $needle . ')');
}

$templates = G2ExpansionBatch2BlueprintService::templates();
$docNumbers = array_column($templates, 'doc_number');
sort($docNumbers);
g2b2_assert($docNumbers === [
    'XZTC/BG-02-01',
    'XZTC/BG-03-02',
    'XZTC/BG-03-03',
    'XZTC/BG-04-03',
    'XZTC/BG-04-05',
    'XZTC/BG-04-06',
    'XZTC/BG-35-01',
    'XZTC/BG-35-02',
], 'G2 expansion batch2 exposes exactly candidate eight tables');
foreach (['XZTC/BG-04-01', 'XZTC/BG-04-02', 'XZTC/BG-04-04', 'XZTC/BG-03-09', 'XZTC/BG-35-03'] as $excluded) {
    g2b2_assert(!in_array($excluded, $docNumbers, true), $excluded . ' is not in expansion batch2');
}

$byNumber = [];
foreach ($templates as $template) {
    $byNumber[(string)$template['doc_number']] = $template;
    g2b2_assert(($template['version'] ?? '') === 'A/0', $template['doc_number'] . ' starts at A/0');
    g2b2_assert(($template['retention'] ?? '') === '不少于6年', $template['doc_number'] . ' declares retention');
    g2b2_assert(($template['status'] ?? '') === 'candidate_pending_human_review', $template['doc_number'] . ' remains candidate pending human review');
    g2b2_assert(($template['print_template_key'] ?? '') === G2ExpansionBatch2BlueprintService::PRINT_TEMPLATE_KEY, $template['doc_number'] . ' uses expansion batch2 print template');
}

foreach (['XZTC/BG-04-03', 'XZTC/BG-04-05', 'XZTC/BG-04-06'] as $docNumber) {
    g2b2_contains('母版化', (string)($byNumber[$docNumber]['master_note'] ?? ''), $docNumber . ' carries masterization note');
}
g2b2_contains('编号迁移仍按阻断维持不动', (string)($byNumber['XZTC/BG-35-01']['blocking_note'] ?? ''), 'BG-35-01 keeps UF-09 blocking note');
g2b2_contains('领用记录', (string)($byNumber['XZTC/BG-35-02']['alias_note'] ?? ''), 'BG-35-02 carries alias note');
g2b2_contains('XZTC-ZW01', (string)($byNumber['XZTC/BG-03-02']['migration_note'] ?? ''), 'BG-03-02 carries stray-number archive note');

$environmentSchema = array_column($byNumber['XZTC/BG-02-01']['field_schema'], null, 'key');
g2b2_assert(($environmentSchema['monitor_month']['type'] ?? '') === 'month', 'BG-02-01 monitor month uses month input');
g2b2_assert(($environmentSchema['monitor_month']['required'] ?? false) === true, 'BG-02-01 monitor month is required');
g2b2_assert(($environmentSchema['monitor_site']['required'] ?? false) === true, 'BG-02-01 monitor site is required');
g2b2_assert(($environmentSchema['daily_monitor_items']['required'] ?? false) === true, 'BG-02-01 requires at least one daily row');
$dailyColumns = array_column($environmentSchema['daily_monitor_items']['columns'] ?? [], null, 'key');
g2b2_assert(($dailyColumns['date']['type'] ?? '') === 'date', 'BG-02-01 daily date uses date input');
g2b2_assert(($dailyColumns['temperature']['type'] ?? '') === 'number', 'BG-02-01 temperature uses number input');
g2b2_assert(($dailyColumns['temperature']['unit'] ?? '') === '℃', 'BG-02-01 temperature displays Celsius');
g2b2_assert(($dailyColumns['humidity']['type'] ?? '') === 'number', 'BG-02-01 humidity uses number input');
g2b2_assert(($dailyColumns['humidity']['unit'] ?? '') === '%RH', 'BG-02-01 humidity displays RH unit');
g2b2_assert(($dailyColumns['conformity_judgment']['type'] ?? '') === 'select', 'BG-02-01 conformity uses select input');
g2b2_assert(
    ($dailyColumns['conformity_judgment']['options'] ?? []) === ['符合', '不符合'],
    'BG-02-01 conformity offers clear options'
);
g2b2_assert(($dailyColumns['recorder']['type'] ?? '') === 'person', 'BG-02-01 recorder uses personnel picker');
g2b2_assert(
    (($dailyColumns['exception_disposal']['validation']['required_when']['field'] ?? '') === 'conformity_judgment'),
    'BG-02-01 requires disposal when conformity changes'
);

$needles = [
    'XZTC/BG-02-01' => ['月度逐日监控网格', '环境控制要求', '逐日实测', '符合性判定', '异常处置', '℃', '%RH'],
    'XZTC/BG-03-02' => ['关联样品/委托编号'],
    'XZTC/BG-03-03' => ['依据保养计划/周期'],
    'XZTC/BG-04-03' => ['空白母版', '核查依据', '标准值', '实测值', '允差', '结论判定', '由设备档案带出'],
    'XZTC/BG-04-05' => ['空白母版', '偏光镜', '二色镜', '其他小型光学仪器', '勾选式设计'],
    'XZTC/BG-04-06' => ['空白母版', '核查结论对设备状态的处置意见', '继续使用', '限用', '停用', '联动03-06/03-07说明'],
    'XZTC/BG-35-01' => ['标准物质台账', '当前状态(在用/停用/报废)', '证书号', '有效期', '只正字，不迁号'],
    'XZTC/BG-35-02' => ['登记别名', '领用记录', '使用前状态', '使用后状态'],
];

foreach ($templates as $template) {
    foreach (['wulumuqi', 'hetian'] as $site) {
        $values = G2ExpansionBatch2BlueprintService::sampleValues((string)$template['doc_number'], $site);
        $html = RecordFormPrintService::render((string)$template['print_template_key'], $template, $values);
        g2b2_contains('候选待人审', $html, $template['doc_number'] . ' renders candidate status');
        g2b2_contains('版次：A/0', $html, $template['doc_number'] . ' renders version');
        g2b2_contains('保存期限：不少于6年', $html, $template['doc_number'] . ' renders retention');
        g2b2_contains((string)$template['name'], $html, $template['doc_number'] . ' renders name');
        if ($site === 'hetian') {
            g2b2_contains(str_replace('XZTC/BG-', 'XZTCH-BG-', (string)$template['doc_number']), $html, $template['doc_number'] . ' renders Hetian display number');
            g2b2_contains('母版：' . (string)$template['doc_number'], $html, $template['doc_number'] . ' renders master number');
        } else {
            g2b2_contains((string)$template['doc_number'], $html, $template['doc_number'] . ' renders Wulumuqi number');
        }
        foreach ($needles[(string)$template['doc_number']] as $needle) {
            g2b2_contains($needle, $html, $template['doc_number'] . ' renders blueprint field');
        }
    }
}

echo "g2_expansion_batch2_blueprint_smoke passed\n";
