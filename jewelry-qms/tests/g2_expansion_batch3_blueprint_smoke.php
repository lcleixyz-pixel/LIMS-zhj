<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\service\G2ExpansionBatch3BlueprintService;
use app\service\RecordFormPrintService;

function g2b3_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function g2b3_contains(string $needle, string $haystack, string $message): void
{
    g2b3_assert(str_contains($haystack, $needle), $message . ' (missing: ' . $needle . ')');
}

$templates = G2ExpansionBatch3BlueprintService::templates();
$docNumbers = array_column($templates, 'doc_number');
sort($docNumbers);
g2b3_assert($docNumbers === [
    'XZTC/BG-01-01',
    'XZTC/BG-01-03',
    'XZTC/BG-01-04',
    'XZTC/BG-01-05',
    'XZTC/BG-01-07',
    'XZTC/BG-01-08',
    'XZTC/BG-01-09',
    'XZTC/BG-22-01',
    'XZTC/BG-22-02',
    'XZTC/BG-22-03',
    'XZTC/BG-22-04',
    'XZTC/BG-30-01',
    'XZTC/BG-30-02',
    'XZTC/BG-30-03',
    'XZTC/BG-30-04',
    'XZTC/BG-30-05',
    'XZTC/BG-30-06',
    'XZTC/BG-31-01',
    'XZTC/BG-31-02',
], 'G2 expansion batch3 exposes exactly nineteen approved tables');

$byNumber = [];
foreach ($templates as $template) {
    $byNumber[(string)$template['doc_number']] = $template;
    g2b3_assert(($template['version'] ?? '') === 'A/0', $template['doc_number'] . ' starts at A/0');
    g2b3_assert(($template['retention'] ?? '') === '不少于6年', $template['doc_number'] . ' declares retention');
    g2b3_assert(($template['status'] ?? '') === 'human_review_approved', $template['doc_number'] . ' is human-review approved');
    g2b3_assert(($template['print_template_key'] ?? '') === G2ExpansionBatch3BlueprintService::PRINT_TEMPLATE_KEY, $template['doc_number'] . ' uses expansion batch3 print template');
}

g2b3_contains('撤回新建BG-30-03实施及评价记录表', (string)($byNumber['XZTC/BG-30-05']['correction_note'] ?? ''), 'BG-30-05 carries 30-am correction');
g2b3_contains('能力验证计划表占用', (string)($byNumber['XZTC/BG-30-03']['correction_note'] ?? ''), 'BG-30-03 remains capability verification plan');
g2b3_contains('归位BG-22-04', (string)($byNumber['XZTC/BG-22-04']['correction_note'] ?? ''), 'BG-22-04 carries standard-search numbering note');
g2b3_contains('已填件归档B1', (string)($byNumber['XZTC/BG-01-08']['master_note'] ?? ''), 'BG-01-08 carries filled-form archive note');
g2b3_contains('标准方法用验证', (string)($byNumber['XZTC/BG-22-02']['terminology_note'] ?? ''), 'BG-22-02 carries terminology note');

$needles = [
    'XZTC/BG-01-01' => ['预期目标', '完成情况回填'],
    'XZTC/BG-01-03' => ['证书复审提醒期'],
    'XZTC/BG-01-04' => ['考核结论(合格/需再培训)'],
    'XZTC/BG-01-05' => ['个人必要项(敏感信息最小化)', '档案联保密存放注'],
    'XZTC/BG-01-07' => ['技术档案要素索引(教育/培训/资格确认/授权/监督)'],
    'XZTC/BG-01-08' => ['空白母版重制说明', '已填件归档B1说明', '授权范围(方法)', '授权范围(场所)', '有效期'],
    'XZTC/BG-01-09' => ['对照01-06预期目标的达成评价'],
    'XZTC/BG-31-01' => ['监督员分工(俞总监督/曹乌市/李和田)'],
    'XZTC/BG-31-02' => ['被监督人', '监督发现', '处理及验证', '监督员签名'],
    'XZTC/BG-30-01' => ['客户再送样复测/影像比对复核', '留样复测仅限N10例外暂存样品'],
    'XZTC/BG-30-05' => ['结果评价与结论(满意/可疑/不满意及措施)'],
    'XZTC/BG-30-06' => ['结论联动纠正措施程序'],
    'XZTC/BG-30-02' => ['异常情况描述', '原因分析', '处理结果'],
    'XZTC/BG-30-03' => ['CNAS-RL02申请认可前能力验证要求注记'],
    'XZTC/BG-30-04' => ['实验室间比对计划明细'],
    'XZTC/BG-22-01' => ['确认阶段', '验证阶段'],
    'XZTC/BG-22-02' => ['验证内容', '验证结论'],
    'XZTC/BG-22-03' => ['CMA在库状态', 'CNAS申请状态', '库属性月查日期与查新人(T3)', '启用证据链索引(N7)'],
    'XZTC/BG-22-04' => ['标准查新报告', '结论联动22-03更新'],
];

foreach ($templates as $template) {
    foreach (['wulumuqi', 'hetian'] as $site) {
        $values = G2ExpansionBatch3BlueprintService::sampleValues((string)$template['doc_number'], $site);
        $html = RecordFormPrintService::render((string)$template['print_template_key'], $template, $values);
        g2b3_contains('人审通过', $html, $template['doc_number'] . ' renders approved status');
        g2b3_contains('版次：A/0', $html, $template['doc_number'] . ' renders version');
        g2b3_contains('保存期限：不少于6年', $html, $template['doc_number'] . ' renders retention');
        g2b3_contains((string)$template['name'], $html, $template['doc_number'] . ' renders name');
        if ($site === 'hetian') {
            g2b3_contains(str_replace('XZTC/BG-', 'XZTCH-BG-', (string)$template['doc_number']), $html, $template['doc_number'] . ' renders Hetian display number');
            g2b3_contains('母版：' . (string)$template['doc_number'], $html, $template['doc_number'] . ' renders master number');
        } else {
            g2b3_contains((string)$template['doc_number'], $html, $template['doc_number'] . ' renders Wulumuqi number');
        }
        foreach ($needles[(string)$template['doc_number']] as $needle) {
            g2b3_contains($needle, $html, $template['doc_number'] . ' renders blueprint field');
        }
    }
}

echo "g2_expansion_batch3_blueprint_smoke passed\n";
