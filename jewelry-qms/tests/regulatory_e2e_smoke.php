<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\command\MonitorRegulatoryChanges;
use app\controller\PlanningRegulatoryMonitor;
use app\middleware\AuditLog;
use app\service\regulatory\RegulatoryCandidateReviewService;
use app\service\regulatory\RegulatoryMonitorService;
use think\console\Input;
use think\console\Output;
use think\facade\Config;
use think\facade\Db;
use think\facade\Session;

final class RegulatoryE2ESmokeAssertionFailed extends RuntimeException
{
}

function regulatory_e2e_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RegulatoryE2ESmokeAssertionFailed($message);
    }
}

/** @return array{0: int, 1: string} */
function regulatory_e2e_run_command(think\App $app, string $fixtureDir, int &$fixtureFetchCalls): array
{
    $command = new MonitorRegulatoryChanges(
        static function (array $result): void {
            // 端到端验收只验证成功链，不在共享测试库生成通知。
        },
        static function (?callable $fixtureFetcher, bool $dryRun) use (&$fixtureFetchCalls): RegulatoryMonitorService {
            regulatory_e2e_assert(is_callable($fixtureFetcher), '端到端命令必须使用离线 fixture fetcher');
            regulatory_e2e_assert($dryRun === false, '端到端持久化链不得误运行为 dry-run');

            return new RegulatoryMonitorService(sourceFetcher: static function (array $source) use (
                $fixtureFetcher,
                &$fixtureFetchCalls
            ): string {
                $fixtureFetchCalls++;
                return $fixtureFetcher($source);
            });
        }
    );
    $command->setApp($app);
    $output = new Output('buffer');
    $exitCode = $command->run(new Input([
        '--source=samr_rkjcs_notice',
        '--fixture-dir=' . $fixtureDir,
    ]), $output);

    return [$exitCode, $output->fetch()];
}

function regulatory_e2e_run_id(string $output): string
{
    regulatory_e2e_assert(
        preg_match('/\brun_id:\s*([0-9a-f-]{36})\b/i', $output, $matches) === 1,
        '命令输出必须包含 run_id'
    );

    return (string)$matches[1];
}

/** @return array<string, mixed> */
function regulatory_e2e_json(mixed $value, string $field): array
{
    if (is_array($value)) {
        return $value;
    }
    regulatory_e2e_assert(is_string($value) && $value !== '', $field . ' 必须是非空 JSON');
    try {
        $decoded = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        throw new RegulatoryE2ESmokeAssertionFailed($field . ' 必须是有效 JSON', 0, $exception);
    }
    regulatory_e2e_assert(is_array($decoded), $field . ' 必须解析为数组');

    return $decoded;
}

function regulatory_e2e_promote(think\App $app, string $candidateId): think\Response
{
    $request = (new app\Request())
        ->setMethod('POST')
        ->setController('PlanningRegulatoryMonitor')
        ->setAction('promote')
        ->withPost([
            'id' => $candidateId,
            // 伪造字段必须被控制器忽略，actor 只能来自 Session。
            'actor_id' => 'forged-e2e-actor',
        ]);
    $app->instance('request', $request);
    $controller = new PlanningRegulatoryMonitor($app);

    return (new AuditLog())->handle($request, static fn () => $controller->promote());
}

function regulatory_e2e_export(think\App $app, string $candidateId): think\Response
{
    $request = (new app\Request())
        ->setMethod('GET')
        ->setController('PlanningRegulatoryMonitor')
        ->setAction('export')
        ->withGet(['id' => $candidateId]);
    $app->instance('request', $request);

    return (new PlanningRegulatoryMonitor($app))->export();
}

$app = new think\App();
$app->initialize();

$previousAppEnv = getenv('APP_ENV');
$previousQms = (array)Config::get('qms', []);
$proxyVariables = ['HTTP_PROXY', 'HTTPS_PROXY', 'ALL_PROXY', 'http_proxy', 'https_proxy', 'all_proxy', 'NO_PROXY', 'no_proxy'];
$previousProxyValues = [];
foreach ($proxyVariables as $variable) {
    $previousProxyValues[$variable] = getenv($variable);
}

$token = strtolower(bin2hex(random_bytes(6)));
$fixtureDir = sys_get_temp_dir() . '/regulatory-e2e-' . $token;
$fixturePath = $fixtureDir . '/samr_rkjcs_notice.html';
$announcementNumber = 'E2E-' . strtoupper($token);
$articleId = 'art_e2e_' . $token;
$canonicalUrl = 'https://www.samr.gov.cn/zw/zfxxgk/fdzdgknr/rkjcs/art/2026/' . $articleId . '.html';
$companyId = trim((string)Config::get('qms.company_id'));
$actorId = 'regulatory-e2e-qm';
$runIds = [];
$candidateIds = [];
$eventIds = [];
$fixtureFetchCalls = 0;
$failure = null;

try {
    regulatory_e2e_assert($companyId !== '', '端到端测试需要隔离库 company_id');
    regulatory_e2e_assert(mkdir($fixtureDir, 0700), '必须能创建端到端临时夹具目录');
    $fixture = file_get_contents(__DIR__ . '/fixtures/regulatory/samr_one_list_one_library.html');
    regulatory_e2e_assert(is_string($fixture) && $fixture !== '', '必须能读取一单一库基准夹具');
    $fixture = str_replace(
        [
            'art_ea608a4306a34b8bb1f3c548a91cffa8',
            '2026年第14号',
        ],
        [
            $articleId,
            $announcementNumber,
        ],
        $fixture
    );
    regulatory_e2e_assert(
        file_put_contents($fixturePath, $fixture, LOCK_EX) === strlen($fixture),
        '必须完整写入一单一库端到端夹具'
    );

    putenv('APP_ENV=test');
    foreach (['HTTP_PROXY', 'HTTPS_PROXY', 'ALL_PROXY', 'http_proxy', 'https_proxy', 'all_proxy'] as $variable) {
        putenv($variable . '=http://127.0.0.1:9');
    }
    putenv('NO_PROXY=');
    putenv('no_proxy=');
    $qms = $previousQms;
    $qms['regulatory_monitor']['enabled'] = true;
    Config::set($qms, 'qms');

    [$firstExit, $firstOutput] = regulatory_e2e_run_command($app, $fixtureDir, $fixtureFetchCalls);
    regulatory_e2e_assert($firstExit === 0, '一单一库离线夹具命令必须成功：' . trim($firstOutput));
    regulatory_e2e_assert($fixtureFetchCalls === 1, '首次命令必须且只能读取一次本地 fixture');
    $firstRunId = regulatory_e2e_run_id($firstOutput);
    $runIds[] = $firstRunId;
    $firstRun = Db::name('qms_regulatory_monitor_runs')->where('id', $firstRunId)->find();
    regulatory_e2e_assert(is_array($firstRun), '命令必须持久化监测 run');
    regulatory_e2e_assert((string)$firstRun['status'] === 'completed', '首次运行状态必须是 completed');
    $firstResult = regulatory_e2e_json($firstRun['result_json'] ?? null, 'result_json');
    regulatory_e2e_assert((int)($firstResult['candidate_new_count'] ?? -1) === 1, '首次运行必须新建一条候选');
    regulatory_e2e_assert((int)($firstResult['candidate_existing_count'] ?? -1) === 0, '首次运行不得命中旧候选');

    $candidates = Db::name('qms_external_change_candidates')
        ->where('company_id', $companyId)
        ->where('monitor_run_id', $firstRunId)
        ->where('source_key', 'samr_rkjcs_notice')
        ->select()
        ->toArray();
    regulatory_e2e_assert(count($candidates) === 1, '首次运行必须仅产生一条一单一库候选');
    $candidate = $candidates[0];
    $candidateId = (string)$candidate['id'];
    $candidateIds[] = $candidateId;
    regulatory_e2e_assert((string)$candidate['review_status'] === 'pending', '新候选必须等待人工复核');
    regulatory_e2e_assert((string)$candidate['normalized_url'] === $canonicalUrl, '候选必须保留官方来源链接');

    $impactAnalysis = regulatory_e2e_json($candidate['impact_analysis'] ?? null, 'impact_analysis');
    $impactKeys = [
        'cma_scope_mark',
        'qms_documents',
        'personnel_authorization',
        'equipment_calibration',
        'lims_rules',
        'training',
    ];
    $storedImpactKeys = array_keys($impactAnalysis);
    sort($storedImpactKeys, SORT_STRING);
    $expectedStoredImpactKeys = $impactKeys;
    sort($expectedStoredImpactKeys, SORT_STRING);
    regulatory_e2e_assert(
        $storedImpactKeys === $expectedStoredImpactKeys,
        '影响初判必须固定包含六类；MySQL JSON 存储不承诺对象键顺序'
    );
    $requiredHits = [
        'cma_scope_mark' => 'REG-CMA-ONE-LIST-DIRECT-001',
        'qms_documents' => 'REG-QMS-ONE-LIST-INFERENCE-001',
        'personnel_authorization' => 'REG-PER-ONE-LIST-INFERENCE-001',
        'lims_rules' => 'REG-LIMS-ONE-LIST-INFERENCE-001',
    ];
    foreach ($requiredHits as $impactKey => $ruleId) {
        regulatory_e2e_assert(
            in_array((string)($impactAnalysis[$impactKey]['conclusion'] ?? ''), ['likely', 'possible'], true),
            '一单一库必须命中关键影响：' . $impactKey
        );
        regulatory_e2e_assert(
            in_array($ruleId, (array)($impactAnalysis[$impactKey]['rule_ids'] ?? []), true),
            '关键影响必须记录命中规则：' . $ruleId
        );
    }
    foreach (['equipment_calibration', 'training'] as $noMatchKey) {
        regulatory_e2e_assert(
            (string)($impactAnalysis[$noMatchKey]['conclusion'] ?? '') === 'no_match',
            '未命中类别必须保留 no_match：' . $noMatchKey
        );
    }

    Session::set('user', ['id' => $actorId, 'role' => 'quality_manager']);
    $reviewed = (new RegulatoryCandidateReviewService())->review(
        $candidateId,
        'confirmed_applicable',
        '质量负责人端到端夹具确认适用'
    );
    regulatory_e2e_assert((string)$reviewed->review_status === 'confirmed_applicable', '人工复核必须确认为相关');

    $promotionResponse = regulatory_e2e_promote($app, $candidateId);
    $promotedCandidate = Db::name('qms_external_change_candidates')->where('id', $candidateId)->find();
    $eventId = trim((string)($promotedCandidate['promoted_event_id'] ?? ''));
    regulatory_e2e_assert($eventId !== '', '复核候选必须晋升为正式外部变更事件');
    $eventIds[] = $eventId;
    regulatory_e2e_assert(
        (string)$promotionResponse->getHeader('Location') === '/planning/change-events/view?id=' . $eventId,
        '控制器晋升成功后必须跳转正式事件'
    );
    regulatory_e2e_assert(
        Db::name('qms_external_change_events')->where('id', $eventId)->count() === 1,
        '晋升必须仅创建一条正式外部变更事件'
    );
    regulatory_e2e_assert(
        (string)Db::name('qms_external_change_events')->where('id', $eventId)->value('created_by') === $actorId,
        '控制器晋升 actor 必须来自 Session'
    );
    regulatory_e2e_assert(
        Db::name('histories')
            ->where('model_name', 'QmsExternalChangeCandidate')
            ->where('record_id', $candidateId)
            ->where('action', 'promoteRegulatoryCandidate')
            ->count() === 1,
        '晋升必须仅保留一条成功动作审计'
    );

    $exportResponse = regulatory_e2e_export($app, $candidateId);
    $packet = json_decode($exportResponse->getContent(), true, flags: JSON_THROW_ON_ERROR);
    regulatory_e2e_assert((string)($packet['schema_version'] ?? '') === '1.0', '复核数据包必须是 schema 1.0');
    regulatory_e2e_assert((string)($packet['candidate']['id'] ?? '') === $candidateId, '复核数据包必须指向同一候选');
    regulatory_e2e_assert((string)($packet['review']['status'] ?? '') === 'promoted', '导出必须保留已晋升复核状态');
    regulatory_e2e_assert(array_keys((array)($packet['impact_assessment'] ?? [])) === $impactKeys, '导出数据包必须保留六类影响');
    regulatory_e2e_assert(
        str_contains((string)($packet['source']['evidence']['summary'] ?? ''), '一单一库'),
        '导出数据包必须保留一单一库来源证据'
    );

    [$secondExit, $secondOutput] = regulatory_e2e_run_command($app, $fixtureDir, $fixtureFetchCalls);
    regulatory_e2e_assert($secondExit === 0, '第二次离线命令必须成功：' . trim($secondOutput));
    regulatory_e2e_assert($fixtureFetchCalls === 2, '第二次命令必须仍只读本地 fixture');
    $secondRunId = regulatory_e2e_run_id($secondOutput);
    $runIds[] = $secondRunId;
    $secondRun = Db::name('qms_regulatory_monitor_runs')->where('id', $secondRunId)->find();
    regulatory_e2e_assert(is_array($secondRun), '第二次命令必须持久化新 run');
    $secondResult = regulatory_e2e_json($secondRun['result_json'] ?? null, 'second_result_json');
    regulatory_e2e_assert((int)($secondResult['candidate_new_count'] ?? -1) === 0, '第二次运行不得创建重复候选');
    regulatory_e2e_assert((int)($secondResult['candidate_existing_count'] ?? -1) === 1, '第二次运行必须命中既有候选');
    regulatory_e2e_assert(
        Db::name('qms_external_change_candidates')
            ->where('company_id', $companyId)
            ->where('source_key', 'samr_rkjcs_notice')
            ->where('source_item_key', $announcementNumber)
            ->count() === 1,
        '一单一库条目版本链不得产生重复候选'
    );

    $retryResponse = regulatory_e2e_promote($app, $candidateId);
    regulatory_e2e_assert(
        (string)$retryResponse->getHeader('Location') === '/planning/change-events/view?id=' . $eventId,
        '重复控制器晋升必须返回同一事件'
    );
    regulatory_e2e_assert(
        Db::name('qms_external_change_events')->where('id', $eventId)->count() === 1,
        '第二次全链路验证不得产生重复事件'
    );
    regulatory_e2e_assert(
        Db::name('histories')
            ->where('model_name', 'QmsExternalChangeCandidate')
            ->where('record_id', $candidateId)
            ->where('action', 'promoteRegulatoryCandidate')
            ->count() === 1,
        '重复晋升不得产生重复成功审计'
    );
} catch (Throwable $exception) {
    $failure = $exception;
} finally {
    Session::delete('user');
    Config::set($previousQms, 'qms');
    if ($previousAppEnv === false) {
        putenv('APP_ENV');
    } else {
        putenv('APP_ENV=' . $previousAppEnv);
    }
    foreach ($previousProxyValues as $variable => $value) {
        if ($value === false) {
            putenv($variable);
        } else {
            putenv($variable . '=' . $value);
        }
    }

    $foundCandidates = Db::name('qms_external_change_candidates')
        ->where('company_id', $companyId)
        ->where('source_url', $canonicalUrl)
        ->select()
        ->toArray();
    foreach ($foundCandidates as $foundCandidate) {
        $foundCandidateId = trim((string)($foundCandidate['id'] ?? ''));
        $foundEventId = trim((string)($foundCandidate['promoted_event_id'] ?? ''));
        $foundRunId = trim((string)($foundCandidate['monitor_run_id'] ?? ''));
        if ($foundCandidateId !== '') {
            $candidateIds[] = $foundCandidateId;
        }
        if ($foundEventId !== '') {
            $eventIds[] = $foundEventId;
        }
        if ($foundRunId !== '') {
            $runIds[] = $foundRunId;
        }
    }
    $candidateIds = array_values(array_unique(array_filter($candidateIds)));
    $eventIds = array_values(array_unique(array_filter($eventIds)));
    $runIds = array_values(array_unique(array_filter($runIds)));
    if ($candidateIds !== []) {
        Db::name('field_change_logs')->where('model_name', 'QmsExternalChangeCandidate')->whereIn('record_id', $candidateIds)->delete();
        Db::name('histories')->where('model_name', 'QmsExternalChangeCandidate')->whereIn('record_id', $candidateIds)->delete();
        Db::name('qms_external_change_candidates')->whereIn('id', $candidateIds)->delete();
    }
    if ($eventIds !== []) {
        Db::name('field_change_logs')->where('model_name', 'QmsExternalChangeEvent')->whereIn('record_id', $eventIds)->delete();
        Db::name('qms_external_change_events')->whereIn('id', $eventIds)->delete();
    }
    if ($runIds !== []) {
        $notificationIds = Db::name('notifications')
            ->whereIn('notification_key', array_map(
                static fn (string $runId): string => 'regulatory_monitor_failure:' . $runId,
                $runIds
            ))
            ->column('id');
        if ($notificationIds !== []) {
            Db::name('notification_users')->whereIn('notification_id', $notificationIds)->delete();
            Db::name('notifications')->whereIn('id', $notificationIds)->delete();
        }
        Db::name('qms_regulatory_monitor_runs')->whereIn('id', $runIds)->delete();
    }
    @unlink($fixturePath);
    @rmdir($fixtureDir);
}

if ($failure instanceof RegulatoryE2ESmokeAssertionFailed) {
    fwrite(STDERR, $failure->getMessage() . PHP_EOL);
    exit(1);
}
if ($failure !== null) {
    fwrite(STDERR, $failure::class . ': ' . $failure->getMessage() . PHP_EOL);
    exit(1);
}

echo "regulatory_e2e_smoke passed\n";
