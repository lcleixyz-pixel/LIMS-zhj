<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\service\G2ExpansionBatch1BlueprintService;
use app\service\RecordFormPrintService;

function g2b1_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function g2b1_contains(string $needle, string $haystack, string $message): void
{
    g2b1_assert(str_contains($haystack, $needle), $message . ' (missing: ' . $needle . ')');
}

$templates = G2ExpansionBatch1BlueprintService::templates();
$docNumbers = array_column($templates, 'doc_number');
sort($docNumbers);
g2b1_assert($docNumbers === [
    'XZTC/BG-09-01',
    'XZTC/BG-09-02',
    'XZTC/BG-09-05',
    'XZTC/BG-28-01',
    'XZTC/BG-28-02',
    'XZTC/BG-28-03',
    'XZTC/BG-28-04',
    'XZTC/BG-29-03',
], 'G2 expansion batch1 exposes exactly signed eight tables');
g2b1_assert(!in_array('XZTC/BG-09-03', $docNumbers, true), 'BG-09-03 remains occupied by contract/agreement register');
g2b1_assert(!in_array('XZTC/BG-09-04', $docNumbers, true), 'BG-09-04 is moved to expansion batch4');
g2b1_assert(!in_array('XZTC/BG-29-01', $docNumbers, true) && !in_array('XZTC/BG-29-02', $docNumbers, true), 'BG-29-01/02 are moved to expansion batch3');

$byNumber = [];
foreach ($templates as $template) {
    $byNumber[(string)$template['doc_number']] = $template;
    g2b1_assert(($template['version'] ?? '') === 'A/0', $template['doc_number'] . ' starts at A/0');
    g2b1_assert(($template['retention'] ?? '') === '不少于6年', $template['doc_number'] . ' declares retention');
    g2b1_assert(($template['print_template_key'] ?? '') === G2ExpansionBatch1BlueprintService::PRINT_TEMPLATE_KEY, $template['doc_number'] . ' uses the sandbox print template');
}

g2b1_contains('BG-09-05', (string)($byNumber['XZTC/BG-09-05']['renumber_note'] ?? ''), 'Transition register carries renumber note');
g2b1_contains('模板A', (string)($byNumber['XZTC/BG-09-05']['rebuild_note'] ?? ''), 'Transition register carries R-2 rebuild note');
g2b1_contains('单一母版', (string)($byNumber['XZTC/BG-28-02']['merge_note'] ?? ''), 'Sample tag card carries one-master merge note');
g2b1_contains('两场所各真实试用不少于1次', (string)($byNumber['XZTC/BG-28-04']['acceptance_gate'] ?? ''), 'BG-28-04 declares real-use gate');
g2b1_contains('两场所各真实试用不少于1次', (string)($byNumber['XZTC/BG-09-05']['acceptance_gate'] ?? ''), 'BG-09-05 declares real-use gate');

$needles = [
    'XZTC/BG-09-02' => ['判定规则约定栏(S3)', '报告标志状态(CMA/无标志)', '库外客户告知确认签字栏(N1/N3)', '检测场所'],
    'XZTC/BG-09-01' => ['所用标准及库内外状态(E3扩展列)', '拟用标志', '库外告知确认', '是否分包(固定否)', '评审日期(N3/N4签认)'],
    'XZTC/BG-28-01' => ['样品台账明细', '逾期通知日期', '处置记录', '备注/BG-09-05联动标记'],
    'XZTC/BG-28-02' => ['样品状态(待检/在检/完检/待领/已退回或处置)', '六联仅作为印制形式说明'],
    'XZTC/BG-28-03' => ['客户告知与处理结果'],
    'XZTC/BG-28-04' => ['核对人(样品管理员)', '领取人签名', '领取日期', '影像核对标记', '独立单联、一单一纸'],
    'XZTC/BG-29-03' => ['发放方式(自取/邮寄/电子)', '领取/接收人签名', '与28-04同场合并办理注记'],
    'XZTC/BG-09-05' => ['库外项目过渡合同台账主表(合同级,一合同一行)', '合同签署日期', '库外标准登记', '已出报告数量', '末份报告日期', '完成确认', '停用关闭确认', '冻结区', '台账明细附页(可选,样品级过程联动BG-28-01)'],
];

foreach ($templates as $template) {
    foreach (['wulumuqi', 'hetian'] as $site) {
        $values = G2ExpansionBatch1BlueprintService::sampleValues((string)$template['doc_number'], $site);
        $html = RecordFormPrintService::render((string)$template['print_template_key'], $template, $values);
        g2b1_contains('版次：A/0', $html, $template['doc_number'] . ' renders version');
        g2b1_contains('保存期限：不少于6年', $html, $template['doc_number'] . ' renders retention');
        g2b1_contains((string)$template['name'], $html, $template['doc_number'] . ' renders name');
        if ($site === 'hetian') {
            g2b1_contains(str_replace('XZTC/BG-', 'XZTCH-BG-', (string)$template['doc_number']), $html, $template['doc_number'] . ' renders Hetian display number');
            g2b1_contains('母版：' . (string)$template['doc_number'], $html, $template['doc_number'] . ' renders master number');
        } else {
            g2b1_contains((string)$template['doc_number'], $html, $template['doc_number'] . ' renders Wulumuqi number');
        }
        foreach ($needles[(string)$template['doc_number']] as $needle) {
            g2b1_contains($needle, $html, $template['doc_number'] . ' renders signed blueprint field');
        }
    }
}

echo "g2_expansion_batch1_blueprint_smoke passed\n";
