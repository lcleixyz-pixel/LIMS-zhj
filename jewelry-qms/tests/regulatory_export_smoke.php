<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\controller\PlanningRegulatoryMonitor;
use app\service\regulatory\RegulatoryExportService;
use think\facade\Config;
use think\facade\Db;
use think\facade\Session;
use think\exception\HttpException;
use think\event\LogRecord;

final class RegulatoryExportSmokeAssertionFailed extends RuntimeException
{
}

function export_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RegulatoryExportSmokeAssertionFailed($message);
    }
}

function export_expect_exception(
    callable $callback,
    string $expectedClass,
    string $expectedMessage,
    string $message
): Throwable
{
    try {
        $callback();
    } catch (Throwable $exception) {
        export_assert($exception instanceof $expectedClass, $message . '（异常类型不符）');
        export_assert($exception->getMessage() === $expectedMessage, $message . '（安全错误不符）');

        return $exception;
    }
    export_assert(false, $message);
}

function export_expect_http_exception(
    callable $callback,
    int $expectedStatus,
    string $expectedMessage,
    string $message
): HttpException {
    $exception = export_expect_exception($callback, HttpException::class, $expectedMessage, $message);
    export_assert($exception instanceof HttpException, $message . '（必须是 HTTP 异常）');
    export_assert($exception->getStatusCode() === $expectedStatus, $message . '（HTTP 状态码不符）');

    return $exception;
}

function export_rejects_sensitive_candidate(
    RegulatoryExportService $service,
    string $candidateId,
    string $sensitiveValue,
    string $message
): void {
    $exception = export_expect_exception(
        fn () => $service->exportCandidate($candidateId),
        UnexpectedValueException::class,
        '法规候选导出内容包含敏感信息',
        $message
    );
    export_assert(
        !str_contains($exception->getMessage(), $sensitiveValue),
        $message . '（错误信息不得回显敏感值）'
    );
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

/** @param array<string, mixed> $candidate @return array<string, mixed> */
function export_inject_sensitive_value(array $candidate, string $location, string $value): array
{
    if ($location === 'title') {
        $candidate['title'] = $value;
        return $candidate;
    }
    if ($location === 'summary') {
        $candidate['evidence_summary'] = $value;
        return $candidate;
    }
    if (str_starts_with($location, 'reference_')) {
        $references = json_decode((string)$candidate['evidence_refs'], true, flags: JSON_THROW_ON_ERROR);
        $references[0][substr($location, strlen('reference_'))] = $value;
        $candidate['evidence_refs'] = json_encode(
            $references,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        return $candidate;
    }

    $impacts = json_decode((string)$candidate['impact_analysis'], true, flags: JSON_THROW_ON_ERROR);
    if ($location === 'impact_evidence') {
        $impacts['cma_scope_mark']['evidence'][0]['summary'] = $value;
    } elseif ($location === 'rule_id') {
        $impacts['cma_scope_mark']['rule_ids'][] = $value;
    } else {
        throw new InvalidArgumentException('未知敏感值注入位置');
    }
    $candidate['impact_analysis'] = json_encode(
        $impacts,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );

    return $candidate;
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
    'sensitive_title' => 'exp-s-title-' . $token,
    'sensitive_summary' => 'exp-s-summary-' . $token,
    'sensitive_reference' => 'exp-s-ref-' . $token,
    'sensitive_impact' => 'exp-s-impact-' . $token,
    'sensitive_rule' => 'exp-s-rule-' . $token,
    'sensitive_locator' => 'exp-s-locator-' . $token,
    'json_password' => 'exp-j-password-' . $token,
    'json_cookie' => 'exp-j-cookie-' . $token,
    'json_authorization' => 'exp-j-auth-' . $token,
    'json_dsn' => 'exp-j-dsn-' . $token,
    'json_mobile' => 'exp-j-mobile-' . $token,
    'json_id_card' => 'exp-j-idcard-' . $token,
    'phone_space' => 'exp-p-space-' . $token,
    'phone_dash' => 'exp-p-dash-' . $token,
    'phone_country' => 'exp-p-country-' . $token,
    'fullwidth_password' => 'exp-f-password-' . $token,
    'smart_cookie' => 'exp-f-cookie-' . $token,
    'safe_json' => 'exp-safe-json-' . $token,
    'deep_json' => 'exp-deep-json-' . $token,
    'double_encoded' => 'exp-double-json-' . $token,
    'unicode_prefix' => 'exp-unicode-json-' . $token,
    'percent_encoded' => 'exp-percent-json-' . $token,
    'double_safe' => 'exp-double-safe-' . $token,
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
    $safeJson = export_candidate($ids['safe_json'], $companyId);
    $safeJson['title'] = '{"notice":"公告2026年第14号","effective_date":"2026-08-01"}';
    Db::name('qms_external_change_candidates')->insert($safeJson);
    $deepJsonValue = '{"pass\\u0077ord":"export-deep-json-secret"}';
    for ($depth = 0; $depth < 20; $depth++) {
        $deepJsonValue = '{"safe":' . $deepJsonValue . '}';
    }
    $deepJson = export_candidate($ids['deep_json'], $companyId);
    $deepJson['title'] = $deepJsonValue;
    Db::name('qms_external_change_candidates')->insert($deepJson);
    $doubleSafe = export_candidate($ids['double_safe'], $companyId);
    $doubleSafe['title'] = '"{\\"notice\\":\\"公告2026年第14号\\",\\"effective_date\\":\\"2026-08-01\\"}"';
    Db::name('qms_external_change_candidates')->insert($doubleSafe);

    $sensitiveCases = [
        'json_password' => ['title', '{"Pass_word":"export-json-password-secret"}'],
        'sensitive_title' => ['title', 'Authorization: Bearer eXpoRt-SecRet-ToKen-123456789'],
        'sensitive_summary' => ['summary', 'Cookie: qms_session=export-cookie-secret'],
        'sensitive_reference' => ['reference_label', 'mysql://export_user:db-secret@db.internal/qms'],
        'sensitive_impact' => ['impact_evidence', '13800138000'],
        'sensitive_rule' => ['rule_id', '11010519491231002X'],
        'sensitive_locator' => ['reference_locator', 'password=export-password-secret'],
        'json_cookie' => ['summary', '[{"COO-KIE":"export-json-cookie-secret"}]'],
        'json_authorization' => ['reference_label', '{"Author_ization":"export-json-auth-secret"}'],
        'json_dsn' => ['impact_evidence', '{"D-S_N":"export-json-dsn-secret"}'],
        'json_mobile' => ['rule_id', '{"Mo_bile":"export-json-mobile-secret"}'],
        'json_id_card' => ['reference_locator', '{"ID-Card":"export-json-id-secret"}'],
        'phone_space' => ['summary', '138 0013 8000'],
        'phone_dash' => ['reference_kind', '138-0013-8000'],
        'phone_country' => ['impact_evidence', '+86 13800138000'],
        'fullwidth_password' => ['title', 'password：export-fullwidth-secret'],
        'smart_cookie' => ['summary', '“coo-kie”：“export-smart-cookie-secret”'],
        'double_encoded' => ['title', '"{\\"password\\":\\"double-secret-24680\\"}"'],
        'unicode_prefix' => ['title', '官方摘录 {"pass\\u0077ord":"unicode-secret-123"}'],
        'percent_encoded' => ['summary', '官方摘录%20%7B%22password%22%3A%22percent-secret-13579%22%7D'],
    ];
    foreach ($sensitiveCases as $scope => [$location, $sensitiveValue]) {
        $candidate = export_inject_sensitive_value(
            export_candidate($ids[$scope], $companyId),
            $location,
            $sensitiveValue
        );
        Db::name('qms_external_change_candidates')->insert($candidate);
    }

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
    export_assert(
        (string)$packet['candidate']['announcement_number'] === '市场监管总局公告2026年第14号'
            && (string)$packet['candidate']['published_date'] === '2026-07-01'
            && (string)$packet['candidate']['effective_date'] === '2026-08-01',
        '正常公告号和日期不得被敏感内容门禁误杀'
    );
    export_assert(
        (new RegulatoryExportService())->exportCandidate($ids['safe_json'])['candidate']['title'] === $safeJson['title'],
        '仅含安全业务字段的 JSON 字符串不得被误杀'
    );
    export_expect_exception(
        fn () => (new RegulatoryExportService())->exportCandidate($ids['deep_json']),
        UnexpectedValueException::class,
        '法规候选导出内容无法安全检查',
        '超过递归扫描深度的疑似 JSON 必须 fail-closed'
    );
    export_assert(
        (new RegulatoryExportService())->exportCandidate($ids['double_safe'])['candidate']['title'] === $doubleSafe['title'],
        '双重编码但仅含安全业务字段的 JSON 不得被误杀或改写'
    );

    foreach ($sensitiveCases as $scope => [, $sensitiveValue]) {
        export_rejects_sensitive_candidate(
            $service,
            $ids[$scope],
            $sensitiveValue,
            '最终导出字符串命中敏感内容时必须 fail-closed: ' . $scope
        );
    }
    foreach (['double_encoded', 'unicode_prefix', 'percent_encoded'] as $scope) {
        $httpException = export_expect_http_exception(
            fn () => export_controller_response($app, $ids[$scope]),
            422,
            '法规候选复核包暂无法安全导出',
            '编码敏感内容阻断必须稳定映射为 422: ' . $scope
        );
        export_assert(
            !str_contains($httpException->getMessage(), $sensitiveCases[$scope][1]),
            '422 安全错误不得回显编码敏感原值: ' . $scope
        );
    }

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
        export_expect_exception(
            fn () => (new RegulatoryExportService())->exportCandidate($ids['visible']),
            DomainException::class,
            '无权导出法规候选复核包',
            $role . ' 不得通过服务层导出'
        );
        export_expect_http_exception(
            fn () => export_controller_response($app, $ids['visible']),
            403,
            '无权执行法规监测操作',
            $role . ' 不得通过控制器导出'
        );
    }

    Session::set('user', ['id' => 'export-admin', 'role' => 'admin']);
    foreach (['other', 'hidden', 'deleted'] as $scope) {
        export_expect_exception(
            fn () => (new RegulatoryExportService())->exportCandidate($ids[$scope]),
            OutOfBoundsException::class,
            '法规候选不存在或无权导出',
            '不得导出越机构或不可见候选: ' . $scope
        );
        export_expect_http_exception(
            fn () => export_controller_response($app, $ids[$scope]),
            404,
            '法规候选不存在或无权导出',
            '控制器必须将越机构或不可见候选映射为 404: ' . $scope
        );
    }
    export_expect_exception(
        fn () => (new RegulatoryExportService())->exportCandidate($ids['unsafe_url']),
        UnexpectedValueException::class,
        '法规候选官方来源链接无法安全导出',
        '非 HTTPS 候选来源不得导出'
    );
    export_expect_http_exception(
        fn () => export_controller_response($app, $ids['unsafe_url']),
        422,
        '法规候选复核包暂无法安全导出',
        '导出契约或安全校验失败必须映射为 422'
    );

    $loggedSecret = $sensitiveCases['json_password'][1];
    $blockedLogMessages = [];
    $app->event->listen(LogRecord::class, static function (LogRecord $record) use (&$blockedLogMessages): void {
        $blockedLogMessages[] = (string)$record->message;
    });
    export_expect_http_exception(
        fn () => export_controller_response($app, $ids['json_password']),
        422,
        '法规候选复核包暂无法安全导出',
        '敏感内容阻断必须映射为 422'
    );
    $blockedLogs = implode("\n", $blockedLogMessages);
    export_assert(str_contains($blockedLogs, 'REG_EXPORT_VALIDATION_BLOCKED'), '422 阻断必须记录固定错误码');
    export_assert(str_contains($blockedLogs, substr(hash('sha256', $ids['json_password']), 0, 16)), '422 阻断日志必须仅记录候选 ID 哈希');
    export_assert(!str_contains($blockedLogs, $loggedSecret), '422 阻断日志不得记录敏感原值');

    foreach (["bad\r\nX-Test: injected", '../bad', '', str_repeat('x', 37)] as $invalidId) {
        export_expect_exception(
            fn () => (new RegulatoryExportService())->exportCandidate($invalidId),
            InvalidArgumentException::class,
            '候选 ID 必须是 1–36 位安全标识符',
            '不安全候选 ID 必须在查询/响应头前拒绝'
        );
        export_expect_exception(
            fn () => (new RegulatoryExportService())->filename($invalidId),
            InvalidArgumentException::class,
            '候选 ID 必须是 1–36 位安全标识符',
            '不安全候选 ID 不得进入文件名'
        );
        export_expect_http_exception(
            fn () => export_controller_response($app, $invalidId),
            404,
            '法规候选不存在或无权导出',
            '控制器必须将无效 ID 映射为 404'
        );
    }

    export_set_enabled(false);
    export_expect_exception(
        fn () => (new RegulatoryExportService())->exportCandidate($ids['visible']),
        RuntimeException::class,
        '法规监测功能未启用',
        '功能开关关闭时必须拒绝导出'
    );
    export_set_enabled(true);

    $qmsConfig = (array)Config::get('qms', []);
    $configuredCompanyId = $qmsConfig['company_id'] ?? null;
    $qmsConfig['company_id'] = '';
    Config::set($qmsConfig, 'qms');
    try {
        export_expect_exception(
            fn () => export_controller_response($app, $ids['visible']),
            RuntimeException::class,
            '法规监测缺少 company_id 配置',
            '内部/配置异常不得被误映射为 404 或 422'
        );
    } finally {
        $qmsConfig['company_id'] = $configuredCompanyId;
        Config::set($qmsConfig, 'qms');
    }

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
