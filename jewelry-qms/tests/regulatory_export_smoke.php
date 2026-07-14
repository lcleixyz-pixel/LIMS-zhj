<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\controller\PlanningRegulatoryMonitor;
use app\service\regulatory\RegulatoryExportService;
use think\facade\Config;
use think\facade\Db;
use think\facade\Session;

final class RegulatoryExportSmokeAssertionFailed extends RuntimeException
{
}

function export_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RegulatoryExportSmokeAssertionFailed($message);
    }
}

function export_throws(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (Throwable) {
        return;
    }
    export_assert(false, $message);
}

function export_set_enabled(bool $enabled): void
{
    $qms = (array)Config::get('qms', []);
    $qms['regulatory_monitor']['enabled'] = $enabled;
    Config::set($qms, 'qms');
}

/** @return array<string, mixed> */
function export_candidate(string $id, string $companyId, int $publish = 1, int $softDelete = 0): array
{
    $now = '2026-07-15 10:00:00';
    $impacts = [];
    foreach (['cma_scope_mark', 'qms_documents', 'personnel_authorization', 'equipment_calibration', 'lims_rules', 'training'] as $index => $key) {
        $impacts[$key] = [
            'conclusion' => $index < 4 ? 'possible' : 'no_match',
            'evidence' => [
                ['summary' => '安全影响证据-' . $key, 'Authorization' => 'nested-impact-secret-' . $key],
                ['cookie' => 'nested-cookie-secret-' . $key],
            ],
            'rule_ids' => $index < 4 ? ['REG-SMOKE-' . $index] : [],
            'confidence' => $index < 4 ? 0.75 : 0.0,
            'dsn' => 'nested-impact-dsn-' . $key,
        ];
    }

    return [
        'id' => $id,
        'company_id' => $companyId,
        'monitor_run_id' => 'export-run-' . substr(hash('sha256', $id), 0, 12),
        'source_key' => 'samr_rkjcs_notice',
        'source_mode' => 'html_list',
        'source_item_key' => 'EXPORT-' . $id,
        'source_url' => 'https://www.samr.gov.cn/export/' . rawurlencode($id),
        'normalized_url' => 'https://www.samr.gov.cn/export/' . rawurlencode($id),
        'title' => '“一单一库”法规候选',
        'announcement_number' => '市场监管总局公告2026年第14号',
        'document_type' => '公示公告',
        'published_date' => '2026-07-01',
        'effective_date' => '2026-08-01',
        'first_seen_at' => $now,
        'last_seen_at' => $now,
        'content_hash' => hash('sha256', 'export-content-' . $id),
        'evidence_summary' => '官方正文和公开附件构成候选来源证据。',
        'evidence_refs' => json_encode([
            [
                'kind' => 'official_page',
                'label' => '市场监管总局官方公告',
                'locator' => 'notice-14',
                'url' => 'https://www.samr.gov.cn/export/' . rawurlencode($id),
                'Password' => 'nested-reference-password',
                'id-card' => 'nested-reference-id-card',
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'evidence_json' => json_encode([
            'raw_text' => '未经白名单允许的原始证据不得整包导出',
            'mobile' => 'nested-evidence-mobile',
            'Cookie' => 'nested-evidence-cookie',
            'nested' => ['authorization' => 'nested-evidence-auth', 'DSN' => 'nested-evidence-dsn'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'relevance' => 'high',
        'preliminary_applicability' => 'needs_review',
        'impact_analysis' => json_encode($impacts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'analysis_rule_version' => 'regulatory-impact-v2-smoke',
        'analysis_confidence' => 0.75,
        'analysis_rationale' => '确定性规则初判，所有结论仍需人工复核。',
        'review_status' => 'confirmed_applicable',
        'reviewed_by' => 'quality-user-not-exported',
        'reviewed_at' => '2026-07-15 11:00:00',
        'review_comment' => 'internal comment not exported',
        'publish' => $publish,
        'soft_delete' => $softDelete,
        'created' => $now,
        'modified' => $now,
    ];
}

/** @return list<string> */
function export_recursive_keys(mixed $value): array
{
    if (!is_array($value)) {
        return [];
    }
    $keys = [];
    foreach ($value as $key => $item) {
        if (is_string($key)) {
            $keys[] = strtolower((string)preg_replace('/[-_]/', '', $key));
        }
        array_push($keys, ...export_recursive_keys($item));
    }

    return $keys;
}

/** @return think\Response */
function export_controller_response(think\App $app, string $candidateId): think\Response
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
$companyId = trim((string)Config::get('qms.company_id'));
$otherCompanyId = '99999999-9999-4999-8999-999999999999';
$token = substr(qms_uuid(), 0, 8);
$ids = [
    'visible' => 'exp-visible-' . $token,
    'other' => 'exp-other-' . $token,
    'hidden' => 'exp-hidden-' . $token,
    'deleted' => 'exp-deleted-' . $token,
    'unsafe_url' => 'exp-url-' . $token,
];
$failure = null;

try {
    foreach ($ids as $id) {
        Db::name('qms_external_change_candidates')->where('id', $id)->delete();
    }
    Db::name('qms_external_change_candidates')->insert(export_candidate($ids['visible'], $companyId));
    Db::name('qms_external_change_candidates')->insert(export_candidate($ids['other'], $otherCompanyId));
    Db::name('qms_external_change_candidates')->insert(export_candidate($ids['hidden'], $companyId, 0, 0));
    Db::name('qms_external_change_candidates')->insert(export_candidate($ids['deleted'], $companyId, 1, 1));
    $unsafeUrl = export_candidate($ids['unsafe_url'], $companyId);
    $unsafeUrl['source_url'] = 'http://www.samr.gov.cn/not-https';
    $unsafeUrl['normalized_url'] = 'http://www.samr.gov.cn/not-https';
    Db::name('qms_external_change_candidates')->insert($unsafeUrl);

    export_set_enabled(true);
    Session::set('user', ['id' => 'export-admin', 'role' => 'admin']);
    $service = new RegulatoryExportService();
    $packet = $service->exportCandidate($ids['visible']);
    $packetAgain = $service->exportCandidate($ids['visible']);

    export_assert(array_keys($packet) === ['schema_version', 'candidate', 'source', 'impact_assessment', 'review'], '导出顶层字段和顺序必须固定');
    export_assert($packet['schema_version'] === '1.0', 'schema_version 必须为 1.0');
    export_assert((string)$packet['candidate']['analysis_rule_version'] === 'regulatory-impact-v2-smoke', '必须导出分析规则版本');
    export_assert((string)$packet['review']['status'] === 'confirmed_applicable', '必须导出人工复核状态');
    export_assert(
        array_keys($packet['impact_assessment']) === ['cma_scope_mark', 'qms_documents', 'personnel_authorization', 'equipment_calibration', 'lims_rules', 'training'],
        '六类影响必须完整且顺序固定'
    );
    export_assert(
        str_starts_with((string)$packet['source']['canonical_url'], 'https://'),
        'source.canonical_url 必须为 HTTPS'
    );
    export_assert(
        (string)$packet['source']['evidence']['summary'] === '官方正文和公开附件构成候选来源证据。'
            && count((array)$packet['source']['evidence']['references']) === 1,
        '必须导出最小官方来源证据'
    );
    export_assert(
        json_encode($packet, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
            === json_encode($packetAgain, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        '相同候选和规则版本必须生成稳定 JSON'
    );

    $forbidden = ['password', 'cookie', 'authorization', 'dsn', 'mobile', 'idcard'];
    export_assert(array_intersect($forbidden, export_recursive_keys($packet)) === [], '导出包不得包含递归禁用键');
    $encoded = json_encode($packet, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    foreach (['nested-reference-password', 'nested-reference-id-card', 'nested-evidence-mobile', 'nested-evidence-cookie', 'nested-evidence-auth', 'nested-evidence-dsn', 'nested-impact-secret', 'nested-cookie-secret', 'nested-impact-dsn', '未经白名单允许的原始证据'] as $secret) {
        export_assert(!str_contains($encoded, $secret), '导出包不得泄露原始或嵌套非白名单字段: ' . $secret);
    }
    export_assert(
        $service->filename($ids['visible']) === 'candidate-' . $ids['visible'] . '-review-packet-v1.0.json',
        '下载文件名必须按 schema 1.0 固定'
    );

    $candidateBefore = Db::name('qms_external_change_candidates')->where('id', $ids['visible'])->find();
    $response = export_controller_response($app, $ids['visible']);
    export_assert(str_starts_with((string)$response->getHeader('Content-Type'), 'application/json'), '控制器必须返回 application/json');
    export_assert(
        (string)$response->getHeader('Content-Disposition') === 'attachment; filename="candidate-' . $ids['visible'] . '-review-packet-v1.0.json"',
        '控制器必须返回安全附件名'
    );
    $responsePacket = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
    export_assert($responsePacket === $packet, '控制器响应必须等于服务层脱敏包');
    export_assert(Db::name('qms_external_change_candidates')->where('id', $ids['visible'])->find() === $candidateBefore, 'GET 导出不得修改候选业务状态');

    foreach (['quality_manager'] as $role) {
        Session::set('user', ['id' => 'export-' . $role, 'role' => $role]);
        export_assert((new RegulatoryExportService())->exportCandidate($ids['visible'])['schema_version'] === '1.0', $role . ' 应可导出');
    }
    foreach (['technical_manager', 'staff', 'auditor'] as $role) {
        Session::set('user', ['id' => 'export-' . $role, 'role' => $role]);
        export_throws(fn () => (new RegulatoryExportService())->exportCandidate($ids['visible']), $role . ' 不得通过服务层导出');
        export_throws(fn () => export_controller_response($app, $ids['visible']), $role . ' 不得通过控制器导出');
    }

    Session::set('user', ['id' => 'export-admin', 'role' => 'admin']);
    foreach (['other', 'hidden', 'deleted'] as $scope) {
        export_throws(fn () => (new RegulatoryExportService())->exportCandidate($ids[$scope]), '不得导出越机构或不可见候选: ' . $scope);
    }
    export_throws(fn () => (new RegulatoryExportService())->exportCandidate($ids['unsafe_url']), '非 HTTPS 候选来源不得导出');
    foreach (["bad\r\nX-Test: injected", '../bad', '', str_repeat('x', 37)] as $invalidId) {
        export_throws(fn () => (new RegulatoryExportService())->exportCandidate($invalidId), '不安全候选 ID 必须在查询/响应头前拒绝');
        export_throws(fn () => (new RegulatoryExportService())->filename($invalidId), '不安全候选 ID 不得进入文件名');
    }

    export_set_enabled(false);
    export_throws(fn () => (new RegulatoryExportService())->exportCandidate($ids['visible']), '功能开关关闭时必须拒绝导出');
    export_set_enabled(true);

    $routeSource = (string)file_get_contents(dirname(__DIR__) . '/route/app.php');
    export_assert(
        str_contains($routeSource, "Route::get('planning/regulatory-monitor/export', 'PlanningRegulatoryMonitor/export');"),
        '必须在现有认证/RBAC/页面上下文路由组内注册 GET 导出路由'
    );
} catch (Throwable $exception) {
    $failure = $exception;
} finally {
    export_set_enabled(true);
    Session::delete('user');
    foreach ($ids as $id) {
        Db::name('qms_external_change_candidates')->where('id', $id)->delete();
    }
}

if ($failure !== null) {
    fwrite(STDERR, $failure::class . ': ' . $failure->getMessage() . PHP_EOL);
    exit(1);
}

echo "regulatory export smoke: PASS\n";
