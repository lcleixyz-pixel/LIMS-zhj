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

function assert_same($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

QmsElementService::seedAll();

$sourceId = (string)Db::name('qms_sources')
    ->where('source_code', 'CNAS-CL01-G001:2024')
    ->where('soft_delete', 0)
    ->value('id');
assert_true($sourceId !== '', 'CNAS G001 source exists');

$clauseIds = Db::name('qms_clauses')
    ->where('source_id', $sourceId)
    ->where('soft_delete', 0)
    ->column('id');
assert_true(count($clauseIds) > 0, 'CNAS G001 clauses exist');

$linkedClauseCount = Db::name('qms_element_clause_links')
    ->whereIn('clause_id', $clauseIds)
    ->where('soft_delete', 0)
    ->distinct(true)
    ->count('clause_id');
assert_true((int)$linkedClauseCount > 0, 'CNAS G001 supplemental clauses are mapped to existing unnumbered elements');

$expectations = [
    '6.2.2' => '人员',
    '6.4.10' => '设备',
    '7.11.2' => '数据控制和信息管理',
    '8.8.2' => '内部审核',
    '8.9.1' => '管理评审',
];
foreach ($expectations as $clauseNumber => $elementName) {
    $row = Db::name('qms_clauses')
        ->alias('c')
        ->join('qms_element_clause_links l', 'l.clause_id = c.id AND l.soft_delete = 0')
        ->join('qms_elements e', 'e.id = l.element_id AND e.soft_delete = 0')
        ->where('c.source_id', $sourceId)
        ->where('c.clause_number', $clauseNumber)
        ->where('c.soft_delete', 0)
        ->field('c.clause_number,e.name,l.mapping_type,l.is_primary,l.note')
        ->find();
    assert_true(is_array($row), 'CNAS G001 clause ' . $clauseNumber . ' has an element mapping');
    assert_same($elementName, (string)$row['name'], 'CNAS G001 clause ' . $clauseNumber . ' maps to expected element');
    assert_same('supplement', (string)$row['mapping_type'], 'CNAS G001 clause ' . $clauseNumber . ' is a supplement mapping');
    assert_same(0, (int)$row['is_primary'], 'CNAS G001 clause ' . $clauseNumber . ' does not become primary ordering basis');
}

$numberedElements = Db::name('qms_elements')
    ->where('soft_delete', 0)
    ->whereRaw("`name` REGEXP '^[0-9]+(\\\\.[0-9]+)*'")
    ->count();
assert_same(0, (int)$numberedElements, 'Supplement mapping does not create numbered elements');

$rows = QmsElementService::externalSourceProcessingRows();
$g001 = null;
foreach ($rows as $row) {
    if ((string)$row['source']->source_code === 'CNAS-CL01-G001:2024') {
        $g001 = $row;
        break;
    }
}
assert_true(is_array($g001), 'Processing rows include CNAS G001');
assert_true((int)$g001['matched_element_count'] >= 10, 'CNAS G001 processing row reports matched existing elements');
assert_true((int)$g001['unmatched_clause_count'] < (int)$g001['clause_count'], 'CNAS G001 row distinguishes mapped and remaining clauses');

echo "qms_supplement_clause_mapping_smoke passed\n";
