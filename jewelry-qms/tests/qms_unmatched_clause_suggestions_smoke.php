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

function assert_contains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Missing: ' . $needle . PHP_EOL);
        exit(1);
    }
}

QmsElementService::seedAll();

$clause = Db::table('qms_clauses')
    ->alias('c')
    ->join('qms_sources s', 's.id = c.source_id')
    ->where('s.source_code', 'GB/T 27025-2019')
    ->where('c.clause_number', '7.5.1')
    ->where('c.soft_delete', 0)
    ->field('c.id,c.clause_number,c.title,s.source_code')
    ->find();
assert_true((bool)$clause, 'Sample unmatched GB/T clause exists');

$linkCountBefore = Db::table('qms_element_clause_links')
    ->where('clause_id', (string)$clause['id'])
    ->where('soft_delete', 0)
    ->count();

$summary = QmsElementService::refreshAgentSuggestions();

$linkCountAfter = Db::table('qms_element_clause_links')
    ->where('clause_id', (string)$clause['id'])
    ->where('soft_delete', 0)
    ->count();
assert_true($linkCountAfter === $linkCountBefore, 'Refreshing suggestions does not create formal clause-element links');
assert_true(isset($summary['unmatched_clause_suggestions']), 'Suggestion refresh reports unmatched clause suggestions');
assert_true((int)$summary['unmatched_clause_suggestions'] > 0, 'Suggestion refresh creates unmatched clause suggestions');

$suggestion = Db::table('qms_agent_suggestions')
    ->whereNull('element_id')
    ->where('suggestion_type', 'mapping')
    ->where('status', 'open')
    ->whereLike('title', '%GB/T 27025-2019 7.5.1%')
    ->find();
assert_true((bool)$suggestion, 'Unmatched clause creates an advisory mapping suggestion');
assert_contains('人工判断', (string)$suggestion['content'], 'Unmatched clause suggestion is explicitly manual');
assert_contains('不自动修改正式体系数据', (string)$suggestion['evidence'], 'Suggestion evidence states it is advisory only');

$dashboardSource = file_get_contents(dirname(__DIR__) . '/app/controller/PlanningDashboard.php') ?: '';
assert_contains('openClauseMappingSuggestions', $dashboardSource, 'Dashboard loads unmatched clause mapping suggestions');

$dashboardView = file_get_contents(dirname(__DIR__) . '/app/view/planning_dashboard/index.html') ?: '';
assert_contains('未匹配条款建议', $dashboardView, 'Dashboard shows unmatched clause suggestions');
assert_contains('仅供人工处理', $dashboardView, 'Dashboard states suggestions are advisory');

echo "qms_unmatched_clause_suggestions_smoke passed\n";
