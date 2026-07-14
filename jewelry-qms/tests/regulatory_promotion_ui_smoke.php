<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\controller\PlanningRegulatoryMonitor;
use app\middleware\AuditLog;
use think\exception\HttpException;
use think\facade\Config;
use think\facade\Db;
use think\facade\Session;
use think\facade\View;

function promotion_ui_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function promotion_ui_expect_http(callable $callback, int $status, string $message): void
{
    try {
        $callback();
    } catch (Throwable $exception) {
        promotion_ui_assert($exception instanceof HttpException, $message . '（必须是 HTTP 异常）');
        promotion_ui_assert($exception->getStatusCode() === $status, $message . '（状态码不符）');
        return;
    }
    promotion_ui_assert(false, $message . '（预期异常）');
}

function promotion_ui_response(think\App $app, string $candidateId, array $extraPost = [])
{
    $request = (new app\Request())
        ->setMethod('POST')
        ->setController('PlanningRegulatoryMonitor')
        ->setAction('promote')
        ->withPost(array_merge(['id' => $candidateId], $extraPost));
    $app->instance('request', $request);
    $controller = new PlanningRegulatoryMonitor($app);

    return (new AuditLog())->handle($request, static fn () => $controller->promote());
}

/** @return array<string, mixed> */
function promotion_ui_candidate(
    string $id,
    string $companyId,
    string $status,
    string $title = '法规晋升界面冒烟'
): array {
    $now = date('Y-m-d H:i:s');
    $token = substr(hash('sha256', $id), 0, 12);
    $impacts = [];
    foreach (['cma_scope_mark', 'qms_documents', 'personnel_authorization', 'equipment_calibration', 'lims_rules', 'training'] as $key) {
        $impacts[$key] = [
            'conclusion' => $key === 'training' ? 'no_match' : 'possible',
            'evidence' => [['summary' => '候选影响证据']],
            'rule_ids' => $key === 'training' ? [] : ['PUI-' . $key],
            'confidence' => $key === 'training' ? 0.0 : 0.7,
        ];
    }

    return [
        'id' => $id,
        'company_id' => $companyId,
        'monitor_run_id' => 'pui-run-' . $token,
        'source_key' => 'samr_rkjcs_notice',
        'source_mode' => 'html_list',
        'source_item_key' => 'PUI-' . $token,
        'source_url' => 'https://www.samr.gov.cn/promotion-ui/' . $token,
        'normalized_url' => 'https://www.samr.gov.cn/promotion-ui/' . $token,
        'title' => $title,
        'announcement_number' => '晋升界面公告〔2026〕' . substr($token, 0, 4) . '号',
        'document_type' => 'official_notice',
        'published_date' => '2026-07-01',
        'effective_date' => '2026-08-01',
        'first_seen_at' => $now,
        'last_seen_at' => $now,
        'content_hash' => hash('sha256', 'pui-content-' . $id),
        'evidence_summary' => '官方候选证据摘要',
        'evidence_refs' => json_encode([
            ['url' => 'https://www.samr.gov.cn/promotion-ui/' . $token],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'evidence_json' => '{}',
        'impact_analysis' => json_encode($impacts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'analysis_rule_version' => 'regulatory-impact-v2-pui',
        'analysis_confidence' => 0.7,
        'analysis_rationale' => '规则初判，须人工复核',
        'graph_snapshot_hash' => hash('sha256', 'pui-graph-' . $id),
        'review_status' => $status,
        'reviewed_by' => $status === 'confirmed_applicable' ? 'prior-qm' : null,
        'reviewed_at' => $status === 'confirmed_applicable' ? $now : null,
        'review_comment' => $status === 'confirmed_applicable' ? '已核对官方证据，确认需进入影响评估' : null,
        'publish' => 1,
        'soft_delete' => 0,
        'created' => $now,
        'modified' => $now,
    ];
}

function promotion_ui_render_show(array $candidate, string $role): string
{
    View::layout(false);
    View::assign([
        // Think 模板的点号访问会编译为数组下标；生产模型可被视图驱动转换，
        // 这里直接传数组以贴合编译后的读取契约。
        'record' => $candidate,
        'safeSourceUrl' => $candidate['source_url'],
        'impactAnalysis' => json_decode((string)$candidate['impact_analysis'], true, flags: JSON_THROW_ON_ERROR),
        'versions' => [$candidate],
        'fieldChangeLogs' => [],
        'reviewStatusLabels' => (array)Config::get('qms.regulatory_monitor.review_status_labels', []),
        'impactLabels' => (array)Config::get('qms.regulatory_monitor.impact_labels', []),
        'conclusionLabels' => (array)Config::get('qms.regulatory_monitor.conclusion_labels', []),
        'currentRole' => $role,
    ]);

    return View::fetch('planning_regulatory_monitor/show');
}

$app = new think\App();
$app->initialize();
$root = dirname(__DIR__);
$routes = (string)file_get_contents($root . '/route/app.php');
$controllerSource = (string)file_get_contents($root . '/app/controller/PlanningRegulatoryMonitor.php');
$showSource = (string)file_get_contents($root . '/app/view/planning_regulatory_monitor/show.html');
$layoutSource = (string)file_get_contents($root . '/app/view/layout/main.html');
$csrfSource = (string)file_get_contents($root . '/public/static/js/csrf.js');

$promotionRoute = "Route::post('planning/regulatory-monitor/promote', 'PlanningRegulatoryMonitor/promote')";
promotion_ui_assert(str_contains($routes, $promotionRoute), '必须注册法规候选晋升 POST 路由');
promotion_ui_assert(!str_contains($routes, "Route::get('planning/regulatory-monitor/promote'"), '晋升不得提供 GET 路由');
$routePosition = strpos($routes, $promotionRoute);
$groupPosition = strpos($routes, 'Route::group(function ()');
$formTokenPosition = strpos($routes, '\\think\\middleware\\FormTokenCheck::class');
promotion_ui_assert(
    is_int($routePosition) && is_int($groupPosition) && is_int($formTokenPosition)
        && $groupPosition < $routePosition && $routePosition < $formTokenPosition,
    '晋升路由必须位于带 FormToken 的已登录路由组'
);
foreach (['Auth::class', 'Rbac::class', 'PageContext::class', 'FormTokenCheck::class', 'AuditLog::class'] as $middleware) {
    promotion_ui_assert(str_contains($routes, $middleware), '晋升路由组缺少中间件：' . $middleware);
}
promotion_ui_assert(str_contains($controllerSource, 'public function promote()'), '控制器必须提供晋升动作');
promotion_ui_assert(
    str_contains($showSource, 'action="/planning/regulatory-monitor/promote"')
        && str_contains($showSource, 'method="post"'),
    '详情页必须使用 POST 晋升表单'
);
promotion_ui_assert(str_contains($showSource, 'confirmed_applicable'), '晋升表单必须只针对已确认相关候选');
promotion_ui_assert(str_contains($showSource, '晋升为外部变更事件'), '晋升表单必须有明确人工确认文案');
promotion_ui_assert(str_contains($showSource, 'onclick="return confirm('), '晋升必须有二次确认');
promotion_ui_assert(str_contains($showSource, '/planning/change-events/view?id='), '已晋升候选必须提供关联事件入口');
promotion_ui_assert(str_contains($showSource, '/planning/regulatory-monitor/export?id='), '详情页必须提供脱敏 JSON 导出按钮');
promotion_ui_assert(str_contains($layoutSource, 'token_meta()') && str_contains($layoutSource, '/static/js/csrf.js'), '页面布局必须加载 CSRF token 和表单助手');
promotion_ui_assert(str_contains($csrfSource, "input.name = '__token__'"), 'CSRF 助手必须为 POST 表单注入 token');

$previousQms = (array)Config::get('qms', []);
$qms = $previousQms;
$qms['regulatory_monitor']['enabled'] = true;
Config::set($qms, 'qms');
$companyId = trim((string)Config::get('qms.company_id'));
$otherCompanyId = '99999999-9999-4999-8999-999999999999';
$suffix = substr(str_replace('-', '', qms_uuid()), 0, 10);
$ids = [
    'pending' => 'pui-pending-' . $suffix,
    'deferred' => 'pui-defer-' . $suffix,
    'not_applicable' => 'pui-notapp-' . $suffix,
    'admin' => 'pui-admin-' . $suffix,
    'staff' => 'pui-staff-' . $suffix,
    'other' => 'pui-other-' . $suffix,
    'invalid' => 'pui-invalid-' . $suffix,
    'success' => 'pui-success-' . $suffix,
];
$eventIds = [];

try {
    promotion_ui_assert($companyId !== '', '界面晋升测试需要隔离库 company_id');
    foreach ($ids as $key => $id) {
        $status = match ($key) {
            'pending' => 'pending',
            'deferred' => 'deferred',
            'not_applicable' => 'confirmed_not_applicable',
            default => 'confirmed_applicable',
        };
        $scopeCompany = $key === 'other' ? $otherCompanyId : $companyId;
        $title = $key === 'invalid' ? '' : '法规晋升界面冒烟 ' . $key;
        Db::name('qms_external_change_candidates')->insert(
            promotion_ui_candidate($id, $scopeCompany, $status, $title)
        );
    }

    foreach (['admin', 'staff'] as $role) {
        Session::set('user', ['id' => 'pui-' . $role, 'role' => $role]);
        promotion_ui_expect_http(
            fn () => promotion_ui_response($app, $ids[$role], ['actor_id' => 'forged-qm']),
            403,
            $role . ' 不得通过控制器晋升候选'
        );
        promotion_ui_assert(
            (string)Db::name('qms_external_change_candidates')->where('id', $ids[$role])->value('review_status')
                === 'confirmed_applicable',
            '权限拒绝不得更改候选：' . $role
        );
    }

    Session::set('user', ['id' => 'pui-qm', 'role' => 'quality_manager']);
    foreach (['pending', 'deferred', 'not_applicable'] as $key) {
        Session::delete('success');
        Session::delete('error');
        $eventsBefore = Db::name('qms_external_change_events')->count();
        $response = promotion_ui_response($app, $ids[$key]);
        promotion_ui_assert(
            str_contains((string)$response->getHeader('Location'), '/planning/regulatory-monitor/show?id='),
            '不可晋升候选必须返回候选详情：' . $key
        );
        promotion_ui_assert(Session::get('success') === null, '被拒绝晋升不得显示成功：' . $key);
        promotion_ui_assert(
            Session::get('error') === '法规候选暂无法晋升，请核对复核状态和关联记录。',
            '被拒绝晋升必须显示统一安全提示：' . $key
        );
        promotion_ui_assert(Db::name('qms_external_change_events')->count() === $eventsBefore, '被拒绝晋升不得创建事件：' . $key);
        promotion_ui_assert(
            Db::name('histories')->where('record_id', $ids[$key])->count() === 0,
            '被拒绝晋升不得留成功审计：' . $key
        );
    }

    foreach (['other', 'invalid'] as $key) {
        Session::delete('success');
        Session::delete('error');
        $eventsBefore = Db::name('qms_external_change_events')->count();
        promotion_ui_response($app, $ids[$key]);
        promotion_ui_assert(Db::name('qms_external_change_events')->count() === $eventsBefore, '失败晋升不得留半成品：' . $key);
        $failedCandidate = Db::name('qms_external_change_candidates')->where('id', $ids[$key])->find();
        promotion_ui_assert((string)$failedCandidate['review_status'] === 'confirmed_applicable', '失败晋升必须保持原复核状态：' . $key);
        promotion_ui_assert(empty($failedCandidate['promoted_event_id']), '失败晋升不得留事件关联：' . $key);
        promotion_ui_assert(Db::name('histories')->where('record_id', $ids[$key])->count() === 0, '失败晋升不得留成功审计：' . $key);
    }

    Session::delete('success');
    Session::delete('error');
    $response = promotion_ui_response($app, $ids['success'], [
        'actor_id' => 'forged-qm',
        'company_id' => $otherCompanyId,
        'promoted_event_id' => 'forged-event',
    ]);
    $successCandidate = Db::name('qms_external_change_candidates')->where('id', $ids['success'])->find();
    $eventId = trim((string)$successCandidate['promoted_event_id']);
    promotion_ui_assert((string)$successCandidate['review_status'] === 'promoted', '控制器必须晋升已确认相关候选');
    promotion_ui_assert($eventId !== '' && $eventId !== 'forged-event', '控制器必须忽略伪造 event id');
    $eventIds[] = $eventId;
    promotion_ui_assert(
        (string)$response->getHeader('Location') === '/planning/change-events/view?id=' . $eventId,
        '成功晋升必须跳转关联事件'
    );
    promotion_ui_assert(
        Session::get('success') === '已晋升为外部变更事件，后续影响评估和修订仍需人工完成。',
        '成功晋升必须提示后续人工边界'
    );
    $event = Db::name('qms_external_change_events')->where('id', $eventId)->find();
    promotion_ui_assert(is_array($event), '控制器晋升必须创建正式事件');
    promotion_ui_assert((string)$event['created_by'] === 'pui-qm', 'actor 必须取 Session，不得信任 POST');
    promotion_ui_assert((string)$event['company_id'] === $companyId, '晋升事件必须保持本机构范围');
    $histories = Db::name('histories')
        ->where('model_name', 'QmsExternalChangeCandidate')
        ->where('record_id', $ids['success'])
        ->where('action', 'promoteRegulatoryCandidate')
        ->select()
        ->toArray();
    promotion_ui_assert(count($histories) === 1, '控制器晋升必须仅留一条原子成功审计');
    promotion_ui_assert(
        Db::name('histories')->where('record_id', $ids['success'])->where('action', 'promote')->count() === 0,
        'AuditLog 不得为晋升重复写入通用成功审计'
    );

    $repeatResponse = promotion_ui_response($app, $ids['success']);
    promotion_ui_assert(
        (string)$repeatResponse->getHeader('Location') === '/planning/change-events/view?id=' . $eventId,
        '重复晋升必须返回同一事件'
    );
    promotion_ui_assert(Db::name('qms_external_change_events')->where('id', $eventId)->count() === 1, '重复晋升不得创建第二事件');
    promotion_ui_assert(
        Db::name('histories')->where('record_id', $ids['success'])->where('action', 'promoteRegulatoryCandidate')->count() === 1,
        '重复晋升不得写第二条成功审计'
    );

    $confirmedBefore = Db::name('qms_external_change_candidates')->where('id', $ids['admin'])->find();
    $qmHtml = promotion_ui_render_show($confirmedBefore, 'quality_manager');
    promotion_ui_assert(str_contains($qmHtml, 'action="/planning/regulatory-monitor/promote"'), '质量负责人的已确认相关详情必须显示晋升表单');
    promotion_ui_assert(str_contains($qmHtml, '/planning/regulatory-monitor/export?id=' . $ids['admin']), '质量负责人必须有 JSON 导出按钮');
    $adminHtml = promotion_ui_render_show($confirmedBefore, 'admin');
    promotion_ui_assert(!str_contains($adminHtml, 'action="/planning/regulatory-monitor/promote"'), 'admin 详情页不得显示晋升表单');
    promotion_ui_assert(str_contains($adminHtml, '/planning/regulatory-monitor/export?id=' . $ids['admin']), 'admin 必须保留 JSON 导出按钮');
    $promotedHtml = promotion_ui_render_show($successCandidate, 'quality_manager');
    promotion_ui_assert(!str_contains($promotedHtml, 'action="/planning/regulatory-monitor/promote"'), '已晋升详情不得再显示晋升表单');
    promotion_ui_assert(str_contains($promotedHtml, '/planning/change-events/view?id=' . $eventId), '已晋升详情必须显示关联事件入口');
} finally {
    Session::clear();
    Config::set($previousQms, 'qms');
    $candidateIds = array_values($ids);
    $linkedEventIds = Db::name('qms_external_change_candidates')->whereIn('id', $candidateIds)->column('promoted_event_id');
    foreach ($linkedEventIds as $linkedEventId) {
        if (trim((string)$linkedEventId) !== '') {
            $eventIds[] = trim((string)$linkedEventId);
        }
    }
    $eventIds = array_values(array_unique(array_filter($eventIds)));
    Db::name('field_change_logs')->where('model_name', 'QmsExternalChangeCandidate')->whereIn('record_id', $candidateIds)->delete();
    Db::name('histories')->where('model_name', 'QmsExternalChangeCandidate')->whereIn('record_id', $candidateIds)->delete();
    Db::name('qms_external_change_candidates')->whereIn('id', $candidateIds)->delete();
    if ($eventIds !== []) {
        Db::name('field_change_logs')->where('model_name', 'QmsExternalChangeEvent')->whereIn('record_id', $eventIds)->delete();
        Db::name('qms_external_change_events')->whereIn('id', $eventIds)->delete();
    }
}

echo "regulatory_promotion_ui_smoke passed\n";
