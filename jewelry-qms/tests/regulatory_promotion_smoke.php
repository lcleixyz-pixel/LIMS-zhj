<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\model\QmsExternalChangeEvent;
use app\service\ExternalChangeEventService;
use think\facade\Config;
use think\facade\Db;
use think\facade\Session;

$app = new think\App();
$app->initialize();

final class PromotionEventFailureService extends ExternalChangeEventService
{
    protected static function persistPromotedEvent(array $data): QmsExternalChangeEvent
    {
        throw new RuntimeException('mysql password=do-not-leak');
    }
}

final class PromotionAuditFailureService extends ExternalChangeEventService
{
    protected static function writePromotionHistory(string $candidateId, string $eventId, string $actorId): void
    {
        throw new RuntimeException('audit backend secret=do-not-leak');
    }
}

function promotion_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function promotion_throws(callable $callback, string $message, ?string $forbiddenText = null): void
{
    try {
        $callback();
    } catch (Throwable $exception) {
        if ($forbiddenText !== null) {
            promotion_assert(!str_contains($exception->getMessage(), $forbiddenText), $message . '（异常泄露内部详情）');
        }
        return;
    }
    promotion_assert(false, $message);
}

/** @return array<string, mixed> */
function promotion_candidate(
    string $id,
    string $companyId,
    string $status = 'confirmed_applicable',
    int $publish = 1,
    int $softDelete = 0,
    string $sourceKey = 'samr_rkjcs_notice',
    ?string $graphHash = null
): array {
    $now = date('Y-m-d H:i:s');
    $token = substr(hash('sha256', $id), 0, 12);

    return [
        'id' => $id,
        'company_id' => $companyId,
        'monitor_run_id' => 'prom-run-' . $token,
        'source_key' => $sourceKey,
        'source_mode' => $sourceKey === 'cma_capability_query' ? 'manual_only' : 'html_list',
        'source_item_key' => 'PROM-' . $token,
        'source_url' => 'https://www.samr.gov.cn/promotion/' . $token,
        'normalized_url' => 'https://www.samr.gov.cn/promotion/' . $token,
        'title' => '法规晋升冒烟 ' . $token,
        'announcement_number' => '晋升公告〔2026〕' . substr($token, 0, 4) . '号',
        'document_type' => 'official_notice',
        'published_date' => '2026-07-01',
        'effective_date' => '2026-08-01',
        'first_seen_at' => $now,
        'last_seen_at' => $now,
        'content_hash' => hash('sha256', 'content-' . $id),
        'evidence_summary' => "&lt;script&gt;encoded-alert&lt;/script&gt; <script>alert('x')</script> 官方候选证据\n第二行",
        'evidence_refs' => json_encode([['url' => 'https://www.samr.gov.cn/promotion/' . $token]], JSON_UNESCAPED_UNICODE),
        'evidence_json' => json_encode(['body' => '<iframe src=javascript:alert(1)>'], JSON_UNESCAPED_UNICODE),
        'impact_analysis' => json_encode(['qms_documents' => ['conclusion' => 'possible']], JSON_UNESCAPED_UNICODE),
        'analysis_rule_version' => 'reg-impact-v1',
        'analysis_confidence' => 0.5,
        'analysis_rationale' => '规则初判，需人工确认',
        'graph_snapshot_hash' => $graphHash,
        'review_status' => $status,
        'reviewed_by' => 'promotion-qm',
        'reviewed_at' => $now,
        'review_comment' => '质量负责人确认相关，允许进入正式影响评估',
        'publish' => $publish,
        'soft_delete' => $softDelete,
        'created' => $now,
        'modified' => $now,
    ];
}

function promotion_insert_candidate(array $candidate): void
{
    Db::name('qms_external_change_candidates')->insert($candidate);
}

/** @return array{process: resource, pipes: array<int, resource>} */
function promotion_start_worker(string $candidateId, string $actorId, string $barrier, int $worker): array
{
    $command = implode(' ', array_map('escapeshellarg', [
        PHP_BINARY,
        __FILE__,
        '--worker',
        $candidateId,
        $actorId,
        $barrier,
        (string)$worker,
    ]));
    $pipes = [];
    $process = proc_open($command, [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes);
    promotion_assert(is_resource($process), '必须能启动真实并发晋升进程');
    fclose($pipes[0]);

    return ['process' => $process, 'pipes' => $pipes];
}

/** @return array<string, mixed> */
function promotion_finish_worker(array $worker): array
{
    $stdout = stream_get_contents($worker['pipes'][1]);
    $stderr = stream_get_contents($worker['pipes'][2]);
    fclose($worker['pipes'][1]);
    fclose($worker['pipes'][2]);
    $exit = proc_close($worker['process']);
    promotion_assert($exit === 0, '并发晋升子进程必须成功：' . trim((string)$stderr));
    $decoded = json_decode(trim((string)$stdout), true);
    promotion_assert(is_array($decoded), '并发晋升子进程必须返回 JSON');

    return $decoded;
}

if (($argv[1] ?? '') === '--worker') {
    $candidateId = (string)($argv[2] ?? '');
    $actorId = (string)($argv[3] ?? '');
    $barrier = (string)($argv[4] ?? '');
    $worker = (string)($argv[5] ?? '');
    Session::set('user', ['id' => $actorId, 'role' => 'quality_manager']);
    file_put_contents($barrier . '.' . $worker . '.ready', 'ready');
    $deadline = microtime(true) + 10.0;
    while (!is_file($barrier . '.go') && microtime(true) < $deadline) {
        usleep(10000);
    }
    if (!is_file($barrier . '.go')) {
        fwrite(STDERR, 'worker barrier timeout');
        exit(3);
    }
    try {
        $event = ExternalChangeEventService::promoteRegulatoryCandidate($candidateId, $actorId);
        echo json_encode(['event_id' => (string)$event->id], JSON_UNESCAPED_UNICODE) . PHP_EOL;
        exit(0);
    } catch (Throwable $exception) {
        fwrite(STDERR, $exception::class . ': ' . $exception->getMessage());
        exit(2);
    }
}

promotion_assert(
    method_exists(ExternalChangeEventService::class, 'promoteRegulatoryCandidate'),
    'ExternalChangeEventService 必须提供候选晋升 API'
);

$companyId = trim((string)Config::get('qms.company_id'));
$otherCompanyId = '99999999-9999-4999-8999-999999999999';
$actorId = 'promotion-qm';
$runToken = substr(hash('sha256', qms_uuid()), 0, 10);
$ids = [];
$eventIds = [];
$barriers = [];

Db::execute("CREATE TABLE IF NOT EXISTS `field_change_logs` (
  `id` varchar(36) NOT NULL, `model_name` varchar(100) NOT NULL,
  `record_id` varchar(36) NOT NULL, `field_name` varchar(100) NOT NULL,
  `old_value` text, `new_value` text, `changed_by` varchar(36) DEFAULT NULL,
  `changed_at` datetime NOT NULL, PRIMARY KEY (`id`),
  KEY `record_lookup` (`model_name`,`record_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

try {
    Session::set('user', ['id' => $actorId, 'role' => 'quality_manager']);

    foreach (['pending', 'deferred', 'confirmed_not_applicable'] as $status) {
        $id = 'prom-' . substr($status, 0, 6) . '-' . $runToken;
        $ids[] = $id;
        promotion_insert_candidate(promotion_candidate($id, $companyId, $status));
        promotion_throws(
            fn () => ExternalChangeEventService::promoteRegulatoryCandidate($id, $actorId),
            $status . ' 候选不得晋升'
        );
        promotion_assert(
            (string)Db::name('qms_external_change_candidates')->where('id', $id)->value('review_status') === $status,
            $status . ' 拒绝后状态不得变化'
        );
    }

    $permissionId = 'prom-perm-' . $runToken;
    $ids[] = $permissionId;
    promotion_insert_candidate(promotion_candidate($permissionId, $companyId));
    foreach (['admin', 'staff', 'auditor', 'department_head'] as $role) {
        Session::set('user', ['id' => 'promotion-' . $role, 'role' => $role]);
        promotion_throws(
            fn () => ExternalChangeEventService::promoteRegulatoryCandidate($permissionId, 'promotion-' . $role),
            $role . ' 不得晋升候选'
        );
    }
    Session::set('user', ['id' => $actorId, 'role' => 'quality_manager']);
    foreach (['', ' bad', str_repeat('x', 37), 'bad actor'] as $invalidActor) {
        promotion_throws(
            fn () => ExternalChangeEventService::promoteRegulatoryCandidate($permissionId, $invalidActor),
            'actor 必须为 1–36 位安全标识'
        );
    }
    promotion_throws(
        fn () => ExternalChangeEventService::promoteRegulatoryCandidate($permissionId, 'different-qm'),
        'actor 必须与当前 Session 用户一致'
    );

    $graphHash = hash('sha256', 'promotion-graph-' . $runToken);
    $successId = 'prom-success-' . $runToken;
    $ids[] = $successId;
    promotion_insert_candidate(promotion_candidate($successId, $companyId, graphHash: $graphHash));
    $event = ExternalChangeEventService::promoteRegulatoryCandidate($successId, $actorId);
    $eventIds[] = (string)$event->id;
    $eventRow = Db::name('qms_external_change_events')->where('id', (string)$event->id)->find();
    promotion_assert(is_array($eventRow), 'confirmed_applicable 应创建正式外部变更事件');
    promotion_assert((string)$eventRow['status'] === 'registered', '晋升事件初始状态必须为 registered');
    promotion_assert((string)$eventRow['source_kind'] === 'samr', 'SAMR 来源必须映射为 samr');
    promotion_assert((string)$eventRow['source_name'] === (string)promotion_candidate($successId, $companyId)['title'], '候选标题必须映射到 source_name');
    promotion_assert((string)$eventRow['announcement_number'] !== '', '候选文号必须映射到正式事件');
    promotion_assert((string)$eventRow['published_date'] === '2026-07-01', '发布日期必须映射');
    promotion_assert((string)$eventRow['effective_date'] === '2026-08-01', '生效日期必须映射');
    promotion_assert((string)$eventRow['graph_snapshot_hash'] === $graphHash, '候选图快照必须保留');
    promotion_assert((string)$eventRow['created_by'] === $actorId, '事件 created_by 必须可追溯到 actor');
    promotion_assert((string)$eventRow['modified_by'] === $actorId, '事件 modified_by 必须可追溯到 actor');
    $summary = (string)$eventRow['event_summary'];
    promotion_assert(str_starts_with($summary, '机器发现/规则初判，待正式影响评估'), '事件摘要必须使用固定机器初判前缀');
    promotion_assert(str_contains($summary, '来源=samr_rkjcs_notice'), '事件摘要必须包含来源 key');
    promotion_assert(str_contains($summary, '文号='), '事件摘要必须包含文号证据');
    promotion_assert(str_contains($summary, '候选证据'), '事件摘要必须明确证据仍为候选');
    promotion_assert(str_contains($summary, '不得视为适用性正式评估'), '事件摘要不得声称已正式完成适用性评估');
    promotion_assert(!str_contains($summary, '<'), '事件摘要不得由实体解码恢复 HTML 执行标签');
    promotion_assert(!str_contains($summary, "\n"), '事件摘要必须规范化换行');

    $candidateRow = Db::name('qms_external_change_candidates')->where('id', $successId)->find();
    promotion_assert((string)$candidateRow['review_status'] === 'promoted', '成功后候选状态必须为 promoted');
    promotion_assert((string)$candidateRow['promoted_event_id'] === (string)$event->id, '成功后必须回写唯一事件 ID');
    promotion_assert((string)$candidateRow['promoted_at'] !== '', '成功后必须记录 promoted_at');
    promotion_assert($candidateRow['promotion_error_summary'] === null, '成功后必须清空 promotion_error_summary');
    promotion_assert((string)$candidateRow['review_comment'] === '质量负责人确认相关，允许进入正式影响评估', '人工复核理由必须保留');

    $promotionFields = Db::name('field_change_logs')
        ->where('model_name', 'QmsExternalChangeCandidate')
        ->where('record_id', $successId)
        ->column('field_name');
    foreach (['review_status', 'promoted_event_id', 'promoted_at'] as $field) {
        promotion_assert(in_array($field, $promotionFields, true), '候选晋升字段必须进入 fail-closed 字段审计：' . $field);
    }
    $history = Db::name('histories')
        ->where('model_name', 'QmsExternalChangeCandidate')
        ->where('record_id', $successId)
        ->where('action', 'promoteRegulatoryCandidate')
        ->select()
        ->toArray();
    promotion_assert(count($history) === 1, '成功晋升必须只写一条动作 History');
    promotion_assert((string)$history[0]['user_id'] === $actorId, 'History user_id 必须为 actor');
    promotion_assert(str_contains((string)$history[0]['details'], 'candidate_id=' . $successId), 'History 必须记录 candidate id');
    promotion_assert(str_contains((string)$history[0]['details'], 'event_id=' . (string)$event->id), 'History 必须记录 event id');
    promotion_assert(str_contains((string)$history[0]['details'], 'actor_id=' . $actorId), 'History 必须记录 actor');
    promotion_assert(!str_contains((string)$history[0]['details'], '官方候选证据'), 'History 不得包含证据原文');

    $idempotent = ExternalChangeEventService::promoteRegulatoryCandidate($successId, $actorId);
    promotion_assert((string)$idempotent->id === (string)$event->id, '顺序重复晋升必须返回同一 event_id');
    promotion_assert(Db::name('qms_external_change_events')->where('id', (string)$event->id)->count() === 1, '顺序重复晋升不得创建第二事件');
    promotion_assert(
        Db::name('histories')->where('model_name', 'QmsExternalChangeCandidate')->where('record_id', $successId)->where('action', 'promoteRegulatoryCandidate')->count() === 1,
        '顺序重复晋升不得创建第二条成功 History'
    );

    foreach ([
        'cnas_lab_notice' => 'cnas',
        'cnas_lab_rules' => 'cnas',
        'xinjiang_samr_notice' => 'samr',
        'cma_capability_query' => 'samr',
        'unknown_future_source' => 'other',
    ] as $sourceKey => $expectedKind) {
        $id = 'prom-map-' . substr(hash('sha256', $sourceKey . $runToken), 0, 12);
        $ids[] = $id;
        $candidate = promotion_candidate($id, $companyId, sourceKey: $sourceKey, graphHash: 'invalid-hash');
        if ($sourceKey === 'unknown_future_source') {
            $candidate['source_url'] = 'https://official.example.invalid/notice';
            $candidate['normalized_url'] = $candidate['source_url'];
        }
        promotion_insert_candidate($candidate);
        $mappedEvent = ExternalChangeEventService::promoteRegulatoryCandidate($id, $actorId);
        $eventIds[] = (string)$mappedEvent->id;
        promotion_assert((string)$mappedEvent->source_kind === $expectedKind, $sourceKey . ' 来源类别映射必须确定');
        promotion_assert(preg_match('/\A[a-f0-9]{64}\z/', (string)$mappedEvent->graph_snapshot_hash) === 1, '无效候选图快照必须规范回退');
        if ($sourceKey === 'unknown_future_source') {
            promotion_assert($mappedEvent->source_url === null, '未知来源的任意 HTTPS 地址不得晋升为官方 URL');
        }
    }

    foreach ([
        'evil-host' => 'https://evil.example/notice',
        'userinfo' => 'https://user:pass@www.samr.gov.cn/notice',
        'bad-port' => 'https://www.samr.gov.cn:444/notice',
    ] as $suffix => $unsafeUrl) {
        $id = 'prom-url-' . substr(hash('sha256', $suffix . $runToken), 0, 12);
        $ids[] = $id;
        $candidate = promotion_candidate($id, $companyId);
        $candidate['source_url'] = $unsafeUrl;
        $candidate['normalized_url'] = $unsafeUrl;
        promotion_insert_candidate($candidate);
        $unsafeEvent = ExternalChangeEventService::promoteRegulatoryCandidate($id, $actorId);
        $eventIds[] = (string)$unsafeEvent->id;
        promotion_assert($unsafeEvent->source_url === null, $suffix . ' 不得写入正式事件官方 URL');
        promotion_assert(
            !str_contains((string)$unsafeEvent->event_summary, $unsafeUrl),
            $suffix . ' 被拒绝的 URL 不得旁路进入事件摘要'
        );
    }

    foreach ([
        ['scope-other', $otherCompanyId, 1, 0],
        ['scope-hidden', $companyId, 0, 0],
        ['scope-deleted', $companyId, 1, 1],
    ] as [$suffix, $scopeCompany, $publish, $softDelete]) {
        $id = 'prom-' . $suffix . '-' . $runToken;
        $ids[] = $id;
        promotion_insert_candidate(promotion_candidate($id, $scopeCompany, publish: $publish, softDelete: $softDelete));
        promotion_throws(
            fn () => ExternalChangeEventService::promoteRegulatoryCandidate($id, $actorId),
            $suffix . ' 候选必须拒绝'
        );
    }

    $missingLinkId = 'prom-badlink-' . $runToken;
    $ids[] = $missingLinkId;
    $missingLink = promotion_candidate($missingLinkId, $companyId, 'promoted');
    $missingLink['promoted_event_id'] = 'missing-event-' . $runToken;
    $missingLink['promoted_at'] = date('Y-m-d H:i:s');
    promotion_insert_candidate($missingLink);
    promotion_throws(fn () => ExternalChangeEventService::promoteRegulatoryCandidate($missingLinkId, $actorId), '缺失关联事件必须 fail closed');

    foreach ([
        'linked-other' => [$otherCompanyId, 1, 0],
        'linked-hidden' => [$companyId, 0, 0],
        'linked-deleted' => [$companyId, 1, 1],
    ] as $suffix => [$linkedCompany, $linkedPublish, $linkedSoftDelete]) {
        $linkedEventId = 'prom-event-' . substr(hash('sha256', $suffix . $runToken), 0, 12);
        $eventIds[] = $linkedEventId;
        Db::name('qms_external_change_events')->insert([
            'id' => $linkedEventId,
            'company_id' => $linkedCompany,
            'event_code' => 'QMS-PROM-' . strtoupper(substr(hash('sha256', $suffix . $runToken), 0, 12)),
            'source_kind' => 'samr',
            'source_name' => '无效关联事件 ' . $suffix,
            'event_summary' => '仅用于晋升边界测试',
            'status' => 'registered',
            'publish' => $linkedPublish,
            'soft_delete' => $linkedSoftDelete,
            'created' => date('Y-m-d H:i:s'),
            'modified' => date('Y-m-d H:i:s'),
        ]);
        $linkedCandidateId = 'prom-' . substr(hash('sha256', 'candidate-' . $suffix . $runToken), 0, 16);
        $ids[] = $linkedCandidateId;
        $linkedCandidate = promotion_candidate($linkedCandidateId, $companyId, 'promoted');
        $linkedCandidate['promoted_event_id'] = $linkedEventId;
        $linkedCandidate['promoted_at'] = date('Y-m-d H:i:s');
        promotion_insert_candidate($linkedCandidate);
        promotion_throws(
            fn () => ExternalChangeEventService::promoteRegulatoryCandidate($linkedCandidateId, $actorId),
            $suffix . ' 的已晋升关联事件必须 fail closed'
        );
    }

    $statusOnlyId = 'prom-status-' . $runToken;
    $ids[] = $statusOnlyId;
    promotion_insert_candidate(promotion_candidate($statusOnlyId, $companyId, 'promoted'));
    promotion_throws(fn () => ExternalChangeEventService::promoteRegulatoryCandidate($statusOnlyId, $actorId), 'promoted 无事件 ID 的矛盾状态必须 fail closed');

    $linkedWhileConfirmedId = 'prom-conflict-' . $runToken;
    $ids[] = $linkedWhileConfirmedId;
    $linkedWhileConfirmed = promotion_candidate($linkedWhileConfirmedId, $companyId);
    $linkedWhileConfirmed['promoted_event_id'] = (string)$event->id;
    promotion_insert_candidate($linkedWhileConfirmed);
    promotion_throws(fn () => ExternalChangeEventService::promoteRegulatoryCandidate($linkedWhileConfirmedId, $actorId), '非 promoted 带事件 ID 的矛盾状态必须 fail closed');

    $eventFailureId = 'prom-evfail-' . $runToken;
    $ids[] = $eventFailureId;
    promotion_insert_candidate(promotion_candidate($eventFailureId, $companyId));
    $eventsBeforeFailure = Db::name('qms_external_change_events')->count();
    $historyBeforeFailure = Db::name('histories')->where('record_id', $eventFailureId)->count();
    promotion_throws(
        fn () => PromotionEventFailureService::promoteRegulatoryCandidate($eventFailureId, $actorId),
        '建单失败必须向上返回安全错误',
        'do-not-leak'
    );
    $failedCandidate = Db::name('qms_external_change_candidates')->where('id', $eventFailureId)->find();
    promotion_assert((string)$failedCandidate['review_status'] === 'confirmed_applicable', '建单失败时候选必须保持 confirmed_applicable');
    promotion_assert($failedCandidate['promoted_event_id'] === null && $failedCandidate['promoted_at'] === null, '建单失败不得留下晋升关联');
    promotion_assert(Db::name('qms_external_change_events')->count() === $eventsBeforeFailure, '建单失败不得新增事件');
    promotion_assert(Db::name('histories')->where('record_id', $eventFailureId)->count() === $historyBeforeFailure, '建单失败不得留下成功 History');

    $auditFailureId = 'prom-audfail-' . $runToken;
    $ids[] = $auditFailureId;
    promotion_insert_candidate(promotion_candidate($auditFailureId, $companyId));
    $eventsBeforeAuditFailure = Db::name('qms_external_change_events')->count();
    promotion_throws(
        fn () => PromotionAuditFailureService::promoteRegulatoryCandidate($auditFailureId, $actorId),
        '动作审计失败必须整笔回滚',
        'do-not-leak'
    );
    $auditFailedCandidate = Db::name('qms_external_change_candidates')->where('id', $auditFailureId)->find();
    promotion_assert((string)$auditFailedCandidate['review_status'] === 'confirmed_applicable', '动作审计失败时候选状态必须回滚');
    promotion_assert($auditFailedCandidate['promoted_event_id'] === null && $auditFailedCandidate['promoted_at'] === null, '动作审计失败不得留下关联');
    promotion_assert(Db::name('qms_external_change_events')->count() === $eventsBeforeAuditFailure, '动作审计失败必须回滚正式事件');
    promotion_assert(Db::name('histories')->where('record_id', $auditFailureId)->count() === 0, '动作审计失败不得留下成功 History');

    $concurrentId = 'prom-concur-' . $runToken;
    $ids[] = $concurrentId;
    promotion_insert_candidate(promotion_candidate($concurrentId, $companyId));
    $barrier = sys_get_temp_dir() . '/qms-promotion-' . $runToken;
    $barriers[] = $barrier;
    $worker1 = promotion_start_worker($concurrentId, $actorId, $barrier, 1);
    $worker2 = promotion_start_worker($concurrentId, $actorId, $barrier, 2);
    $deadline = microtime(true) + 10.0;
    while ((!is_file($barrier . '.1.ready') || !is_file($barrier . '.2.ready')) && microtime(true) < $deadline) {
        usleep(10000);
    }
    promotion_assert(is_file($barrier . '.1.ready') && is_file($barrier . '.2.ready'), '两个并发进程必须先到达同一屏障');
    file_put_contents($barrier . '.go', 'go');
    $result1 = promotion_finish_worker($worker1);
    $result2 = promotion_finish_worker($worker2);
    promotion_assert((string)$result1['event_id'] !== '', '并发进程必须返回 event_id');
    promotion_assert((string)$result1['event_id'] === (string)$result2['event_id'], '并发晋升必须返回同一 event_id');
    $eventIds[] = (string)$result1['event_id'];
    promotion_assert(
        Db::name('qms_external_change_events')->where('id', (string)$result1['event_id'])->count() === 1,
        '并发晋升只能创建一个正式事件'
    );
    promotion_assert(
        Db::name('histories')->where('model_name', 'QmsExternalChangeCandidate')->where('record_id', $concurrentId)->where('action', 'promoteRegulatoryCandidate')->count() === 1,
        '并发晋升只能创建一条成功动作审计'
    );
} finally {
    Session::delete('user');
    foreach ($barriers as $barrier) {
        @unlink($barrier . '.1.ready');
        @unlink($barrier . '.2.ready');
        @unlink($barrier . '.go');
    }
    if ($ids !== []) {
        $linkedEventIds = Db::name('qms_external_change_candidates')->whereIn('id', $ids)->column('promoted_event_id');
        foreach ($linkedEventIds as $linkedEventId) {
            if (trim((string)$linkedEventId) !== '') {
                $eventIds[] = trim((string)$linkedEventId);
            }
        }
        Db::name('field_change_logs')->where('model_name', 'QmsExternalChangeCandidate')->whereIn('record_id', $ids)->delete();
        Db::name('histories')->where('model_name', 'QmsExternalChangeCandidate')->whereIn('record_id', $ids)->delete();
        Db::name('qms_external_change_candidates')->whereIn('id', $ids)->delete();
    }
    if ($eventIds !== []) {
        Db::name('field_change_logs')->where('model_name', 'QmsExternalChangeEvent')->whereIn('record_id', array_values(array_unique($eventIds)))->delete();
        Db::name('qms_external_change_events')->whereIn('id', array_values(array_unique($eventIds)))->delete();
    }
}

echo "regulatory_promotion_smoke passed\n";
