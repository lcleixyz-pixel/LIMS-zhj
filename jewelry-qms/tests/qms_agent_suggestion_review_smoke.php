<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\model\QmsAgentSuggestion;
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

function assert_contains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Missing: ' . $needle . PHP_EOL);
        exit(1);
    }
}

function assert_throws_runtime(callable $callback, string $expectedMessage, string $message): void
{
    try {
        $callback();
    } catch (Throwable $exception) {
        if (!$exception instanceof RuntimeException || !str_contains($exception->getMessage(), $expectedMessage)) {
            fwrite(STDERR, $message . PHP_EOL);
            fwrite(STDERR, 'Actual: ' . get_class($exception) . ' ' . $exception->getMessage() . PHP_EOL);
            exit(1);
        }
        return;
    }

    fwrite(STDERR, $message . PHP_EOL);
    fwrite(STDERR, 'Actual: no exception thrown' . PHP_EOL);
    exit(1);
}

QmsElementService::seedAll();

$elementId = (string)Db::name('qms_elements')->where('key', 'data_information')->where('soft_delete', 0)->value('id');
assert_true($elementId !== '', 'Data-information element exists');
assert_true(method_exists(QmsElementService::class, 'reviewAgentSuggestion'), 'Element service can review agent suggestions');

$acceptId = 'smoke-agent-suggestion-accept';
$rejectId = 'smoke-agent-suggestion-reject';
$reviewedGapId = 'smoke-reviewed-gap';
$reviewedMappingId = 'smoke-reviewed-mapping';
$reviewedClauseId = 'smoke-reviewed-clause';
$acceptNote = '人工采纳：列入后续体系修订任务，但不自动改正式体系数据';
$rejectNote = '人工驳回：证据不足，保留复核说明';
$now = date('Y-m-d H:i:s');

try {
    Db::name('qms_agent_suggestions')->whereIn('id', [$acceptId, $rejectId, $reviewedGapId, $reviewedMappingId])->delete();
    Db::name('qms_clauses')->where('id', $reviewedClauseId)->delete();
    foreach ([
        $acceptId => ['element_id' => $elementId, 'title' => '智能体建议 review smoke：采纳'],
        $rejectId => ['element_id' => null, 'title' => '智能体建议 review smoke：驳回'],
    ] as $id => $row) {
        Db::name('qms_agent_suggestions')->insert([
            'id' => $id,
            'company_id' => (string)Config::get('qms.company_id'),
            'element_id' => $row['element_id'],
            'suggestion_type' => $row['element_id'] ? 'gap' : 'mapping',
            'title' => $row['title'],
            'content' => '智能体只写建议，人工复核后修改正式体系数据。',
            'evidence' => 'review smoke evidence',
            'status' => 'open',
            'created' => $now,
            'modified' => $now,
        ]);
    }

    $clauseLinkCount = Db::name('qms_element_clause_links')->where('soft_delete', 0)->count();
    $blockLinkCount = Db::name('qms_document_block_links')->where('soft_delete', 0)->count();

    $accepted = QmsElementService::reviewAgentSuggestion($acceptId, 'accepted', $acceptNote);
    assert_true(($accepted['status'] ?? '') === 'accepted', 'Suggestion review returns accepted status');
    assert_true(($accepted['review_note'] ?? '') === $acceptNote, 'Accepted suggestion keeps review note');
    assert_true((string)Db::name('qms_agent_suggestions')->where('id', $acceptId)->value('status') === 'accepted', 'Accepted suggestion is no longer open');
    assert_true((string)Db::name('qms_agent_suggestions')->where('id', $acceptId)->value('review_note') === $acceptNote, 'Accepted suggestion persists review note');

    $rejected = QmsElementService::reviewAgentSuggestion($rejectId, 'rejected', $rejectNote);
    assert_true(($rejected['status'] ?? '') === 'rejected', 'Suggestion review returns rejected status');
    assert_true(($rejected['review_note'] ?? '') === $rejectNote, 'Rejected suggestion keeps review note');

    assert_true(Db::name('qms_element_clause_links')->where('soft_delete', 0)->count() === $clauseLinkCount, 'Suggestion review does not create formal clause mappings');
    assert_true(Db::name('qms_document_block_links')->where('soft_delete', 0)->count() === $blockLinkCount, 'Suggestion review does not create formal block links');

    assert_throws_runtime(
        fn () => QmsElementService::reviewAgentSuggestion($acceptId, 'open', 'reopen'),
        '建议处理状态无效',
        'Suggestion review only allows accepted or rejected'
    );
    assert_throws_runtime(
        fn () => QmsElementService::reviewAgentSuggestion('missing-suggestion', 'accepted', $acceptNote),
        '智能体建议不存在',
        'Missing suggestion is a controlled error'
    );

    $gapRow = null;
    foreach (QmsElementService::coverageStats() as $row) {
        if ((string)$row['element']->key === 'resources_general' && (int)$row['gap_count'] > 0) {
            $gapRow = $row;
            break;
        }
    }
    assert_true(is_array($gapRow), 'Resource-general element keeps a coverage gap for reviewed suggestion suppression');
    $gapTitle = '补齐' . (string)$gapRow['element']->name . '追溯缺口';
    $gapContent = implode('、', $gapRow['gap_labels']);
    Db::name('qms_agent_suggestions')
        ->where('suggestion_type', 'gap')
        ->where('element_id', (string)$gapRow['element']->id)
        ->where('title', $gapTitle)
        ->where('content', $gapContent)
        ->delete();
    Db::name('qms_agent_suggestions')->insert([
        'id' => $reviewedGapId,
        'company_id' => (string)Config::get('qms.company_id'),
        'element_id' => (string)$gapRow['element']->id,
        'suggestion_type' => 'gap',
        'title' => $gapTitle,
        'content' => $gapContent,
        'evidence' => 'reviewed gap suppression smoke',
        'status' => 'accepted',
        'review_note' => $acceptNote,
        'created' => $now,
        'modified' => $now,
    ]);

    $source = Db::name('qms_sources')->where('soft_delete', 0)->field('id,source_code,name')->order('source_code', 'asc')->find();
    assert_true(is_array($source), 'A source exists for reviewed mapping suggestion suppression');
    $mappingTitle = '评估未匹配条款：' . (string)$source['source_code'] . ' SMOKE-REVIEW';
    Db::name('qms_agent_suggestions')->where('title', $mappingTitle)->delete();
    Db::name('qms_clauses')->insert([
        'id' => $reviewedClauseId,
        'company_id' => (string)Config::get('qms.company_id'),
        'source_id' => (string)$source['id'],
        'parent_id' => null,
        'clause_number' => 'SMOKE-REVIEW',
        'title' => '已处理条款建议不应重复打开',
        'level' => 1,
        'page_number' => null,
        'locator' => 'reviewed suggestion smoke',
        'applicability' => 'applicable',
        'review_status' => 'published',
        'summary' => '用于验证已处理未匹配条款建议不会重复生成。',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => $now,
        'modified' => $now,
    ]);
    Db::name('qms_agent_suggestions')->insert([
        'id' => $reviewedMappingId,
        'company_id' => (string)Config::get('qms.company_id'),
        'element_id' => null,
        'suggestion_type' => 'mapping',
        'title' => $mappingTitle,
        'content' => '已人工处理的未匹配条款建议不应重复打开。',
        'evidence' => 'reviewed mapping suppression smoke',
        'status' => 'accepted',
        'review_note' => $acceptNote,
        'created' => $now,
        'modified' => $now,
    ]);

    QmsElementService::refreshAgentSuggestions();
    assert_true(
        Db::name('qms_agent_suggestions')
            ->where('suggestion_type', 'gap')
            ->where('element_id', (string)$gapRow['element']->id)
            ->where('title', $gapTitle)
            ->where('content', $gapContent)
            ->where('status', 'open')
            ->count() === 0,
        'Reviewed coverage gap suggestions are not reopened by refresh'
    );
    assert_true(
        Db::name('qms_agent_suggestions')
            ->where('suggestion_type', 'mapping')
            ->where('title', $mappingTitle)
            ->where('status', 'open')
            ->count() === 0,
        'Reviewed unmatched clause suggestions are not reopened by refresh'
    );

    $routeSource = file_get_contents(dirname(__DIR__) . '/route/app.php') ?: '';
    assert_contains("planning/suggestions/review", $routeSource, 'Routes expose suggestion review action');

    $dashboardController = file_get_contents(dirname(__DIR__) . '/app/controller/PlanningDashboard.php') ?: '';
    assert_contains('reviewAgentSuggestion', $dashboardController, 'Dashboard controller delegates suggestion review to service');
    assert_contains('review_note', $dashboardController, 'Dashboard controller collects review note');

    $dashboardView = file_get_contents(dirname(__DIR__) . '/app/view/planning_dashboard/index.html') ?: '';
    assert_contains('采纳建议', $dashboardView, 'Dashboard exposes accepting suggestions');
    assert_contains('驳回建议', $dashboardView, 'Dashboard exposes rejecting suggestions');
    assert_contains('review_note', $dashboardView, 'Dashboard requires a human review note');

    $elementView = file_get_contents(dirname(__DIR__) . '/app/view/planning_element/view.html') ?: '';
    assert_contains('采纳建议', $elementView, 'Element detail exposes accepting element suggestions');
    assert_contains('驳回建议', $elementView, 'Element detail exposes rejecting element suggestions');
    assert_contains('review_note', $elementView, 'Element detail requires a human review note');
} finally {
    Db::name('qms_agent_suggestions')->whereIn('id', [$acceptId, $rejectId, $reviewedGapId, $reviewedMappingId])->delete();
    Db::name('qms_agent_suggestions')->where('title', $mappingTitle ?? '')->delete();
    if (isset($gapTitle, $gapContent, $gapRow) && is_array($gapRow)) {
        Db::name('qms_agent_suggestions')
            ->where('suggestion_type', 'gap')
            ->where('element_id', (string)$gapRow['element']->id)
            ->where('title', $gapTitle)
            ->where('content', $gapContent)
            ->delete();
    }
    Db::name('qms_clauses')->where('id', $reviewedClauseId)->delete();
}

echo "qms_agent_suggestion_review_smoke passed\n";
