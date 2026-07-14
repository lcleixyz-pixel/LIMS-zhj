<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\service\regulatory\RegulatoryCandidateReviewService;
use app\service\RbacService;
use app\controller\PlanningRegulatoryMonitor;
use app\middleware\AuditLog;
use think\facade\Config;
use think\facade\Db;
use think\facade\Session;
use think\facade\View;

function ui_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function ui_throws(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (Throwable) {
        return;
    }
    ui_assert(false, $message);
}

function ui_set_enabled(bool $enabled): void
{
    $qms = (array)Config::get('qms', []);
    $qms['regulatory_monitor']['enabled'] = $enabled;
    Config::set($qms, 'qms');
}

function ui_run_controller_action(think\App $app, string $action, array $post)
{
    $request = (new app\Request())
        ->setMethod('POST')
        ->setController('PlanningRegulatoryMonitor')
        ->setAction($action)
        ->withPost($post);
    $app->instance('request', $request);
    $controller = new PlanningRegulatoryMonitor($app);

    return (new AuditLog())->handle($request, static fn () => $controller->{$action}());
}

function ui_candidate(string $id, string $companyId, string $status = 'pending', int $publish = 1, int $softDelete = 0): array
{
    $now = date('Y-m-d H:i:s');
    $impacts = [];
    foreach (['cma_scope_mark', 'qms_documents', 'personnel_authorization', 'equipment_calibration', 'lims_rules', 'training'] as $key) {
        $impacts[$key] = [
            'conclusion' => $key === 'training' ? 'no_match' : 'possible',
            'evidence' => [['summary' => '<script>alert(1)</script> official evidence']],
            'rule_ids' => ['smoke-rule'],
            'confidence' => 0.5,
        ];
    }

    return [
        'id' => $id,
        'company_id' => $companyId,
        'monitor_run_id' => 'ui-run-' . substr(hash('sha256', $id), 0, 12),
        'source_key' => 'samr_rkjcs_notice',
        'source_mode' => 'html_list',
        'source_item_key' => 'UI-' . $id,
        'source_url' => 'https://www.samr.gov.cn/test/' . rawurlencode($id),
        'normalized_url' => 'https://www.samr.gov.cn/test/' . rawurlencode($id),
        'title' => '<img src=x onerror=alert(1)> 候选 ' . $id,
        'announcement_number' => '公告 ' . $id,
        'document_type' => 'official_notice',
        'published_date' => '2026-07-14',
        'effective_date' => '2026-08-01',
        'first_seen_at' => $now,
        'last_seen_at' => $now,
        'content_hash' => hash('sha256', $id),
        'evidence_summary' => '<script>alert(1)</script> 原始证据',
        'evidence_refs' => json_encode([['url' => 'https://www.samr.gov.cn/test/' . rawurlencode($id)]], JSON_UNESCAPED_UNICODE),
        'evidence_json' => json_encode(['raw' => '<iframe src=javascript:alert(1)>'], JSON_UNESCAPED_UNICODE),
        'impact_analysis' => json_encode($impacts, JSON_UNESCAPED_UNICODE),
        'analysis_rule_version' => 'reg-impact-v1',
        'analysis_confidence' => 0.5,
        'analysis_rationale' => '规则初判，需人工确认',
        'review_status' => $status,
        'promoted_event_id' => $status === 'promoted' ? 'event-' . substr(hash('sha256', $id), 0, 12) : null,
        'publish' => $publish,
        'soft_delete' => $softDelete,
        'created' => $now,
        'modified' => $now,
    ];
}

$app = new think\App();
$app->initialize();

$root = dirname(__DIR__);
$companyId = (string)Config::get('qms.company_id');
$otherCompanyId = '99999999-9999-4999-8999-999999999999';
$ids = [
    'visible' => 'ui-visible-' . substr(qms_uuid(), 0, 8),
    'other' => 'ui-other-' . substr(qms_uuid(), 0, 8),
    'hidden' => 'ui-hidden-' . substr(qms_uuid(), 0, 8),
    'deleted' => 'ui-deleted-' . substr(qms_uuid(), 0, 8),
    'promoted' => 'ui-promoted-' . substr(qms_uuid(), 0, 8),
    'rollback' => 'ui-rollback-' . substr(qms_uuid(), 0, 8),
    'controller' => 'ui-controller-' . substr(qms_uuid(), 0, 8),
];

Db::execute("CREATE TABLE IF NOT EXISTS `field_change_logs` (
  `id` varchar(36) NOT NULL, `model_name` varchar(100) NOT NULL,
  `record_id` varchar(36) NOT NULL, `field_name` varchar(100) NOT NULL,
  `old_value` text, `new_value` text, `changed_by` varchar(36) DEFAULT NULL,
  `changed_at` datetime NOT NULL, PRIMARY KEY (`id`),
  KEY `record_lookup` (`model_name`,`record_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

try {
    foreach ($ids as $id) {
        Db::name('field_change_logs')->where('record_id', $id)->delete();
        Db::name('qms_external_change_candidates')->where('id', $id)->delete();
    }
    Db::name('qms_external_change_candidates')->insert(ui_candidate($ids['visible'], $companyId));
    Db::name('qms_external_change_candidates')->insert(ui_candidate($ids['other'], $otherCompanyId));
    Db::name('qms_external_change_candidates')->insert(ui_candidate($ids['hidden'], $companyId, 'pending', 0, 0));
    Db::name('qms_external_change_candidates')->insert(ui_candidate($ids['deleted'], $companyId, 'pending', 1, 1));
    Db::name('qms_external_change_candidates')->insert(ui_candidate($ids['promoted'], $companyId, 'promoted'));
    Db::name('qms_external_change_candidates')->insert(ui_candidate($ids['rollback'], $companyId));
    Db::name('qms_external_change_candidates')->insert(ui_candidate($ids['controller'], $companyId));

    ui_set_enabled(false);
    Session::set('user', ['id' => 'ui-admin', 'role' => 'admin']);
    ui_throws(fn () => (new RegulatoryCandidateReviewService())->listCandidates(), '功能开关关闭时必须拒绝访问');

    ui_set_enabled(true);
    foreach (['admin', 'quality_manager'] as $role) {
        Session::set('user', ['id' => 'ui-' . $role, 'role' => $role]);
        ui_assert(RbacService::canAccess('PlanningRegulatoryMonitor'), $role . ' RBAC 应可访问法规监测');
        $service = new RegulatoryCandidateReviewService();
        $listed = $service->listCandidates();
        $listedIds = array_column($listed, 'id');
        ui_assert(in_array($ids['visible'], $listedIds, true), $role . ' 可查看本机构候选');
        ui_assert(!in_array($ids['other'], $listedIds, true), $role . ' 不可查看其他机构候选');
        ui_assert(!in_array($ids['hidden'], $listedIds, true), '未发布候选不可见');
        ui_assert(!in_array($ids['deleted'], $listedIds, true), '软删除候选不可见');
        ui_assert((string)$service->findCandidate($ids['visible'])->id === $ids['visible'], '可查看本机构详情');
        ui_throws(fn () => $service->findCandidate($ids['other']), '详情查询不得越机构');
    }

    foreach (['staff', 'department_head', 'auditor'] as $role) {
        Session::set('user', ['id' => 'ui-' . $role, 'role' => $role]);
        ui_assert(!RbacService::canAccess('PlanningRegulatoryMonitor'), $role . ' RBAC 不得访问法规监测');
        $service = new RegulatoryCandidateReviewService();
        ui_throws(fn () => $service->listCandidates(), $role . ' 不得查看候选模块');
        ui_throws(fn () => $service->review($ids['visible'], 'deferred', '等待技术负责人复核'), $role . ' 不得修改复核');
    }

    Session::set('user', ['id' => 'ui-admin', 'role' => 'admin']);
    $service = new RegulatoryCandidateReviewService();
    ui_throws(fn () => $service->review($ids['visible'], 'confirmed_applicable', '相关'), 'admin 不得提交业务适用性结论');

    Session::set('user', ['id' => 'ui-qm', 'role' => 'quality_manager']);
    $service = new RegulatoryCandidateReviewService();
    foreach (['', ' ', '理'] as $invalidReason) {
        ui_throws(fn () => $service->review($ids['visible'], 'deferred', $invalidReason), '人工复核理由必填且有合理长度');
    }
    ui_throws(fn () => $service->review($ids['visible'], 'pending', '无效状态测试'), '不得接受未批准复核状态');
    ui_throws(fn () => $service->review($ids['visible'], 'deferred', str_repeat('过长', 600)), '理由必须有上限');
    ui_throws(fn () => $service->review($ids['promoted'], 'deferred', '已晋升项不可重新复核'), 'promoted 候选不得复核');

    foreach (['confirmed_applicable', 'confirmed_not_applicable', 'deferred'] as $status) {
        $reason = '质量负责人人工复核：' . $status;
        $candidate = $service->review($ids['visible'], $status, '  ' . $reason . '  ');
        ui_assert((string)$candidate->review_status === $status, '合法复核状态应保存');
        ui_assert((string)$candidate->review_comment === $reason, '复核理由应 trim 后保存');
        ui_assert((string)$candidate->reviewed_by === 'ui-qm', 'reviewed_by 必须取 Session 用户');
        ui_assert((string)$candidate->reviewed_at !== '', 'reviewed_at 必须取服务器时间');
    }
    $auditFields = Db::name('field_change_logs')
        ->where('model_name', 'QmsExternalChangeCandidate')
        ->where('record_id', $ids['visible'])
        ->column('field_name');
    foreach (['review_status', 'review_comment', 'reviewed_by', 'reviewed_at'] as $field) {
        ui_assert(in_array($field, $auditFields, true), '复核字段必须进入字段审计: ' . $field);
    }
    $commentLog = Db::name('field_change_logs')
        ->where('record_id', $ids['visible'])
        ->where('field_name', 'review_comment')
        ->order('changed_at', 'desc')
        ->find();
    ui_assert(is_array($commentLog) && (string)$commentLog['new_value'] === '[已变更]', '人工长理由在通用审计中应脱敏');

    $beforeRollbackLogs = Db::name('field_change_logs')->where('record_id', $ids['rollback'])->count();
    Db::startTrans();
    try {
        $service->review($ids['rollback'], 'deferred', '此复核故意回滚');
    } finally {
        Db::rollback();
    }
    ui_assert((string)Db::name('qms_external_change_candidates')->where('id', $ids['rollback'])->value('review_status') === 'pending', '业务失败时不得保留状态变更');
    ui_assert(Db::name('field_change_logs')->where('record_id', $ids['rollback'])->count() === $beforeRollbackLogs, '业务失败时不得保留成功字段审计');

    $runCount = Db::name('qms_regulatory_monitor_runs')->count();
    $candidateCount = Db::name('qms_external_change_candidates')->count();
    $notificationCount = Db::name('notifications')->count();
    $fieldAuditCount = Db::name('field_change_logs')->count();
    $historyCount = Db::name('histories')->count();
    $dryResult = $service->runManual(['cma_capability_query'], null, true);
    ui_assert((string)$dryResult['status'] === 'completed', 'quality_manager dry-run 应可执行');
    ui_assert(Db::name('qms_regulatory_monitor_runs')->count() === $runCount, 'UI dry-run 必须零运行记录持久化');
    ui_assert(Db::name('qms_external_change_candidates')->count() === $candidateCount, 'UI dry-run 必须零候选持久化');
    ui_assert(Db::name('notifications')->count() === $notificationCount, 'UI dry-run 必须零通知持久化');
    ui_assert(Db::name('field_change_logs')->count() === $fieldAuditCount, 'UI dry-run 必须零字段审计持久化');
    ui_assert(Db::name('histories')->count() === $historyCount, 'UI dry-run 必须零路由审计持久化');
    ui_throws(fn () => $service->runManual(['cma_capability_query'], null, false), 'quality_manager 不得 actual 采集');
    ui_throws(fn () => $service->runManual(['unknown_source'], null, true), '手工运行仅接受已批准 source key');

    Session::set('user', ['id' => 'ui-admin', 'role' => 'admin']);
    $adminDryRunCount = Db::name('qms_regulatory_monitor_runs')->count();
    $adminHistoryCount = Db::name('histories')->count();
    $adminDry = $service->runManual(['cma_capability_query'], null, true);
    ui_assert((string)$adminDry['status'] === 'completed', 'admin 也可执行 dry-run');
    ui_assert(Db::name('qms_regulatory_monitor_runs')->count() === $adminDryRunCount, 'admin dry-run 不得持久化');
    ui_assert(Db::name('histories')->count() === $adminHistoryCount, 'admin dry-run 不得留下 history');
    $actual = $service->runManual(['cma_capability_query'], '2026-01-01', false);
    ui_assert((string)$actual['status'] === 'completed', 'admin 可实际执行已批准 manual-only 来源');
    ui_assert((string)Db::name('qms_regulatory_monitor_runs')->where('id', $actual['run_id'])->value('trigger_mode') === 'manual', 'UI actual 必须记为 manual');
    Db::name('qms_regulatory_monitor_runs')->where('id', $actual['run_id'])->delete();

    Session::set('user', ['id' => 'ui-http-qm', 'role' => 'quality_manager']);
    $httpHistoryBefore = Db::name('histories')->where('user_id', 'ui-http-qm')->count();
    ui_run_controller_action($app, 'review', [
        'id' => $ids['controller'],
        'review_status' => 'deferred',
        'review_comment' => '控制器人工复核审计测试',
        'company_id' => $otherCompanyId,
        'promoted_event_id' => 'forged-event',
    ]);
    $httpCandidate = Db::name('qms_external_change_candidates')->where('id', $ids['controller'])->find();
    ui_assert((string)$httpCandidate['review_status'] === 'deferred', '控制器应保存合法人工复核');
    ui_assert((string)$httpCandidate['company_id'] === $companyId && empty($httpCandidate['promoted_event_id']), '控制器必须忽略伪造 company/promoted 字段');
    $httpReviewHistory = Db::name('histories')->where('user_id', 'ui-http-qm')->where('action', 'review')->order('created', 'desc')->find();
    ui_assert(is_array($httpReviewHistory), '成功人工复核必须进入 route audit');
    ui_assert((string)$httpReviewHistory['record_id'] === $ids['controller'], 'route audit 必须指向真实 candidate id');
    ui_assert(!str_contains((string)$httpReviewHistory['details'], '控制器人工复核审计测试'), '通用 history 不得写入人工理由或证据原文');
    ui_run_controller_action($app, 'review', [
        'id' => $ids['controller'],
        'review_status' => 'pending',
        'review_comment' => '非法状态不应审计成功',
    ]);
    ui_assert(Db::name('histories')->where('user_id', 'ui-http-qm')->count() === $httpHistoryBefore + 1, '被拒绝的复核不得记为成功 history');

    $httpDryBaseline = [
        'runs' => Db::name('qms_regulatory_monitor_runs')->count(),
        'candidates' => Db::name('qms_external_change_candidates')->count(),
        'notifications' => Db::name('notifications')->count(),
        'field_logs' => Db::name('field_change_logs')->count(),
        'histories' => Db::name('histories')->count(),
    ];
    ui_run_controller_action($app, 'run', [
        'source' => ['cma_capability_query'],
        'since' => '',
        'dry_run' => '1',
        'fixture_dir' => '/tmp/forbidden',
        'APP_ENV' => 'test',
    ]);
    ui_assert(Db::name('qms_regulatory_monitor_runs')->count() === $httpDryBaseline['runs'], 'HTTP dry-run 必须零 runs 持久化');
    ui_assert(Db::name('qms_external_change_candidates')->count() === $httpDryBaseline['candidates'], 'HTTP dry-run 必须零 candidates 持久化');
    ui_assert(Db::name('notifications')->count() === $httpDryBaseline['notifications'], 'HTTP dry-run 必须零 notifications 持久化');
    ui_assert(Db::name('field_change_logs')->count() === $httpDryBaseline['field_logs'], 'HTTP dry-run 必须零 field logs 持久化');
    ui_assert(Db::name('histories')->count() === $httpDryBaseline['histories'], 'HTTP dry-run 必须零 histories 持久化');

    Session::set('user', ['id' => 'ui-http-admin', 'role' => 'admin']);
    ui_run_controller_action($app, 'run', [
        'source' => ['cma_capability_query'],
        'since' => '2026-01-01',
        'dry_run' => '0',
    ]);
    $httpRunHistory = Db::name('histories')->where('user_id', 'ui-http-admin')->where('action', 'run')->order('created', 'desc')->find();
    ui_assert(is_array($httpRunHistory), 'admin actual 手工运行必须记录 route audit');
    $httpRunId = (string)$httpRunHistory['record_id'];
    ui_assert((string)Db::name('qms_regulatory_monitor_runs')->where('id', $httpRunId)->value('trigger_mode') === 'manual', 'actual route audit 必须指向真实 manual run id');
    Db::name('qms_regulatory_monitor_runs')->where('id', $httpRunId)->delete();

    foreach (['app/controller/PlanningRegulatoryMonitor.php', 'app/view/planning_regulatory_monitor/index.html', 'app/view/planning_regulatory_monitor/show.html'] as $relative) {
        ui_assert(is_file($root . '/' . $relative), '法规候选 UI 文件缺失: ' . $relative);
    }
    $routes = (string)file_get_contents($root . '/route/app.php');
    $controller = (string)file_get_contents($root . '/app/controller/PlanningRegulatoryMonitor.php');
    foreach ([
        "Route::get('planning/regulatory-monitor', 'PlanningRegulatoryMonitor/index')",
        "Route::get('planning/regulatory-monitor/show', 'PlanningRegulatoryMonitor/show')",
        "Route::post('planning/regulatory-monitor/review', 'PlanningRegulatoryMonitor/review')",
        "Route::post('planning/regulatory-monitor/run', 'PlanningRegulatoryMonitor/run')",
    ] as $route) {
        ui_assert(str_contains($routes, $route), '缺失明确 GET/POST 路由: ' . $route);
    }
    ui_assert(!str_contains($controller, 'fixture') && !str_contains($controller, 'APP_ENV'), 'Web 控制器不得接收 fixture-dir 或 APP_ENV 绕过');
    ui_assert(str_contains($controller, "assertControllerRole(['quality_manager'])"), '控制器必须二次限制复核角色');
    ui_assert(str_contains($controller, "['admin', 'quality_manager']") && str_contains($controller, "['admin']"), '控制器必须分离 dry-run 与 actual 角色');
    ui_assert(str_contains($controller, "if (!\$dryRun)"), 'UI dry-run 不得标记 route audit');
    $indexView = (string)file_get_contents($root . '/app/view/planning_regulatory_monitor/index.html');
    $showView = (string)file_get_contents($root . '/app/view/planning_regulatory_monitor/show.html');
    $combinedViews = $indexView . "\n" . $showView;
    ui_assert(str_contains($combinedViews, '机器发现/规则初判，须人工确认'), '页面必须显著显示固定风险文案');
    ui_assert(str_contains($combinedViews, '未命中规则'), 'no_match 必须显示为未命中规则');
    foreach (['cma_scope_mark', 'qms_documents', 'personnel_authorization', 'equipment_calibration', 'lims_rules', 'training'] as $impactKey) {
        ui_assert(str_contains($showView, $impactKey), '详情页必须固定展示六类影响: ' . $impactKey);
    }
    ui_assert(!str_contains($showView, '|raw'), '候选证据与原始内容不得 raw 渲染');
    ui_assert(substr_count($indexView, '|raw') === 1 && str_contains($indexView, '{$pages|raw}'), '列表只允许框架分页 HTML 使用 raw');
    ui_assert(str_contains($showView, 'method="post"') && str_contains($indexView, 'method="post"'), '写操作必须由 POST 表单提交');

    $qmsConfig = (string)file_get_contents($root . '/config/qms.php');
    ui_assert(str_contains($qmsConfig, "'regulatory_monitor'"), '配置必须包含功能开关与标签');
    ui_assert(str_contains($qmsConfig, "'planningregulatorymonitor'"), '仅 quality_manager 权限表增加法规监测模块');
    foreach ([$root . '/compose.yaml', $root . '/deploy/experience/compose.yaml'] as $composePath) {
        ui_assert(str_contains((string)file_get_contents($composePath), 'REGULATORY_MONITOR_ENABLED'), '统一法规监测开关必须透传到 Compose: ' . $composePath);
    }
    foreach ([$root . '/.example.env', $root . '/deploy/experience/.env.example'] as $exampleEnvPath) {
        ui_assert(preg_match('/^REGULATORY_MONITOR_ENABLED\s*=\s*0\s*$/m', (string)file_get_contents($exampleEnvPath)) === 1, '示例 env 中法规监测必须默认关闭: ' . $exampleEnvPath);
    }
    $layout = (string)file_get_contents($root . '/app/view/layout/main.html');
    ui_assert(str_contains($layout, "qms_can('planningregulatorymonitor')"), '导航必须按权限条件显示');
    ui_assert(str_contains($layout, "config('qms.regulatory_monitor.enabled')"), '导航必须受功能开关控制');

    $controllerObject = new PlanningRegulatoryMonitor($app);
    $safeSourceUrl = new ReflectionMethod(PlanningRegulatoryMonitor::class, 'safeSourceUrl');
    $safeSourceUrl->setAccessible(true);
    $approvedSources = $service->approvedSources();
    ui_assert(
        $safeSourceUrl->invoke($controllerObject, ['source_key' => 'samr_rkjcs_notice', 'source_url' => 'javascript:alert(1)'], $approvedSources) === null,
        '恶意 scheme 不得渲染为可点击链接'
    );
    ui_assert(
        $safeSourceUrl->invoke($controllerObject, ['source_key' => 'samr_rkjcs_notice', 'source_url' => 'https://evil.example/test'], $approvedSources) === null,
        '非对应官方 host 不得渲染为可点击链接'
    );
    ui_assert(
        $safeSourceUrl->invoke($controllerObject, ['source_key' => 'samr_rkjcs_notice', 'source_url' => 'https://www.samr.gov.cn:444/test'], $approvedSources) === null,
        '非批准端口不得渲染为可点击链接'
    );
    ui_assert(
        $safeSourceUrl->invoke($controllerObject, ['source_key' => 'samr_rkjcs_notice', 'source_url' => 'https://www.samr.gov.cn/test'], $approvedSources) === 'https://www.samr.gov.cn/test',
        '对应官方 HTTPS host 和批准端口应可作证据链接'
    );

    $renderCandidate = $service->findCandidate($ids['visible']);
    View::layout(false);
    View::assign([
        'record' => $renderCandidate,
        'safeSourceUrl' => null,
        'impactAnalysis' => (array)$renderCandidate->impact_analysis,
        'versions' => [$renderCandidate->toArray()],
        'fieldChangeLogs' => [],
        'reviewStatusLabels' => (array)Config::get('qms.regulatory_monitor.review_status_labels', []),
        'impactLabels' => (array)Config::get('qms.regulatory_monitor.impact_labels', []),
        'conclusionLabels' => (array)Config::get('qms.regulatory_monitor.conclusion_labels', []),
        'currentRole' => 'admin',
    ]);
    $renderedShow = View::fetch('planning_regulatory_monitor/show');
    ui_assert(!str_contains($renderedShow, '<script>alert(1)</script>'), '渲染后的原始证据不得成为可执行 HTML');
    ui_assert(str_contains($renderedShow, '&lt;script&gt;alert(1)&lt;/script&gt;'), '渲染层必须转义不可信证据');

    fwrite(STDOUT, "regulatory monitor UI smoke: PASS\n");
} finally {
    foreach ($ids as $id) {
        Db::name('field_change_logs')->where('record_id', $id)->delete();
        Db::name('qms_external_change_candidates')->where('id', $id)->delete();
    }
    Db::name('histories')->whereIn('user_id', ['ui-http-qm', 'ui-http-admin'])->delete();
    Session::clear();
}
