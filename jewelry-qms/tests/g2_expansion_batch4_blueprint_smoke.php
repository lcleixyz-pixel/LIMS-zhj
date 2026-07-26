<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\service\G2ExpansionBatch4BlueprintService;
use app\service\RecordFormPrintService;

function g2b4_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function g2b4_contains(string $needle, string $haystack, string $message): void
{
    g2b4_assert(str_contains($haystack, $needle), $message . ' (missing: ' . $needle . ')');
}

$templates = G2ExpansionBatch4BlueprintService::templates();
$docNumbers = array_column($templates, 'doc_number');
sort($docNumbers);
$expected = [
    'XZTC/BG-02-02',
    'XZTC/BG-03-09',
    'XZTC/BG-04-01', 'XZTC/BG-04-02', 'XZTC/BG-04-04',
    'XZTC/BG-05-01', 'XZTC/BG-05-02',
    'XZTC/BG-06-01',
    'XZTC/BG-07-01',
    'XZTC/BG-08-01', 'XZTC/BG-08-03', 'XZTC/BG-08-04', 'XZTC/BG-08-05', 'XZTC/BG-08-06', 'XZTC/BG-08-07', 'XZTC/BG-08-08', 'XZTC/BG-08-09',
    'XZTC/BG-09-04',
    'XZTC/BG-11-01', 'XZTC/BG-11-02', 'XZTC/BG-11-03', 'XZTC/BG-11-04', 'XZTC/BG-11-05',
    'XZTC/BG-12-01', 'XZTC/BG-12-02',
    'XZTC/BG-13-01',
    'XZTC/BG-14-01', 'XZTC/BG-14-02',
    'XZTC/BG-15-01',
    'XZTC/BG-16-01',
    'XZTC/BG-17-01',
    'XZTC/BG-19-01', 'XZTC/BG-19-02', 'XZTC/BG-19-03', 'XZTC/BG-19-04',
    'XZTC/BG-20-01', 'XZTC/BG-20-02', 'XZTC/BG-20-03', 'XZTC/BG-20-04', 'XZTC/BG-20-05', 'XZTC/BG-20-06', 'XZTC/BG-20-07', 'XZTC/BG-20-08',
    'XZTC/BG-21-01', 'XZTC/BG-21-02', 'XZTC/BG-21-03', 'XZTC/BG-21-04',
    'XZTC/BG-23-01',
    'XZTC/BG-24-01', 'XZTC/BG-24-02',
    'XZTC/BG-26-01', 'XZTC/BG-26-02',
    'XZTC/BG-29-01', 'XZTC/BG-29-02',
    'XZTC/BG-32-01',
    'XZTC/BG-33-01',
    'XZTC/BG-34-01', 'XZTC/BG-34-02',
    'XZTC/BG-35-03',
];
sort($expected);
g2b4_assert($docNumbers === $expected, 'G2 expansion batch4 exposes all explicit signed-scope form numbers');

$byNumber = [];
foreach ($templates as $template) {
    $byNumber[(string)$template['doc_number']] = $template;
    g2b4_assert(($template['version'] ?? '') === 'A/0', $template['doc_number'] . ' starts at A/0');
    g2b4_assert(($template['retention'] ?? '') === '不少于6年', $template['doc_number'] . ' declares retention');
    g2b4_assert(($template['status'] ?? '') === 'human_review_approved', $template['doc_number'] . ' is human-review approved');
    g2b4_assert(($template['print_template_key'] ?? '') === G2ExpansionBatch4BlueprintService::PRINT_TEMPLATE_KEY, $template['doc_number'] . ' uses expansion batch4 print template');
}

$specialNeedles = [
    'XZTC/BG-08-09' => ['CX-25 检测工作控制程序', '样品编号(=证书号)', '观测数据与图谱粘贴区', '样品影像', '检测人签名', '校核人签名'],
    'XZTC/BG-20-06' => ['条款', '查证方法', '证据', '判定'],
    'XZTC/BG-21-01' => ['批准人(总经理)'],
    'XZTC/BG-21-02' => ['报告结论', '输出决议明细'],
    'XZTC/BG-21-04' => ['管理评审验证记录表', '决议事项', '责任人', '期限', '验证结果'],
    'XZTC/BG-23-01' => ['申报审批', '实施', '效果审核'],
    'XZTC/BG-24-01' => ['能力确认要素勾选', '人员', '设备', '材料', '方法验证', '环境', 'GB/T 44914'],
    'XZTC/BG-24-02' => ['能力确认要素', '批准链明细'],
    'XZTC/BG-02-02' => ['区域', '参数', '要求值', '依据(标准或A015)', '监控方式', '对应记录(02-01)'],
    'XZTC/BG-05-02' => ['UF-02关闭'],
    'XZTC/BG-09-04' => ['定期重评审日期'],
    'XZTC/BG-13-01' => ['未编号会议记录表归位13-01'],
    'XZTC/BG-16-01' => ['两个未编号变体合并回母版'],
    'XZTC/BG-19-03' => ['保存期限(不少于6年)'],
    'XZTC/BG-19-04' => ['保存期限(不少于6年)'],
    'XZTC/BG-29-01' => ['7.8.7.2报告召回条款字段'],
    'XZTC/BG-33-01' => ['带空格重复件作废'],
];

foreach ($templates as $template) {
    foreach (['wulumuqi', 'hetian'] as $site) {
        $values = G2ExpansionBatch4BlueprintService::sampleValues((string)$template['doc_number'], $site);
        $html = RecordFormPrintService::render((string)$template['print_template_key'], $template, $values);
        g2b4_contains('版次：A/0', $html, $template['doc_number'] . ' renders version');
        g2b4_contains('保存期限：不少于6年', $html, $template['doc_number'] . ' renders retention');
        g2b4_contains('G2扩4批签认蓝图沙箱样张', $html, $template['doc_number'] . ' renders batch status');
        g2b4_contains((string)$template['name'], $html, $template['doc_number'] . ' renders name');
        if ($site === 'hetian') {
            g2b4_contains(str_replace('XZTC/BG-', 'XZTCH-BG-', (string)$template['doc_number']), $html, $template['doc_number'] . ' renders Hetian display number');
            g2b4_contains('母版：' . (string)$template['doc_number'], $html, $template['doc_number'] . ' renders master number');
        } else {
            g2b4_contains((string)$template['doc_number'], $html, $template['doc_number'] . ' renders Wulumuqi number');
        }
        foreach ($specialNeedles[(string)$template['doc_number']] ?? [] as $needle) {
            g2b4_contains($needle, $html, $template['doc_number'] . ' renders blueprint field');
        }
    }
}

echo "g2_expansion_batch4_blueprint_smoke passed\n";
