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

function assert_same($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual: ' . var_export($actual, true) . PHP_EOL);
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

assert_true(method_exists(QmsElementService::class, 'mapClauseToElement'), 'Element service exposes formal clause-to-element mapping');
assert_true(method_exists(QmsElementService::class, 'createLocalSupplementElementForClause'), 'Element service can create a local supplement element from a clause');

$root = dirname(__DIR__);
$route = (string)file_get_contents($root . '/route/app.php');
$clauseController = (string)file_get_contents($root . '/app/controller/PlanningClause.php');
$clauseView = (string)file_get_contents($root . '/app/view/planning_clause/view.html');
assert_contains('planning/clauses/map', $route, 'Routes expose formal clause mapping action');
assert_contains('planning/clauses/local-element', $route, 'Routes expose local supplement element action');
assert_contains('mapClauseToElement', $clauseController, 'Clause controller calls formal mapping service');
assert_contains('createLocalSupplementElementForClause', $clauseController, 'Clause controller calls local supplement element service');
assert_contains('映射到已有要素', $clauseView, 'Clause detail shows mapping-to-existing-element form');
assert_contains('创建本地补充要素', $clauseView, 'Clause detail shows local supplement element form');
assert_contains('/planning/clauses/map', $clauseView, 'Clause detail posts formal mapping form');
assert_contains('/planning/clauses/local-element', $clauseView, 'Clause detail posts local supplement element form');

$sourceId = (string)Db::name('qms_sources')
    ->where('soft_delete', 0)
    ->order('source_code', 'asc')
    ->value('id');
assert_true($sourceId !== '', 'A QMS source exists for manual mapping smoke');

$elementId = (string)Db::name('qms_elements')
    ->where('key', 'personnel')
    ->where('soft_delete', 0)
    ->value('id');
assert_true($elementId !== '', 'Personnel element exists as mapping target');

$manualClauseId = 'smoke-manual-mapping-clause';
$localClauseId = 'smoke-local-element-clause';
$localElementKey = 'local_supplement_' . substr(sha1($localClauseId), 0, 12);
$manualNote = '人工确认：归入人员要素';
$localNote = '人工确认：作为本地补充要素单独跟踪';
$now = date('Y-m-d H:i:s');

try {
    Db::name('qms_element_clause_links')->whereIn('clause_id', [$manualClauseId, $localClauseId])->delete();
    Db::name('qms_clauses')->whereIn('id', [$manualClauseId, $localClauseId])->delete();
    Db::name('qms_elements')->where('key', $localElementKey)->delete();

    foreach ([
        $manualClauseId => ['SMOKE-MAP-1', '人工映射 smoke 条款'],
        $localClauseId => ['SMOKE-LOCAL-1', '本地补充要素 smoke 条款'],
    ] as $id => [$number, $title]) {
        Db::name('qms_clauses')->insert([
            'id' => $id,
            'company_id' => (string)Config::get('qms.company_id'),
            'source_id' => $sourceId,
            'parent_id' => null,
            'clause_number' => $number,
            'title' => $title,
            'level' => 1,
            'page_number' => null,
            'locator' => 'smoke',
            'applicability' => 'applicable',
            'review_status' => 'published',
            'summary' => 'manual mapping smoke',
            'publish' => 1,
            'soft_delete' => 0,
            'created' => $now,
            'modified' => $now,
        ]);
    }

    $mapped = QmsElementService::mapClauseToElement($manualClauseId, $elementId, 'supplement', $manualNote);
    assert_same($manualClauseId, (string)$mapped['clause_id'], 'Manual mapping returns clause id');
    assert_same($elementId, (string)$mapped['element_id'], 'Manual mapping returns element id');

    QmsElementService::mapClauseToElement($manualClauseId, $elementId, 'supplement', $manualNote . '；复核保持同一映射');
    $manualLinks = Db::name('qms_element_clause_links')
        ->where('clause_id', $manualClauseId)
        ->where('element_id', $elementId)
        ->where('soft_delete', 0)
        ->select()
        ->toArray();
    assert_same(1, count($manualLinks), 'Manual mapping is idempotent for the same clause and element');
    assert_same('supplement', (string)$manualLinks[0]['mapping_type'], 'Manual mapping keeps supplement mapping type');
    assert_same(0, (int)$manualLinks[0]['is_primary'], 'Manual mapping never becomes the primary 27025 ordering basis');
    assert_contains('人工确认', (string)$manualLinks[0]['note'], 'Manual mapping stores human review note');

    $created = QmsElementService::createLocalSupplementElementForClause($localClauseId, [
        'name' => '本地补充要素 smoke',
        'summary' => '用于验证确无归属时新增本地补充要素。',
        'element_type' => 'management',
        'sort_order' => 9950,
        'note' => $localNote,
    ]);
    assert_same($localElementKey, (string)$created['element']['key'], 'Local supplement element uses stable hidden key derived from clause id');
    assert_same('本地补充要素 smoke', (string)$created['element']['name'], 'Local supplement element uses Chinese display name');
    assert_true(preg_match('/^[0-9]+(\\.[0-9]+)*/', (string)$created['element']['name']) !== 1, 'Local supplement element name is not a clause number');
    assert_same('management', (string)$created['element']['element_type'], 'Local supplement element keeps allowed element type');
    assert_same('under_review', (string)$created['element']['status'], 'Local supplement element is created under review');

    $localLink = Db::name('qms_element_clause_links')
        ->where('clause_id', $localClauseId)
        ->where('element_id', (string)$created['element']['id'])
        ->where('soft_delete', 0)
        ->find();
    assert_true(is_array($localLink), 'Local supplement element is linked to source clause');
    assert_same('supplement', (string)$localLink['mapping_type'], 'Local supplement clause link is a supplement mapping');
    assert_same(0, (int)$localLink['is_primary'], 'Local supplement clause link is not primary ordering basis');
    assert_contains('本地补充要素', (string)$localLink['note'], 'Local supplement link keeps human note');
} finally {
    Db::name('qms_element_clause_links')->whereIn('clause_id', [$manualClauseId, $localClauseId])->delete();
    Db::name('qms_clauses')->whereIn('id', [$manualClauseId, $localClauseId])->delete();
    Db::name('qms_elements')->where('key', $localElementKey)->delete();
}

echo "qms_clause_manual_mapping_smoke passed\n";
