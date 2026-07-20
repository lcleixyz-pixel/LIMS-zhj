<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\service\QmsElementService;
use app\service\QmsDocumentStructureService;
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

function assert_throws(callable $fn, string $needle, string $message): void
{
    try {
        $fn();
    } catch (Throwable $exception) {
        assert_contains($needle, $exception->getMessage(), $message);
        return;
    }
    fwrite(STDERR, $message . PHP_EOL);
    fwrite(STDERR, 'Expected exception containing: ' . $needle . PHP_EOL);
    exit(1);
}

QmsElementService::seedExternalSources();
QmsDocumentStructureService::seedAll();

assert_true(
    method_exists(QmsElementService::class, 'updateSourceFreshness'),
    'Element service can update external source freshness records'
);

$sourceId = (string)Db::name('qms_sources')
    ->where('source_code', 'CNAS-CL01-A015:2018')
    ->where('soft_delete', 0)
    ->value('id');
assert_true($sourceId !== '', 'CNAS A015 source exists for freshness update smoke');

$original = Db::name('qms_sources')->where('id', $sourceId)->find();
$freshnessPayload = [
    'freshness_checked_at' => '2026-05-28',
    'freshness_result' => 'CNAS官网查新：现行有效，未发现替代版本。',
    'freshness_evidence' => 'https://www.cnas.org.cn/认可规范文件清单；人工查新记录 2026-05-28',
    'next_freshness_due' => '2026-11-28',
    'freshness_status' => 'current',
];
$revisionNeedle = '外部依据查新更新：CNAS-CL01-A015:2018';

try {
    Db::name('qms_document_change_logs')
        ->where('revision_note', 'like', $revisionNeedle . '%')
        ->delete();

    $updated = QmsElementService::updateSourceFreshness($sourceId, $freshnessPayload);
    assert_true((string)($updated['source']->freshness_checked_at ?? '') === '2026-05-28', 'Freshness update returns checked date');
    assert_true((string)($updated['source']->freshness_result ?? '') === $freshnessPayload['freshness_result'], 'Freshness update returns result');
    assert_true((string)($updated['source']->freshness_evidence ?? '') === $freshnessPayload['freshness_evidence'], 'Freshness update returns evidence');
    assert_true((string)($updated['source']->next_freshness_due ?? '') === '2026-11-28', 'Freshness update returns next due date');
    assert_true((string)($updated['source']->freshness_status ?? '') === 'current', 'Freshness update returns status');
    assert_true((string)($updated['structured_document_id'] ?? '') !== '', 'Freshness update refreshes the external-basis structured document');
    assert_true((string)($updated['rendered_file_path'] ?? '') !== '', 'Freshness update returns the refreshed rendered markdown path');
    assert_true(is_file(dirname(__DIR__) . '/' . (string)$updated['rendered_file_path']), 'Freshness update renders markdown output');

    $stored = Db::name('qms_sources')->where('id', $sourceId)->find();
    assert_true((string)$stored['freshness_result'] === $freshnessPayload['freshness_result'], 'Freshness result is persisted');
    assert_true((string)$stored['freshness_evidence'] === $freshnessPayload['freshness_evidence'], 'Freshness evidence is persisted');

    $renderedMarkdown = file_get_contents(dirname(__DIR__) . '/' . (string)$updated['rendered_file_path']) ?: '';
    assert_contains('- 查新日期：2026-05-28', $renderedMarkdown, 'External-basis markdown includes freshness checked date');
    assert_contains('- 查新结论：' . $freshnessPayload['freshness_result'], $renderedMarkdown, 'External-basis markdown includes freshness result');
    assert_contains('- 查新证据：' . $freshnessPayload['freshness_evidence'], $renderedMarkdown, 'External-basis markdown includes freshness evidence');
    assert_contains('- 下次查新：2026-11-28', $renderedMarkdown, 'External-basis markdown includes next freshness due date');

    $log = Db::name('qms_document_change_logs')
        ->where('structured_document_id', (string)$updated['structured_document_id'])
        ->where('change_type', 'version_update')
        ->where('revision_note', 'like', $revisionNeedle . '%')
        ->where('soft_delete', 0)
        ->find();
    assert_true(is_array($log), 'Freshness update writes a structured document version log');
    assert_true((string)$log['archive_path'] !== '', 'Freshness update log points to a render archive');
    assert_true(is_file(dirname(__DIR__) . '/' . (string)$log['archive_path']), 'Freshness update render archive exists');

    QmsDocumentStructureService::renderSystemPackage();
    $impactRows = QmsDocumentStructureService::latestSystemPackageChangeImpactRows();
    $impactFound = false;
    foreach ($impactRows as $row) {
        if (str_starts_with((string)($row['revision_note'] ?? ''), $revisionNeedle)) {
            $impactFound = true;
            assert_true((string)($row['document_url'] ?? '') !== '', 'Freshness impact row links back to structured document');
            assert_contains('CNAS-CL01-A015:2018', (string)($row['doc_number'] ?? ''), 'Freshness impact row keeps source code');
            break;
        }
    }
    assert_true($impactFound, 'Latest system package impact rows include the freshness update');

    assert_throws(
        fn () => QmsElementService::updateSourceFreshness($sourceId, [
            'freshness_checked_at' => '2026-05-28',
            'freshness_result' => '无效状态测试',
            'freshness_evidence' => '测试证据',
            'next_freshness_due' => '2026-11-28',
            'freshness_status' => 'invalid',
        ]),
        '查新状态无效',
        'Freshness update rejects invalid status'
    );
    assert_throws(
        fn () => QmsElementService::updateSourceFreshness($sourceId, [
            'freshness_checked_at' => '2026-05-28',
            'freshness_result' => '',
            'freshness_evidence' => '测试证据',
            'next_freshness_due' => '2026-11-28',
            'freshness_status' => 'current',
        ]),
        '查新结论不能为空',
        'Freshness update requires a result'
    );
} finally {
    if (is_array($original)) {
        Db::name('qms_sources')->where('id', $sourceId)->update([
            'freshness_checked_at' => $original['freshness_checked_at'],
            'freshness_result' => $original['freshness_result'],
            'freshness_evidence' => $original['freshness_evidence'],
            'next_freshness_due' => $original['next_freshness_due'],
            'freshness_status' => $original['freshness_status'],
        ]);
        QmsDocumentStructureService::structureExternalBasisSource($sourceId);
        Db::name('qms_document_change_logs')
            ->where('revision_note', 'like', $revisionNeedle . '%')
            ->delete();
    }
}

$routeSource = file_get_contents(dirname(__DIR__) . '/route/app.php') ?: '';
assert_contains('planning/sources/freshness', $routeSource, 'Routes expose source freshness update action');

$controllerSource = file_get_contents(dirname(__DIR__) . '/app/controller/PlanningSource.php') ?: '';
assert_contains('updateSourceFreshness', $controllerSource, 'Source controller invokes freshness update service');
assert_contains("post('freshness_checked_at'", $controllerSource, 'Source controller reads freshness checked date from form');
assert_contains("post('freshness_evidence'", $controllerSource, 'Source controller reads freshness evidence from form');

$viewSource = file_get_contents(dirname(__DIR__) . '/app/view/planning_source/index.html') ?: '';
foreach ([
    'name="freshness_checked_at"',
    'name="freshness_result"',
    'name="freshness_evidence"',
    'name="next_freshness_due"',
    'name="freshness_status"',
    '/planning/sources/freshness',
    '更新查新',
] as $needle) {
    assert_contains($needle, $viewSource, 'Source page exposes freshness form field ' . $needle);
}

echo "qms_external_source_freshness_smoke passed\n";
