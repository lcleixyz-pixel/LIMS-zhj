<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\service\QmsElementService;
use think\facade\Db;

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function assert_mapping(string $elementKey, string $procedureNumber): void
{
    $row = Db::name('qms_element_documents')
        ->alias('l')
        ->join('qms_elements e', 'e.id = l.element_id')
        ->join('documents d', 'd.id = l.document_id')
        ->where('e.key', $elementKey)
        ->where('d.doc_number', $procedureNumber)
        ->where('e.soft_delete', 0)
        ->where('d.soft_delete', 0)
        ->where('l.soft_delete', 0)
        ->field('e.name,d.doc_number,d.title,l.note')
        ->find();
    assert_true(is_array($row), 'Element ' . $elementKey . ' maps to procedure ' . $procedureNumber);
    assert_true(!preg_match('/^[0-9]+(\\.[0-9]+)*/', (string)$row['name']), 'Mapped element is unnumbered: ' . $elementKey);
    assert_true(str_contains((string)$row['note'], '程序文件标题关键词'), 'Procedure mapping keeps seed evidence note');
}

QmsElementService::seedAll();

foreach ([
    'confidentiality' => 'XZTC/CX-06-2022',
    'metrological_traceability' => 'XZTC/CX-05-2022',
    'technical_records' => 'XZTC/CX-19-2022',
    'measurement_uncertainty' => 'XZTC/CX-27-2022',
    'improvement' => 'XZTC/CX-18-2022',
] as $elementKey => $procedureNumber) {
    assert_mapping($elementKey, $procedureNumber);
}

$beforeCount = Db::name('qms_element_documents')->where('soft_delete', 0)->count();
QmsElementService::seedProcedureDocuments();
$afterCount = Db::name('qms_element_documents')->where('soft_delete', 0)->count();
assert_true($beforeCount === $afterCount, 'Procedure element mapping seed is idempotent');

echo "qms_procedure_element_mapping_smoke passed\n";
