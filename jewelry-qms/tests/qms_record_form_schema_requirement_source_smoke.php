<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\service\QmsDocumentStructureService;
use app\service\QmsElementService;
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

QmsElementService::seedAll();
QmsDocumentStructureService::seedAll();

$template = Db::name('record_form_templates')
    ->where('doc_number', 'XZTC/BG-26-01')
    ->where('soft_delete', 0)
    ->field('id,doc_number,name')
    ->find();
assert_true(is_array($template), 'Computer software record form exists');

$evidence = QmsDocumentStructureService::recordFormRequirementEvidence((string)$template['id']);
$sourceEvidence = null;
foreach ($evidence as $row) {
    if ((string)($row['procedure_number'] ?? '') === 'XZTC/CX-26-2022'
        && str_contains((string)($row['markdown'] ?? ''), '计算机软件登记表')) {
        $sourceEvidence = $row;
        break;
    }
}
assert_true(is_array($sourceEvidence), 'Record form has XZTC/CX-26-2022 procedure requirement evidence');

$renderedPath = dirname(__DIR__) . '/runtime/qms_structured/record_form/XZTC_BG-26-01-A_0.md';
$markdown = file_get_contents($renderedPath) ?: '';
assert_true($markdown !== '', 'Rendered record form schema markdown exists');
assert_contains('### 程序记录要求来源', $markdown, 'Record form schema document embeds procedure requirement source section');
assert_contains('XZTC/CX-26-2022 计算机文件及数据控制程序', $markdown, 'Requirement source section names the source procedure');
assert_contains('procedure:qp_26:source:records', $markdown, 'Requirement source section keeps stable block key');
assert_contains('《计算机软件登记表》 XZTC/BG-26-01', $markdown, 'Requirement source section quotes the procedure record list');
assert_contains('schema来源：按程序文件记录要求复核字段、责任人、频次和保留期限', $markdown, 'Requirement source section carries schema construction requirement');
assert_contains('### schema构建依据', $markdown, 'Record form schema document has structured construction evidence');
assert_contains('程序要求：已匹配 XZTC/BG-26-01 计算机软件登记表', $markdown, 'Structured evidence confirms the procedure-required record form');
assert_contains('明细字段：`software_items` 计算机软件登记明细', $markdown, 'Structured evidence identifies the schema detail table');
assert_contains('责任/保管字段：`custodian` 保管人', $markdown, 'Structured evidence identifies responsible custody fields');
assert_contains('日期字段：`purchase_date` 购置日期', $markdown, 'Structured evidence identifies date fields');
assert_contains('频次要求：程序记录要求未明确，需人工复核', $markdown, 'Structured evidence flags missing frequency requirements for review');
assert_contains('保留期限：程序记录要求未明确，需人工复核', $markdown, 'Structured evidence flags missing retention requirements for review');

echo "qms_record_form_schema_requirement_source_smoke passed\n";
