<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\service\QmsDocumentStructureService;
use app\service\QmsElementService;
use think\facade\Db;

function assert_same($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function rendered_record_form_markdown(string $docNumber, string $name, string $sourceFileName): string
{
    $row = Db::name('qms_structured_documents')
        ->alias('sd')
        ->join('qms_document_assets a', 'a.id = sd.source_asset_id')
        ->join('record_form_templates r', 'r.id = a.record_form_template_id')
        ->where('sd.document_role', 'record_form')
        ->where('sd.soft_delete', 0)
        ->where('a.soft_delete', 0)
        ->where('r.soft_delete', 0)
        ->where('r.doc_number', $docNumber)
        ->where('r.name', $name)
        ->where('r.source_file_name', $sourceFileName)
        ->field('sd.rendered_file_path')
        ->find();
    assert_true(is_array($row), 'Rendered record form structured document exists for ' . $docNumber . ' ' . $name);

    $path = (string)($row['rendered_file_path'] ?? '');
    assert_true($path !== '', 'Rendered record form stores markdown path for ' . $docNumber . ' ' . $name);
    assert_true(is_file(dirname(__DIR__) . '/' . $path), 'Rendered record form markdown file exists for ' . $docNumber . ' ' . $name);

    return file_get_contents(dirname(__DIR__) . '/' . $path) ?: '';
}

QmsElementService::seedAll();
QmsDocumentStructureService::seedAll();

$rows = Db::name('record_form_templates')
    ->alias('r')
    ->leftJoin('documents d', 'd.id = r.procedure_doc_id')
    ->leftJoin('qms_elements e', 'e.id = r.element_id')
    ->where('r.soft_delete', 0)
    ->whereIn('r.doc_number', ['XZTC/BG-20-04', '待定-20-04', 'XZTC/BG-26-01', 'XZTC/BG-26-02', 'XZTC/BG-28-02'])
    ->field('r.doc_number,r.name,r.source_file_name,d.doc_number procedure_number,e.key element_key')
    ->order('r.doc_number')
    ->order('r.source_file_name')
    ->select()
    ->toArray();

$expected = [
    'XZTC/BG-20-04|授权签字人审核记录表|20-04授权签字人审核记录表.docx' => ['XZTC/CX-20-2022', 'internal_audit'],
    '待定-20-04|内部审核资料封皮目录|内部审核资料封皮目录.docx' => ['XZTC/CX-20-2022', 'internal_audit'],
    'XZTC/BG-26-01|计算机软件登记表|26-01计算机软件登记表.doc' => ['XZTC/CX-26-2022', 'data_information'],
    'XZTC/BG-26-02|计算机内容变更申请表|26-02计算机内容变更申请表.doc' => ['XZTC/CX-26-2022', 'data_information'],
    'XZTC/BG-28-02|样品标识卡|28-02样品标识卡.docx' => ['XZTC/CX-28-2022', 'item_handling'],
    'XZTC/BG-28-02|样品标识卡（（六联））|28-02样品标识卡（六联）.docx' => ['XZTC/CX-28-2022', 'item_handling'],
];

$actual = [];
foreach ($rows as $row) {
    $key = $row['doc_number'] . '|' . $row['name'] . '|' . $row['source_file_name'];
    if (isset($expected[$key])) {
        $actual[$key] = [$row['procedure_number'], $row['element_key']];
    }
}
ksort($expected);
ksort($actual);

assert_same($expected, $actual, 'Record form templates trace to their source procedure and unnumbered element');

$qp26Links = Db::name('qms_document_blocks')
    ->alias('b')
    ->join('qms_structured_documents sd', 'sd.id = b.structured_document_id')
    ->join('qms_document_block_links l', 'l.block_id = b.id AND l.soft_delete = 0')
    ->join('record_form_templates r', 'r.id = l.record_form_template_id')
    ->where('sd.doc_number', 'XZTC/CX-26-2022')
    ->where('b.block_type', 'record_requirement')
    ->where('l.relation_type', 'requires_record')
    ->where('b.soft_delete', 0)
    ->where('r.soft_delete', 0)
    ->column('r.doc_number');
$qp26Links = array_values(array_unique($qp26Links));
sort($qp26Links);

assert_same(['XZTC/BG-26-01', 'XZTC/BG-26-02'], $qp26Links, 'XZTC/CX-26-2022 record requirement block links both computer record forms');

$qp28Links = Db::name('qms_document_blocks')
    ->alias('b')
    ->join('qms_structured_documents sd', 'sd.id = b.structured_document_id')
    ->join('qms_document_block_links l', 'l.block_id = b.id AND l.soft_delete = 0')
    ->join('record_form_templates r', 'r.id = l.record_form_template_id')
    ->where('sd.doc_number', 'XZTC/CX-28-2022')
    ->where('b.block_type', 'record_requirement')
    ->where('l.relation_type', 'requires_record')
    ->where('b.soft_delete', 0)
    ->where('r.soft_delete', 0)
    ->where('r.doc_number', 'XZTC/BG-28-02')
    ->count();

assert_true($qp28Links >= 1, 'XZTC/CX-28-2022 record requirement block links sample label card templates');

$bg2601Markdown = rendered_record_form_markdown('XZTC/BG-26-01', '计算机软件登记表', '26-01计算机软件登记表.doc');
assert_true(str_contains($bg2601Markdown, '关联程序：XZTC/CX-26-2022 计算机文件及数据控制程序'), 'Rendered record form markdown names the linked XZTC/CX-26-2022 procedure');
assert_true(str_contains($bg2601Markdown, '关联要素：数据控制和信息管理'), 'Rendered record form markdown names the linked data-information element');

$bg2802Markdown = rendered_record_form_markdown('XZTC/BG-28-02', '样品标识卡', '28-02样品标识卡.docx');
assert_true(str_contains($bg2802Markdown, '关联程序：XZTC/CX-28-2022 样品处置和管理程序'), 'Rendered sample label markdown names the linked XZTC/CX-28-2022 procedure');
assert_true(str_contains($bg2802Markdown, '关联要素：检测和校准物品处置'), 'Rendered sample label markdown names the linked item-handling element');

echo "qms_record_form_traceability_smoke passed\n";
