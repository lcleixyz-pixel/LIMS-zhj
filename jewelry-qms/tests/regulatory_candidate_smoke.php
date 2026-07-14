<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\service\regulatory\RegulatoryCandidateService;
use app\service\regulatory\RegulatoryMonitorService;
use app\service\regulatory\RegulatorySourceRegistry;
use think\facade\Config;
use think\facade\Db;

function candidate_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function candidate_assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . PHP_EOL
            . 'Expected: ' . var_export($expected, true) . PHP_EOL
            . 'Actual: ' . var_export($actual, true)
        );
    }
}

function candidate_json(mixed $value): mixed
{
    if (is_array($value)) {
        return $value;
    }
    if (!is_string($value) || $value === '') {
        return null;
    }
    return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
}

function candidate_canonical(mixed $value): mixed
{
    if (!is_array($value)) {
        return $value;
    }
    foreach ($value as $key => $item) {
        $value[$key] = candidate_canonical($item);
    }
    if (!array_is_list($value)) {
        ksort($value);
    }
    return $value;
}

function make_sequence_clock(string $start): Closure
{
    $time = new DateTimeImmutable($start);
    return static function () use (&$time): DateTimeImmutable {
        $current = $time;
        $time = $time->modify('+1 second');
        return $current;
    };
}

function parsed_changed_item(RegulatorySourceRegistry $registry, string $fixture): array
{
    $source = $registry->source('samr_rkjcs_notice');
    $html = (string)file_get_contents(__DIR__ . '/fixtures/regulatory/' . $fixture);
    $result = $registry->adapterFor('samr_rkjcs_notice')->parse($html, $source);
    candidate_assert_same(1, count($result['items']), 'Changed notice fixture must parse exactly one item');
    return $result['items'][0];
}

$companyId = (string)Config::get('qms.company_id');
candidate_assert($companyId !== '', 'Smoke test requires configured company_id');

$registry = new RegulatorySourceRegistry();
$runIds = [];
$candidateIds = [];
$failure = null;

try {
    $v1 = parsed_changed_item($registry, 'changed_notice_v1.html');
    $v2 = parsed_changed_item($registry, 'changed_notice_v2.html');
    candidate_assert_same(
        'CNAS RL01:2026',
        RegulatoryCandidateService::normalizeAnnouncementNumber((string)$v1['announcement_number']),
        'Announcement number normalization must stabilize case and whitespace'
    );
    candidate_assert_same(
        RegulatoryCandidateService::normalizeAnnouncementNumber((string)$v1['announcement_number']),
        RegulatoryCandidateService::normalizeAnnouncementNumber((string)$v2['announcement_number']),
        'Full-width and half-width whitespace must produce the same source item key'
    );
    candidate_assert(
        RegulatoryCandidateService::normalizeAnnouncementNumber('CNAS RL01:2026')
            !== RegulatoryCandidateService::normalizeAnnouncementNumber('CNAS-RL01:2026'),
        'Announcement normalization must not merge distinct punctuation variants'
    );

    $hashItemA = $v1 + [
        'fetched_at' => '2026-07-14 09:00:00',
        'attachments' => [
            ['name' => '附件B.pdf', 'sha256' => 'bbb', 'size' => 20, 'fetched_at' => '2026-07-14 09:00:00'],
            ['size' => 10, 'sha256' => 'aaa', 'name' => '附件A.pdf', 'fetched_at' => '2026-07-14 09:00:00'],
        ],
    ];
    $hashItemB = $v1 + [
        'fetched_at' => '2026-07-15 11:30:00',
        'attachments' => [
            ['fetched_at' => '2026-07-15 11:30:00', 'name' => '附件A.pdf', 'size' => 10, 'sha256' => 'aaa'],
            ['sha256' => 'bbb', 'name' => '附件B.pdf', 'size' => 20, 'fetched_at' => '2026-07-15 11:30:00'],
        ],
    ];
    $hashService = new RegulatoryCandidateService(make_sequence_clock('2026-07-14 09:00:00'));
    candidate_assert_same(
        $hashService->contentHash($hashItemA),
        $hashService->contentHash($hashItemB),
        'Content hash must ignore fetched_at and stabilize attachment/key ordering'
    );

    $noNumber = $v1;
    $noNumber['announcement_number'] = null;
    candidate_assert_same(
        $v1['canonical_url'],
        $hashService->sourceItemKey($noNumber),
        'Canonical URL must be the source item key when announcement number is absent'
    );

    $runId = qms_uuid();
    $runIds[] = $runId;
    Db::name('qms_regulatory_monitor_runs')->insert([
        'id' => $runId,
        'company_id' => $companyId,
        'run_code' => 'REG-CANDIDATE-' . substr($runId, 0, 8),
        'trigger_mode' => 'manual',
        'started_at' => '2026-07-14 09:00:00',
        'status' => 'running',
        'created' => '2026-07-14 09:00:00',
        'modified' => '2026-07-14 09:00:00',
    ]);

    $now = new DateTimeImmutable('2026-07-14 09:00:01');
    $candidateService = new RegulatoryCandidateService(
        static function () use (&$now): DateTimeImmutable {
            return $now;
        }
    );
    $first = $candidateService->record(
        $companyId,
        $runId,
        'samr_rkjcs_notice',
        'html_list',
        $hashItemA
    );
    candidate_assert_same('new', $first['status'], 'First observation must create a candidate');
    $candidateIds[] = (string)$first['candidate']['id'];
    candidate_assert_same(1, (int)$first['new_count'], 'First observation new count must be one');
    candidate_assert_same(0, (int)$first['existing_count'], 'First observation existing count must be zero');
    candidate_assert_same(
        $hashService->contentHash($hashItemA),
        $first['candidate']['content_hash'],
        'Persisted content hash must match the public stable fingerprint of the raw parsed item'
    );
    candidate_assert_same(null, $first['candidate']['impact_analysis'], 'Task 3 must leave impact analysis unassessed');
    candidate_assert_same(null, $first['candidate']['supersedes_candidate_id'], 'First version must not supersede anything');
    candidate_assert_same('CNAS RL01:2026', $first['candidate']['announcement_number'], 'Stored announcement number must use the stable normalized form');
    candidate_assert_same(
        candidate_canonical($hashItemA),
        candidate_canonical($first['candidate']['evidence_json']),
        'Candidate must retain the complete parsed raw evidence'
    );

    Db::name('qms_external_change_candidates')->where('id', $first['candidate']['id'])->update([
        'review_status' => 'deferred',
        'reviewed_by' => 'human-reviewer',
        'reviewed_at' => '2026-07-14 09:30:00',
        'review_comment' => '保留人工判断',
        'promoted_event_id' => null,
    ]);
    $now = new DateTimeImmutable('2026-07-14 10:00:00');
    $same = $candidateService->record(
        $companyId,
        $runId,
        'samr_rkjcs_notice',
        'html_list',
        $hashItemB
    );
    candidate_assert_same('existing', $same['status'], 'Repeated identical content must reuse the candidate');
    candidate_assert_same(0, (int)$same['new_count'], 'Repeated content new count must be zero');
    candidate_assert_same(1, (int)$same['existing_count'], 'Repeated content existing count must be one');
    candidate_assert_same($first['candidate']['id'], $same['candidate']['id'], 'Repeated content must retain candidate id');
    candidate_assert_same('2026-07-14 09:00:01', $same['candidate']['first_seen_at'], 'Repeated content must retain first_seen_at');
    candidate_assert_same('2026-07-14 10:00:00', $same['candidate']['last_seen_at'], 'Repeated content must advance last_seen_at');
    candidate_assert_same('deferred', $same['candidate']['review_status'], 'Repeated content must preserve manual review status');
    candidate_assert_same('human-reviewer', $same['candidate']['reviewed_by'], 'Repeated content must preserve reviewer');
    candidate_assert_same('保留人工判断', $same['candidate']['review_comment'], 'Repeated content must preserve review comment');
    candidate_assert_same(1, Db::name('qms_external_change_candidates')->where('id', $first['candidate']['id'])->count(), 'Repeated content must keep one row');

    $sameDocumentDifferentUrl = $hashItemA;
    $sameDocumentDifferentUrl['canonical_url'] = 'https://www.samr.gov.cn/rkjcs/tzgg/alternate-location.html';
    $now = new DateTimeImmutable('2026-07-14 10:30:00');
    $sameByNumber = $candidateService->record(
        $companyId,
        $runId,
        'samr_rkjcs_notice',
        'html_list',
        $sameDocumentDifferentUrl
    );
    candidate_assert_same('existing', $sameByNumber['status'], 'Same announcement and content at a different URL must be existing');
    candidate_assert_same($first['candidate']['id'], $sameByNumber['candidate']['id'], 'Announcement number must take priority over URL');

    $now = new DateTimeImmutable('2026-07-14 11:00:00');
    $second = $candidateService->record(
        $companyId,
        $runId,
        'samr_rkjcs_notice',
        'html_list',
        $v2
    );
    $candidateIds[] = (string)$second['candidate']['id'];
    candidate_assert_same('new', $second['status'], 'Changed content must create a new version');
    candidate_assert_same($first['candidate']['id'], $second['candidate']['supersedes_candidate_id'], 'New version must supersede the previous candidate');
    candidate_assert_same('pending', $second['candidate']['review_status'], 'New version must restart at pending review');
    $oldAfterVersion = Db::name('qms_external_change_candidates')->where('id', $first['candidate']['id'])->find();
    candidate_assert_same('deferred', $oldAfterVersion['review_status'], 'Creating a new version must not alter old review status');
    candidate_assert_same('保留人工判断', $oldAfterVersion['review_comment'], 'Creating a new version must not alter old review comment');

    foreach ([
        ['title' => str_repeat('题', 301), 'message' => 'title'],
        ['announcement_number' => str_repeat('N', 121), 'message' => 'announcement_number'],
        ['canonical_url' => 'https://www.samr.gov.cn/' . str_repeat('u', 490), 'message' => 'source_url'],
    ] as $invalidCase) {
        $invalid = $v1;
        $field = array_key_first($invalidCase);
        $invalid[$field] = $invalidCase[$field];
        try {
            $candidateService->record($companyId, $runId, 'length-check-' . qms_uuid(), 'html_list', $invalid);
            throw new RuntimeException('Length validation did not reject ' . $invalidCase['message']);
        } catch (InvalidArgumentException $exception) {
            candidate_assert(str_contains($exception->getMessage(), $invalidCase['message']), 'Length error must name ' . $invalidCase['message']);
        }
    }

    $raceRunId = qms_uuid();
    $runIds[] = $raceRunId;
    Db::name('qms_regulatory_monitor_runs')->insert([
        'id' => $raceRunId,
        'company_id' => $companyId,
        'run_code' => 'REG-RACE-' . substr($raceRunId, 0, 8),
        'trigger_mode' => 'manual',
        'started_at' => '2026-07-14 12:00:00',
        'status' => 'running',
        'created' => '2026-07-14 12:00:00',
        'modified' => '2026-07-14 12:00:00',
    ]);
    $raceInsertCalls = 0;
    $raceInserter = static function (array $data) use (&$raceInsertCalls): void {
        $raceInsertCalls++;
        Db::name('qms_external_change_candidates')->insert($data);
        throw new RuntimeException('SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry', 1062);
    };
    $raceService = new RegulatoryCandidateService(
        static fn (): DateTimeImmutable => new DateTimeImmutable('2026-07-14 12:00:01'),
        $raceInserter
    );
    $raceItem = $noNumber;
    $raceItem['canonical_url'] = 'https://www.samr.gov.cn/race/' . qms_uuid() . '.html';
    $race = $raceService->record($companyId, $raceRunId, 'race-source', 'html_list', $raceItem);
    $candidateIds[] = (string)$race['candidate']['id'];
    candidate_assert_same(1, $raceInsertCalls, 'Race simulation must exercise the insert path once');
    candidate_assert_same('existing', $race['status'], 'Unique-key race must re-query and return existing');
    candidate_assert_same(1, Db::name('qms_external_change_candidates')->where('id', $race['candidate']['id'])->count(), 'Unique-key race must retain one row');

    $fixtureBodies = [
        'samr_rkjcs_notice' => (string)file_get_contents(__DIR__ . '/fixtures/regulatory/changed_notice_v1.html'),
        'cnas_lab_notice' => (string)file_get_contents(__DIR__ . '/fixtures/regulatory/cnas_notice_list.html'),
    ];
    $fetchedKeys = [];
    $fetcher = static function (array $source) use (&$fetchedKeys, $fixtureBodies): string {
        $key = (string)$source['key'];
        $fetchedKeys[] = $key;
        return $fixtureBodies[$key] ?? throw new RuntimeException('Unexpected fetch key: ' . $key);
    };
    $allSuccessService = new RegulatoryMonitorService(
        registry: $registry,
        sourceFetcher: $fetcher,
        clock: make_sequence_clock('2026-07-14 13:00:00'),
        candidateService: new RegulatoryCandidateService(make_sequence_clock('2026-07-14 13:00:10'))
    );
    $allSuccess = $allSuccessService->run('manual', [
        'samr_rkjcs_notice',
        'cnas_lab_notice',
        'cma_capability_query',
    ]);
    $runIds[] = (string)$allSuccess['run_id'];
    candidate_assert_same('completed', $allSuccess['status'], 'All automatic sources succeeding must complete the run');
    candidate_assert_same(3, $allSuccess['source_count'], 'Run must count all selected sources');
    candidate_assert_same(2, $allSuccess['success_count'], 'Run must count successful automatic sources');
    candidate_assert_same(0, $allSuccess['failure_count'], 'Run must report no automatic failures');
    candidate_assert_same(1, $allSuccess['manual_verification_count'], 'Manual-only source must be reported separately');
    candidate_assert(!in_array('cma_capability_query', $fetchedKeys, true), 'Manual-only source must never invoke fetcher');
    candidate_assert_same(2, $allSuccess['candidate_new_count'] + $allSuccess['candidate_existing_count'], 'Run candidate counts must cover parsed items');

    $secretError = "Authorization: Bearer super-secret\nCookie: sid=secret; preference=private\n"
        . 'password=hunter2 token=abc123 dsn=mysql://root:dbpass@db/jewelry '
        . str_repeat('detail-', 120);
    $partialFetcher = static function (array $source) use ($fixtureBodies, $secretError): string {
        if ($source['key'] === 'cnas_lab_notice') {
            throw new RuntimeException($secretError);
        }
        return $fixtureBodies[(string)$source['key']];
    };
    $partialService = new RegulatoryMonitorService(
        registry: $registry,
        sourceFetcher: $partialFetcher,
        clock: make_sequence_clock('2026-07-14 14:00:00'),
        candidateService: new RegulatoryCandidateService(make_sequence_clock('2026-07-14 14:00:10'))
    );
    $partial = $partialService->run('scheduled', ['samr_rkjcs_notice', 'cnas_lab_notice']);
    $runIds[] = (string)$partial['run_id'];
    candidate_assert_same('partial_failed', $partial['status'], 'One source failure must not stop another successful source');
    candidate_assert_same(1, $partial['success_count'], 'Partial run must retain successful source count');
    candidate_assert_same(1, $partial['failure_count'], 'Partial run must retain failed source count');
    $partialRow = Db::name('qms_regulatory_monitor_runs')->where('id', $partial['run_id'])->find();
    candidate_assert(is_array($partialRow), 'Partial run must be persisted');
    candidate_assert(strlen((string)$partialRow['error_summary']) <= 1000, 'Persisted error summary must have a hard length bound');
    foreach (['super-secret', 'sid=secret', 'preference=private', 'hunter2', 'abc123', 'mysql://', 'root:dbpass', 'Authorization:', 'Cookie:'] as $leak) {
        candidate_assert(!str_contains((string)$partialRow['error_summary'], $leak), 'Error summary leaked secret fragment: ' . $leak);
    }
    $partialResult = candidate_json($partialRow['result_json']);
    candidate_assert_same('partial_failed', $partialResult['status'], 'Persisted result JSON must retain status');
    candidate_assert_same(2, count($partialResult['sources']), 'Persisted result JSON must retain source-level summaries');
    candidate_assert(isset($partialRow['execution_version'], $partialRow['source_config_version'], $partialRow['rule_version']), 'Run must persist collector, source config and rule versions');

    $allFailedService = new RegulatoryMonitorService(
        registry: $registry,
        sourceFetcher: static fn (array $source): string => throw new RuntimeException('fetch failed for ' . $source['key']),
        clock: make_sequence_clock('2026-07-14 15:00:00'),
        candidateService: new RegulatoryCandidateService(make_sequence_clock('2026-07-14 15:00:10'))
    );
    $allFailed = $allFailedService->run('scheduled', ['samr_rkjcs_notice', 'cnas_lab_notice']);
    $runIds[] = (string)$allFailed['run_id'];
    candidate_assert_same('failed', $allFailed['status'], 'All automatic sources failing must fail the run');
    candidate_assert_same(0, $allFailed['success_count'], 'Failed run must report zero successes');
    candidate_assert_same(2, $allFailed['failure_count'], 'Failed run must report all automatic failures');

    foreach ($runIds as $storedRunId) {
        $stored = Db::name('qms_regulatory_monitor_runs')->where('id', $storedRunId)->find();
        candidate_assert(is_array($stored), 'Every run must remain readable until finally cleanup');
        if (in_array((string)$stored['status'], ['completed', 'partial_failed', 'failed'], true)) {
            candidate_assert((string)$stored['finished_at'] !== '', 'Finished run must persist finished_at');
        }
    }
} catch (Throwable $exception) {
    $failure = $exception;
} finally {
    if ($runIds !== []) {
        Db::name('qms_external_change_candidates')->whereIn('monitor_run_id', array_values(array_unique($runIds)))->delete();
        Db::name('qms_regulatory_monitor_runs')->whereIn('id', array_values(array_unique($runIds)))->delete();
    }
    foreach (array_values(array_unique($candidateIds)) as $candidateId) {
        Db::name('qms_external_change_candidates')->where('id', $candidateId)->delete();
    }
}

if ($failure instanceof Throwable) {
    fwrite(STDERR, $failure->getMessage() . PHP_EOL);
    exit(1);
}

echo "regulatory_candidate_smoke passed\n";
