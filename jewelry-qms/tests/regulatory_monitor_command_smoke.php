<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\command\MonitorRegulatoryChanges;
use app\service\NotificationService;
use app\service\regulatory\RegulatoryCandidateService;
use app\service\regulatory\RegulatoryMonitorService;
use app\service\regulatory\RegulatoryTransactionAbortedException;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Config;

function monitor_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

/** @return array{0: int, 1: string} */
function run_monitor_command(
    think\App $app,
    array $arguments,
    ?callable $failureNotifier = null,
    ?callable $serviceFactory = null
): array
{
    $command = new MonitorRegulatoryChanges($failureNotifier, $serviceFactory);
    $command->setApp($app);
    $output = new Output('buffer');
    $exitCode = $command->run(new Input($arguments), $output);

    return [$exitCode, $output->fetch()];
}

function monitor_run_id(string $output): string
{
    monitor_assert(
        preg_match('/\brun_id:\s*([0-9a-f-]{36})\b/i', $output, $matches) === 1,
        'Command output must contain run_id'
    );
    return (string)$matches[1];
}

function cleanup_monitor_run(string $runId): void
{
    $notificationIds = Db::name('notifications')
        ->where('notification_key', 'regulatory_monitor_failure:' . $runId)
        ->column('id');
    if ($notificationIds !== []) {
        Db::name('notification_users')->whereIn('notification_id', $notificationIds)->delete();
        Db::name('notifications')->whereIn('id', $notificationIds)->delete();
    }
    Db::name('qms_external_change_candidates')->where('monitor_run_id', $runId)->delete();
    Db::name('qms_regulatory_monitor_runs')->where('id', $runId)->delete();
}

function fixture_html(string $listClass, string $slug, ?string $publishedDate = '2026-07-14'): string
{
    $date = $publishedDate === null ? '' : '<time datetime="' . $publishedDate . '">' . $publishedDate . '</time>';
    return '<!doctype html><html><head><meta charset="utf-8"></head><body><ul class="' . $listClass . '"><li>'
        . '<a href="/fixture/' . $slug . '.html">法规监测命令测试 ' . $slug . '</a>'
        . $date
        . '<span class="announcement-number">CMD-' . $slug . '</span>'
        . '<p class="summary">测试条目，需人工复核。</p>'
        . '</li></ul></body></html>';
}

function since_fixture_html(string $slug): string
{
    return '<!doctype html><html><head><meta charset="utf-8"></head><body><ul class="news-list">'
        . '<li><a href="/fixture/' . $slug . '-old.html">旧日期条目</a>'
        . '<time datetime="2026-01-31">2026-01-31</time>'
        . '<span class="announcement-number">SINCE-OLD-' . $slug . '</span></li>'
        . '<li><a href="/fixture/' . $slug . '-boundary.html">边界日期条目</a>'
        . '<time datetime="2026-02-01">2026-02-01</time>'
        . '<span class="announcement-number">SINCE-BOUNDARY-' . $slug . '</span></li>'
        . '<li><a href="/fixture/' . $slug . '-undated.html">无发布日期条目</a>'
        . '<span class="announcement-number">SINCE-UNDATED-' . $slug . '</span></li>'
        . '<li><a href="/fixture/' . $slug . '-invalid-date.html">非法发布日期条目</a>'
        . '<time datetime="2025-99-99">2025-99-99</time>'
        . '<span class="announcement-number">SINCE-INVALID-' . $slug . '</span></li>'
        . '</ul></body></html>';
}

$app = new think\App();
$app->initialize();

$root = dirname(__DIR__);
$commandPath = $root . '/app/command/MonitorRegulatoryChanges.php';
$console = (string)file_get_contents($root . '/config/console.php');

if (!is_file($commandPath)) {
    fwrite(STDERR, "MonitorRegulatoryChanges command file is missing\n");
    exit(1);
}

$command = (string)file_get_contents($commandPath);
if (!str_contains($command, "setName('qms:monitor-regulatory-changes')")) {
    fwrite(STDERR, "Regulatory monitor command name is missing\n");
    exit(1);
}
if (!str_contains($console, 'app\\command\\MonitorRegulatoryChanges')) {
    fwrite(STDERR, "Regulatory monitor command is not registered\n");
    exit(1);
}

foreach (['source', 'since', 'dry-run', 'fixture-dir', 'scheduled'] as $option) {
    monitor_assert(
        str_contains($command, "addOption('{$option}'"),
        "Regulatory monitor command option is missing: {$option}"
    );
}

$runsBeforeInvalidInput = Db::name('qms_regulatory_monitor_runs')->count();
foreach ([
    ['--source='],
    ['--source=unknown_source'],
    ['--source=samr_rkjcs_notice,,cnas_lab_notice'],
    ['--since=2026-02-30'],
    ['--since=2026-2-03'],
    ['--since='],
] as $invalidArguments) {
    [$exitCode, $output] = run_monitor_command($app, $invalidArguments);
    monitor_assert($exitCode === 1, 'Invalid source/since input must exit 1');
    monitor_assert(trim($output) !== '', 'Invalid source/since input must explain the error');
    monitor_assert(
        Db::name('qms_regulatory_monitor_runs')->count() === $runsBeforeInvalidInput,
        'Invalid source/since input must fail before creating a run'
    );
}

$sinceDisposition = new ReflectionMethod(RegulatoryMonitorService::class, 'sinceDisposition');
$sinceDisposition->setAccessible(true);
$dispositionService = new RegulatoryMonitorService();
foreach ([null, '', '2025/01/01', '2025-99-99', '2025-02-29'] as $invalidPublishedDate) {
    monitor_assert(
        $sinceDisposition->invoke($dispositionService, $invalidPublishedDate, '2026-01-01')
            === 'included_missing_date_manual_confirmation',
        'Empty, malformed and calendar-invalid published dates must be kept for manual confirmation'
    );
}
monitor_assert(
    $sinceDisposition->invoke($dispositionService, '2025-12-31', '2026-01-01') === 'filtered_before_since',
    'Valid dates before since must be filtered'
);
monitor_assert(
    $sinceDisposition->invoke($dispositionService, '2026-01-01', '2026-01-01') === 'included',
    'Valid date equal to since must be included'
);

$fixtureDir = sys_get_temp_dir() . '/regulatory-command-fixture-' . str_replace('-', '', qms_uuid());
monitor_assert(mkdir($fixtureDir, 0700), 'Fixture test directory must be created');
$samrSlug = 'samr-' . substr(qms_uuid(), 0, 8);
$cnasSlug = 'cnas-' . substr(qms_uuid(), 0, 8);
file_put_contents(
    $fixtureDir . '/samr_rkjcs_notice.html',
    fixture_html('news-list', $samrSlug)
);
file_put_contents(
    $fixtureDir . '/cnas_lab_notice.html',
    fixture_html('notice-list', $cnasSlug)
);

$createdRunIds = [];
try {
    putenv('APP_ENV=production');
    [$exitCode, $output] = run_monitor_command($app, [
        '--source=samr_rkjcs_notice',
        '--fixture-dir=' . $fixtureDir,
    ]);
    monitor_assert($exitCode === 1, 'Non-test fixture-dir use must exit 1');
    monitor_assert(
        Db::name('qms_regulatory_monitor_runs')->count() === $runsBeforeInvalidInput,
        'Non-test fixture-dir must fail before creating a run'
    );

    putenv('APP_ENV=test');
    [$exitCode, $output] = run_monitor_command($app, [
        '--source=samr_rkjcs_notice',
        '--fixture-dir=' . $fixtureDir . '/../' . basename($fixtureDir),
    ]);
    monitor_assert($exitCode === 1, 'Fixture path traversal must exit 1');
    monitor_assert(
        Db::name('qms_regulatory_monitor_runs')->count() === $runsBeforeInvalidInput,
        'Fixture path traversal must fail before creating a run'
    );

    [$completedExit, $completedOutput] = run_monitor_command($app, [
        '--source=samr_rkjcs_notice',
        '--fixture-dir=' . $fixtureDir,
    ]);
    monitor_assert($completedExit === 0, 'Completed monitor run must exit 0');
    monitor_assert(str_contains($completedOutput, 'completed'), 'Completed output must show status');
    $completedRunId = monitor_run_id($completedOutput);
    $createdRunIds[] = $completedRunId;
    monitor_assert(
        Db::name('qms_regulatory_monitor_runs')->where('id', $completedRunId)->value('created_by') === null,
        'CLI/manual command without a web actor must keep created_by null'
    );
    monitor_assert(
        Db::name('qms_external_change_candidates')->where('monitor_run_id', $completedRunId)->count() === 1,
        'Fixture run must execute parsing and candidate recording'
    );

    [$manualExit, $manualOutput] = run_monitor_command($app, [
        '--source=cma_capability_query',
        '--fixture-dir=' . $fixtureDir,
    ]);
    monitor_assert($manualExit === 0, 'Manual-only fixture run must complete without a file');
    $manualRunId = monitor_run_id($manualOutput);
    $createdRunIds[] = $manualRunId;
    monitor_assert(
        Db::name('qms_external_change_candidates')->where('monitor_run_id', $manualRunId)->count() === 0,
        'Manual-only source must not read a fixture or create candidates'
    );

    [$partialExit, $partialOutput] = run_monitor_command($app, [
        '--source=samr_rkjcs_notice,xinjiang_samr_notice',
        '--fixture-dir=' . $fixtureDir,
        '--scheduled',
    ]);
    monitor_assert($partialExit === 2, 'Partial monitor run must exit 2');
    monitor_assert(str_contains($partialOutput, 'partial_failed'), 'Partial output must show status');
    $partialRunId = monitor_run_id($partialOutput);
    $createdRunIds[] = $partialRunId;
    monitor_assert(
        Db::name('qms_regulatory_monitor_runs')->where('id', $partialRunId)->value('trigger_mode') === 'scheduled',
        '--scheduled must persist scheduled trigger mode'
    );

    [$failedExit, $failedOutput] = run_monitor_command($app, [
        '--source=xinjiang_samr_notice',
        '--fixture-dir=' . $fixtureDir,
    ]);
    monitor_assert($failedExit === 1, 'Failed monitor run must exit 1');
    monitor_assert(str_contains($failedOutput, 'failed'), 'Failed output must show status');
    $failedRunId = monitor_run_id($failedOutput);
    $createdRunIds[] = $failedRunId;
    monitor_assert(
        Db::name('qms_regulatory_monitor_runs')->where('id', $failedRunId)->value('trigger_mode') === 'manual',
        'Default command trigger mode must be manual'
    );

    $sinceSlug = substr(str_replace('-', '', qms_uuid()), 0, 12);
    file_put_contents(
        $fixtureDir . '/samr_rkjcs_notice.html',
        since_fixture_html($sinceSlug)
    );
    [$sinceExit, $sinceOutput] = run_monitor_command($app, [
        '--source=samr_rkjcs_notice',
        '--since=2026-02-01',
        '--fixture-dir=' . $fixtureDir,
    ]);
    monitor_assert($sinceExit === 0, 'Since-filtered run must complete');
    $sinceRunId = monitor_run_id($sinceOutput);
    $createdRunIds[] = $sinceRunId;
    $sinceCandidates = Db::name('qms_external_change_candidates')
        ->where('monitor_run_id', $sinceRunId)
        ->order('title', 'asc')
        ->select()
        ->toArray();
    monitor_assert(count($sinceCandidates) === 3, 'Since filter must keep boundary, undated and invalid-date items');
    $sinceTitles = array_column($sinceCandidates, 'title');
    monitor_assert(in_array('边界日期条目', $sinceTitles, true), 'Since boundary date must be inclusive');
    monitor_assert(in_array('无发布日期条目', $sinceTitles, true), 'Undated item must not be silently dropped');
    monitor_assert(in_array('非法发布日期条目', $sinceTitles, true), 'Calendar-invalid item must not be silently dropped');
    monitor_assert(!in_array('旧日期条目', $sinceTitles, true), 'Older dated item must be filtered out');
    $undatedCandidate = array_values(array_filter(
        $sinceCandidates,
        static fn (array $candidate): bool => $candidate['title'] === '无发布日期条目'
    ))[0];
    $undatedEvidence = json_decode((string)$undatedCandidate['evidence_json'], true, 512, JSON_THROW_ON_ERROR);
    monitor_assert(
        ($undatedEvidence['monitor_filter']['disposition'] ?? null) === 'included_missing_date_manual_confirmation',
        'Undated since item must carry an explicit manual-confirmation disposition'
    );
    $invalidCandidate = array_values(array_filter(
        $sinceCandidates,
        static fn (array $candidate): bool => $candidate['title'] === '非法发布日期条目'
    ))[0];
    $invalidEvidence = json_decode((string)$invalidCandidate['evidence_json'], true, 512, JSON_THROW_ON_ERROR);
    monitor_assert($invalidCandidate['published_date'] === null, 'Invalid observed date must be stored as null');
    monitor_assert(
        ($invalidEvidence['monitor_filter']['observed_published_date'] ?? null) === '2025-99-99',
        'Invalid observed date must be retained in monitor evidence'
    );
    $sinceResult = json_decode(
        (string)Db::name('qms_regulatory_monitor_runs')->where('id', $sinceRunId)->value('result_json'),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
    monitor_assert(
        ($sinceResult['sources'][0]['filter_skipped_count'] ?? null) === 1,
        'Run result must count items skipped by since'
    );
    monitor_assert(
        ($sinceResult['sources'][0]['missing_published_date_count'] ?? null) === 2,
        'Run result must count undated and invalid-date items kept for manual confirmation'
    );

    cleanup_monitor_run($sinceRunId);
    $createdRunIds = array_values(array_filter(
        $createdRunIds,
        static fn (string $runId): bool => $runId !== $sinceRunId
    ));
    $runsBeforeDryRun = Db::name('qms_regulatory_monitor_runs')->count();
    $candidatesBeforeDryRun = Db::name('qms_external_change_candidates')->count();
    $notificationsBeforeDryRun = Db::name('notifications')->count();
    [$dryExit, $dryOutput] = run_monitor_command($app, [
        '--source=samr_rkjcs_notice',
        '--since=2026-02-01',
        '--fixture-dir=' . $fixtureDir,
        '--dry-run',
    ]);
    monitor_assert($dryExit === 0, 'Successful dry-run must preserve the completed exit code');
    $dryRunId = monitor_run_id($dryOutput);
    monitor_assert(str_contains($dryOutput, 'DRY-RUN'), 'Dry-run output must state that it was not persisted');
    monitor_assert(str_contains($dryOutput, 'candidates_new: 3'), 'Dry-run must execute actual parsing and candidate rules');
    monitor_assert(
        Db::name('qms_regulatory_monitor_runs')->count() === $runsBeforeDryRun
        && Db::name('qms_regulatory_monitor_runs')->where('id', $dryRunId)->count() === 0,
        'Dry-run must leave zero monitor run residue'
    );
    monitor_assert(
        Db::name('qms_external_change_candidates')->count() === $candidatesBeforeDryRun,
        'Dry-run must leave zero candidate residue'
    );
    monitor_assert(
        Db::name('notifications')->count() === $notificationsBeforeDryRun,
        'Dry-run must leave zero notification residue'
    );

    $runsBeforeAbortedDryRun = Db::name('qms_regulatory_monitor_runs')->count();
    $candidatesBeforeAbortedDryRun = Db::name('qms_external_change_candidates')->count();
    $notificationsBeforeAbortedDryRun = Db::name('notifications')->count();
    $abortingServiceFactory = static function (?callable $sourceFetcher, bool $dryRun): RegulatoryMonitorService {
        monitor_assert($dryRun, 'Aborted transaction regression must exercise the command dry-run branch');
        return new RegulatoryMonitorService(
            sourceFetcher: $sourceFetcher,
            candidateService: new RegulatoryCandidateService(
                candidateInserter: static function (): never {
                    throw new RegulatoryTransactionAbortedException('forced ambient transaction abort');
                },
                ownsTransaction: false
            )
        );
    };
    [$abortedExit, $abortedOutput] = run_monitor_command(
        $app,
        [
            '--source=samr_rkjcs_notice',
            '--fixture-dir=' . $fixtureDir,
            '--dry-run',
        ],
        null,
        $abortingServiceFactory
    );
    monitor_assert($abortedExit === 1, 'Ambient transaction abort must propagate through monitor to command failure');
    monitor_assert(
        str_contains($abortedOutput, '法规监测未能完成'),
        'Command must report a system failure for an aborted ambient transaction'
    );
    monitor_assert(
        Db::name('qms_regulatory_monitor_runs')->count() === $runsBeforeAbortedDryRun,
        'Command finally must roll back the monitor run after an ambient transaction abort'
    );
    monitor_assert(
        Db::name('qms_external_change_candidates')->count() === $candidatesBeforeAbortedDryRun,
        'Command finally must leave zero candidate residue after an ambient transaction abort'
    );
    monitor_assert(
        Db::name('notifications')->count() === $notificationsBeforeAbortedDryRun,
        'Command must leave zero notification residue after an ambient transaction abort'
    );
} finally {
    putenv('APP_ENV=test');
    foreach ($createdRunIds as $createdRunId) {
        cleanup_monitor_run($createdRunId);
    }
    @unlink($fixtureDir . '/samr_rkjcs_notice.html');
    @unlink($fixtureDir . '/cnas_lab_notice.html');
    @rmdir($fixtureDir);
}

$companyId = (string)Config::get('qms.company_id');
$userIds = [
    'admin' => qms_uuid(),
    'quality_manager' => qms_uuid(),
    'disabled_admin' => qms_uuid(),
    'deleted_quality_manager' => qms_uuid(),
    'foreign_admin' => qms_uuid(),
    'foreign_quality_manager' => qms_uuid(),
];
$foreignCompanyId = qms_uuid();
$usernameSuffix = substr(str_replace('-', '', qms_uuid()), 0, 12);
Db::name('users')->insertAll([
    [
        'id' => $userIds['admin'], 'company_id' => $companyId,
        'username' => 'regmon_admin_' . $usernameSuffix, 'password' => 'test-only',
        'name' => '法规监测测试管理员', 'role' => 'admin', 'publish' => 1, 'soft_delete' => 0,
    ],
    [
        'id' => $userIds['quality_manager'], 'company_id' => $companyId,
        'username' => 'regmon_qm_' . $usernameSuffix, 'password' => 'test-only',
        'name' => '法规监测测试质量负责人', 'role' => 'quality_manager', 'publish' => 1, 'soft_delete' => 0,
    ],
    [
        'id' => $userIds['disabled_admin'], 'company_id' => $companyId,
        'username' => 'regmon_disabled_' . $usernameSuffix, 'password' => 'test-only',
        'name' => '已停用管理员', 'role' => 'admin', 'publish' => 0, 'soft_delete' => 0,
    ],
    [
        'id' => $userIds['deleted_quality_manager'], 'company_id' => $companyId,
        'username' => 'regmon_deleted_' . $usernameSuffix, 'password' => 'test-only',
        'name' => '已删除质量负责人', 'role' => 'quality_manager', 'publish' => 1, 'soft_delete' => 1,
    ],
    [
        'id' => $userIds['foreign_admin'], 'company_id' => $foreignCompanyId,
        'username' => 'regmon_foreign_admin_' . $usernameSuffix, 'password' => 'test-only',
        'name' => '外机构管理员', 'role' => 'admin', 'publish' => 1, 'soft_delete' => 0,
    ],
    [
        'id' => $userIds['foreign_quality_manager'], 'company_id' => $foreignCompanyId,
        'username' => 'regmon_foreign_qm_' . $usernameSuffix, 'password' => 'test-only',
        'name' => '外机构质量负责人', 'role' => 'quality_manager', 'publish' => 1, 'soft_delete' => 0,
    ],
]);

$notificationRunIds = [];
$notificationFixtureDir = sys_get_temp_dir()
    . '/token=REGULATORY_COMMAND_SECRET_' . substr(str_replace('-', '', qms_uuid()), 0, 8);
monitor_assert(mkdir($notificationFixtureDir, 0700), 'Notification fixture directory must be created');
file_put_contents(
    $notificationFixtureDir . '/samr_rkjcs_notice.html',
    fixture_html('news-list', 'notify-' . substr(qms_uuid(), 0, 8))
);
try {
    $notificationsBeforeCompleted = Db::name('notifications')->count();
    NotificationService::notifyRegulatoryMonitorFailure([
        'run_id' => qms_uuid(),
        'status' => 'completed',
        'success_count' => 1,
        'failure_count' => 0,
        'candidate_new_count' => 1,
        'candidate_existing_count' => 0,
    ]);
    monitor_assert(
        Db::name('notifications')->count() === $notificationsBeforeCompleted,
        'Completed run must not create a failure notification'
    );

    [$partialNotifyExit, $partialNotifyOutput] = run_monitor_command($app, [
        '--source=samr_rkjcs_notice,xinjiang_samr_notice',
        '--fixture-dir=' . $notificationFixtureDir,
    ]);
    monitor_assert($partialNotifyExit === 2, 'Partial command notification test must exit 2');
    monitor_assert(
        !str_contains($partialNotifyOutput, 'REGULATORY_COMMAND_SECRET'),
        'Command output must not leak fixture paths or sensitive exception detail'
    );
    $partialNotifyRunId = monitor_run_id($partialNotifyOutput);
    $notificationRunIds[] = $partialNotifyRunId;
    $partialNotification = Db::name('notifications')
        ->where('notification_key', 'regulatory_monitor_failure:' . $partialNotifyRunId)
        ->find();
    monitor_assert(is_array($partialNotification), 'Partial command must create a failure notification');
    $partialRecipients = Db::name('notification_users')
        ->where('notification_id', (string)$partialNotification['id'])
        ->column('user_id');
    monitor_assert(in_array($userIds['admin'], $partialRecipients, true), 'Admin must receive partial failure notification');
    monitor_assert(in_array($userIds['quality_manager'], $partialRecipients, true), 'Quality manager must receive partial failure notification');
    monitor_assert(!in_array($userIds['disabled_admin'], $partialRecipients, true), 'Disabled admin must not receive notification');
    monitor_assert(!in_array($userIds['deleted_quality_manager'], $partialRecipients, true), 'Soft-deleted quality manager must not receive notification');
    monitor_assert(!in_array($userIds['foreign_admin'], $partialRecipients, true), 'Foreign-company admin must not receive notification');
    monitor_assert(!in_array($userIds['foreign_quality_manager'], $partialRecipients, true), 'Foreign-company quality manager must not receive notification');
    $partialMessage = (string)$partialNotification['message'];
    foreach ([$partialNotifyRunId, 'partial_failed', '成功 1', '失败 1', '法规候选清单'] as $safeSummary) {
        monitor_assert(str_contains($partialMessage, $safeSummary), 'Failure notification must contain safe run summary');
    }
    foreach (['token', 'password', 'dsn', 'REGULATORY_COMMAND_SECRET', 'http://', 'https://'] as $secretFragment) {
        monitor_assert(
            stripos($partialMessage, $secretFragment) === false,
            'Failure notification leaked a sensitive fragment: ' . $secretFragment
        );
    }

    $failedNotificationRunId = qms_uuid();
    $notificationRunIds[] = $failedNotificationRunId;
    $failedResult = [
        'run_id' => $failedNotificationRunId,
        'status' => 'failed',
        'success_count' => 0,
        'failure_count' => 2,
        'candidate_new_count' => 0,
        'candidate_existing_count' => 0,
        'sources' => [[
            'error' => 'Authorization: Bearer raw-token password=raw-pass dsn=mysql://raw-secret',
        ]],
    ];
    NotificationService::notifyRegulatoryMonitorFailure($failedResult);
    NotificationService::notifyRegulatoryMonitorFailure($failedResult);
    monitor_assert(
        Db::name('notifications')
            ->where('notification_key', 'regulatory_monitor_failure:' . $failedNotificationRunId)
            ->count() === 1,
        'Repeated notification calls for one run must remain idempotent'
    );
    $failedNotificationMessage = (string)Db::name('notifications')
        ->where('notification_key', 'regulatory_monitor_failure:' . $failedNotificationRunId)
        ->value('message');
    foreach (['raw-token', 'raw-pass', 'raw-secret', 'authorization', 'password', 'dsn'] as $secretFragment) {
        monitor_assert(
            stripos($failedNotificationMessage, $secretFragment) === false,
            'Failed notification must ignore raw source exceptions: ' . $secretFragment
        );
    }

    [$notificationErrorExit, $notificationErrorOutput] = run_monitor_command(
        $app,
        [
            '--source=samr_rkjcs_notice,xinjiang_samr_notice',
            '--fixture-dir=' . $notificationFixtureDir,
        ],
        static fn (array $result): never => throw new RuntimeException(
            'notification failed token=NOTIFICATION_SECRET password=NOTIFICATION_PASSWORD'
        )
    );
    monitor_assert($notificationErrorExit === 2, 'Notification failure must preserve partial_failed exit 2');
    monitor_assert(str_contains($notificationErrorOutput, 'partial_failed'), 'Notification failure must preserve status output');
    monitor_assert(
        !str_contains($notificationErrorOutput, 'NOTIFICATION_SECRET')
        && !str_contains($notificationErrorOutput, 'NOTIFICATION_PASSWORD'),
        'Notification failure output must not expose exception details'
    );
    $notificationErrorRunId = monitor_run_id($notificationErrorOutput);
    $notificationRunIds[] = $notificationErrorRunId;
    monitor_assert(
        Db::name('qms_regulatory_monitor_runs')->where('id', $notificationErrorRunId)->value('status') === 'partial_failed',
        'Notification failure must not hide or roll back the persisted monitor result'
    );
} finally {
    foreach ($notificationRunIds as $notificationRunId) {
        cleanup_monitor_run($notificationRunId);
    }
    Db::name('users')->whereIn('id', array_values($userIds))->delete();
    @unlink($notificationFixtureDir . '/samr_rkjcs_notice.html');
    @rmdir($notificationFixtureDir);
}

$crontab = (string)file_get_contents($root . '/docker/crontab');
monitor_assert(
    preg_match('/^TZ=Asia\/Shanghai$/m', $crontab) === 1,
    'Regulatory monitor cron must explicitly use Asia/Shanghai'
);
monitor_assert(
    preg_match('/^0 9 1 \* \* (?<command>.+)$/m', $crontab, $cronMatches) === 1,
    'Regulatory monitor cron must run at 09:00 on day 1 of every month'
);
$cronCommand = (string)$cronMatches['command'];
foreach ([
    'cd /app',
    'case "${REGULATORY_MONITOR_ENABLED:-0}" in',
    '/usr/local/bin/php /app/think qms:monitor-regulatory-changes --scheduled',
    '>> /tmp/regulatory-monitor.log 2>&1',
] as $cronFragment) {
    monitor_assert(str_contains($cronCommand, $cronFragment), 'Cron command is missing: ' . $cronFragment);
}
monitor_assert(
    preg_match('/case "\$\{REGULATORY_MONITOR_ENABLED:-0\}" in (?<pattern>[^)]+)\)/', $cronCommand, $guardMatches) === 1,
    'Cron enable guard pattern must be parseable'
);
$enabledPattern = (string)$guardMatches['pattern'];
$guardScript = 'case "${REGULATORY_MONITOR_ENABLED:-0}" in '
    . $enabledPattern . ') printf executed ;; *) : ;; esac';
foreach ([null, '0', 'false', 'disabled', 'yes'] as $disabledValue) {
    $environment = $disabledValue === null ? [] : ['REGULATORY_MONITOR_ENABLED' => $disabledValue];
    $pipes = [];
    $process = proc_open(['/bin/sh', '-c', $guardScript], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $environment);
    monitor_assert(is_resource($process), 'Cron disabled guard process must start');
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    monitor_assert(proc_close($process) === 0 && $stderr === '', 'Cron disabled guard must execute cleanly');
    monitor_assert($stdout === '', 'Missing/disabled cron flag must not execute the command');
}
foreach (['1', 'true', 'TRUE'] as $enabledValue) {
    $pipes = [];
    $process = proc_open(
        ['/bin/sh', '-c', $guardScript],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        null,
        ['REGULATORY_MONITOR_ENABLED' => $enabledValue]
    );
    monitor_assert(is_resource($process), 'Cron enabled guard process must start');
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    monitor_assert(proc_close($process) === 0 && $stderr === '', 'Cron enabled guard must execute cleanly');
    monitor_assert($stdout === 'executed', 'Explicit cron enable flag must execute the command');
}

echo "regulatory_monitor_command_smoke passed\n";
