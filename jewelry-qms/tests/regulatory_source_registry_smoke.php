<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use app\service\regulatory\HtmlListSourceAdapter;
use app\service\regulatory\ManualOnlySourceAdapter;
use app\service\regulatory\RegulatoryHttpClient;
use app\service\regulatory\RegulatorySourceAdapterInterface;
use app\service\regulatory\RegulatorySourceRegistry;

function regulatory_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function regulatory_assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . PHP_EOL
            . 'Expected: ' . var_export($expected, true) . PHP_EOL
            . 'Actual: ' . var_export($actual, true)
        );
    }
}

function regulatory_assert_throws(callable $callback, string $messageNeedle, string $message): void
{
    try {
        $callback();
    } catch (Throwable $exception) {
        regulatory_assert(
            str_contains($exception->getMessage(), $messageNeedle),
            $message . '; unexpected message: ' . $exception->getMessage()
        );
        return;
    }

    throw new RuntimeException($message . '; expected an exception');
}

function make_http_client(
    array $allowedHosts,
    array $dns,
    ?callable $transport = null,
    ?callable $clock = null
): RegulatoryHttpClient {
    $resolver = static function (string $host, float $timeout = 20.0) use ($dns): array {
        return $dns[$host] ?? [];
    };

    $transport ??= static function (string $url, array $options): array {
        return ['status' => 200, 'headers' => [], 'body' => 'offline fixture response'];
    };

    return new RegulatoryHttpClient($allowedHosts, $resolver, $transport, $clock);
}

$expectedSources = [
    'samr_rkjcs_notice' => ['html_list', 'https://www.samr.gov.cn/rkjcs/tzgg/index.html'],
    'cnas_lab_notice' => ['html_list', 'https://www.cnas.org.cn/rkfw/sys/zxtz/index.html'],
    'cnas_lab_rules' => ['html_list', 'https://www.cnas.org.cn/rkfw/sys/rkyq/rkzz/index.html'],
    'xinjiang_samr_notice' => ['html_list', 'https://scjgj.xinjiang.gov.cn/xjaic/tzgg/'],
    'cma_capability_query' => ['manual_only', 'https://cma.caqit.org.cn/'],
];

$registry = new RegulatorySourceRegistry();
$sources = $registry->all();
regulatory_assert_same(array_keys($expectedSources), array_keys($sources), 'Registry must contain only approved source keys');
foreach ($expectedSources as $key => [$mode, $entryUrl]) {
    $source = $registry->source($key);
    regulatory_assert_same($key, $source['key'], 'Source key must be immutable: ' . $key);
    regulatory_assert_same($mode, $source['mode'], 'Source mode mismatch: ' . $key);
    regulatory_assert_same($entryUrl, $source['entry_url'], 'Source entry URL mismatch: ' . $key);
    regulatory_assert(
        in_array(parse_url($entryUrl, PHP_URL_HOST), $source['allowed_hosts'], true),
        'Entry host must be explicitly allowlisted: ' . $key
    );
}
regulatory_assert_throws(
    static fn () => $registry->source('https://user.example/custom-feed'),
    '未批准',
    'Registry must reject arbitrary user URLs and unknown source keys'
);
regulatory_assert_same(
    ['www.samr.gov.cn'],
    $registry->allowedHosts('samr_rkjcs_notice'),
    'HTTP allowlist must be scoped to one approved source'
);
regulatory_assert_throws(
    static fn () => $registry->httpClientFor('cma_capability_query'),
    '人工',
    'Manual-only source must structurally prohibit HTTP client creation'
);

$htmlAdapter = $registry->adapterFor('samr_rkjcs_notice');
regulatory_assert($htmlAdapter instanceof RegulatorySourceAdapterInterface, 'HTML adapter must implement source interface');
regulatory_assert($htmlAdapter instanceof HtmlListSourceAdapter, 'HTML source must resolve to HtmlListSourceAdapter');
regulatory_assert($htmlAdapter->supports('html_list'), 'HTML adapter must match html_list mode');
regulatory_assert(!$htmlAdapter->supports('manual_only'), 'HTML adapter must not match manual_only mode');

$manualAdapter = $registry->adapterFor('cma_capability_query');
regulatory_assert($manualAdapter instanceof RegulatorySourceAdapterInterface, 'Manual adapter must implement source interface');
regulatory_assert($manualAdapter instanceof ManualOnlySourceAdapter, 'Manual source must resolve to ManualOnlySourceAdapter');
regulatory_assert($manualAdapter->supports('manual_only'), 'Manual adapter must match manual_only mode');
$manualResult = $manualAdapter->parse('body must be ignored', $registry->source('cma_capability_query'));
regulatory_assert_same([], $manualResult['items'], 'Manual-only source must not synthesize fetched items');
regulatory_assert_same(true, $manualResult['requires_manual_verification'], 'Manual-only source must require human verification');
regulatory_assert(
    str_contains($manualResult['message'], '人工')
        && str_contains($manualResult['message'], '验证码')
        && str_contains($manualResult['message'], '不得'),
    'Manual-only result must explicitly prohibit captcha/JS bypass and request human verification'
);

$fixtureDir = __DIR__ . '/fixtures/regulatory';
$samrHtml = (string)file_get_contents($fixtureDir . '/samr_one_list_one_library.html');
$samrResult = $htmlAdapter->parse($samrHtml, $registry->source('samr_rkjcs_notice'));
regulatory_assert_same(false, $samrResult['requires_manual_verification'], 'HTML result must not require manual collection');
regulatory_assert_same(1, count($samrResult['items']), 'SAMR parser must ignore links outside configured list nodes');
$samrItem = $samrResult['items'][0];
regulatory_assert_same(
    '市场监管总局关于发布检验检测机构能力验证结果的公告',
    $samrItem['title'],
    'SAMR title normalization failed'
);
regulatory_assert_same(
    'https://www.samr.gov.cn/rkjcs/tzgg/202607/t20260714_123456.html',
    $samrItem['canonical_url'],
    'SAMR relative URL normalization failed'
);
regulatory_assert_same('2026-07-14', $samrItem['published_date'], 'SAMR published date normalization failed');
regulatory_assert_same(
    '国家市场监督管理总局公告 2026 年第 28 号',
    $samrItem['announcement_number'],
    'SAMR announcement number normalization failed'
);
regulatory_assert(str_contains($samrItem['summary'], '能力验证结果'), 'SAMR summary evidence missing');
regulatory_assert_same('samr_rkjcs_notice', $samrItem['evidence']['source_key'], 'SAMR evidence source key missing');
regulatory_assert(str_contains($samrItem['evidence']['raw_text'], '能力验证结果'), 'SAMR raw evidence missing');

$cnasAdapter = $registry->adapterFor('cnas_lab_notice');
$cnasHtml = (string)file_get_contents($fixtureDir . '/cnas_notice_list.html');
$cnasResult = $cnasAdapter->parse($cnasHtml, $registry->source('cnas_lab_notice'));
regulatory_assert_same(1, count($cnasResult['items']), 'CNAS parser must return one normalized item');
$cnasItem = $cnasResult['items'][0];
regulatory_assert_same('关于修订实验室认可规则的通知', $cnasItem['title'], 'CNAS title normalization failed');
regulatory_assert_same(
    'https://www.cnas.org.cn/rkfw/sys/zxtz/2026/07/notice-42.html',
    $cnasItem['canonical_url'],
    'CNAS parent-relative URL normalization failed'
);
regulatory_assert_same('2026-07-10', $cnasItem['published_date'], 'CNAS published date normalization failed');
regulatory_assert_same(null, $cnasItem['announcement_number'], 'CNAS missing announcement number must normalize to null');
regulatory_assert(str_contains($cnasItem['summary'], '变更管理'), 'CNAS summary evidence missing');
regulatory_assert(isset($cnasItem['evidence']['raw_text']), 'CNAS evidence must be present');

$officialDns = [
    'www.samr.gov.cn' => ['8.8.8.8'],
    'www.cnas.org.cn' => ['8.8.4.4'],
    'scjgj.xinjiang.gov.cn' => ['1.1.1.1'],
    'cma.caqit.org.cn' => ['1.0.0.1'],
    'evil.example' => ['9.9.9.9'],
    'samr.gov.cn.evil.example' => ['149.112.112.112'],
];
$allowedHosts = $registry->allowedHosts('samr_rkjcs_notice');

foreach ([
    ['http://www.samr.gov.cn/rkjcs/tzgg/index.html', 'HTTPS', 'HTTP URL'],
    ['https://user:secret@www.samr.gov.cn/rkjcs/tzgg/index.html', 'userinfo', 'URL userinfo'],
    ['https://www.samr.gov.cn:444/rkjcs/tzgg/index.html', '端口', 'non-allowed port'],
    ['https://evil.example/notices', '白名单', 'non-allowlisted host'],
    ['https://samr.gov.cn.evil.example/notices', '白名单', 'suffix-spoofed host'],
] as [$url, $needle, $case]) {
    $client = make_http_client($allowedHosts, $officialDns);
    regulatory_assert_throws(
        static fn () => $client->fetch($url),
        $needle,
        'HTTP client must reject ' . $case
    );
}

foreach ([
    '127.0.0.1' => 'loopback',
    '10.0.0.8' => 'private',
    '169.254.169.254' => 'link-local',
    '192.0.0.1' => 'IETF protocol assignment/reserved',
    '192.0.2.10' => 'reserved',
    '224.0.0.1' => 'multicast/reserved',
    '::1' => 'IPv6 loopback',
    'fc00::1' => 'IPv6 private',
    'fe80::1' => 'IPv6 link-local',
    'ff00::1' => 'IPv6 multicast/reserved',
] as $address => $case) {
    $client = make_http_client($allowedHosts, ['www.samr.gov.cn' => [$address]]);
    regulatory_assert_throws(
        static fn () => $client->fetch('https://www.samr.gov.cn/rkjcs/tzgg/index.html'),
        'IP',
        'HTTP client must reject DNS resolution to ' . $case
    );
}

$calls = [];
$redirectTransport = static function (string $url, array $options) use (&$calls): array {
    $calls[] = [$url, $options];
    if (count($calls) === 1) {
        return [
            'status' => 302,
            'headers' => ['location' => '../final.html'],
            'body' => '',
        ];
    }
    return ['status' => 200, 'headers' => ['content-type' => 'text/html'], 'body' => 'redirected body'];
};
$client = make_http_client($allowedHosts, $officialDns, $redirectTransport);
regulatory_assert_same(
    'redirected body',
    $client->fetch('https://www.samr.gov.cn/rkjcs/tzgg/index.html'),
    'HTTP client must follow a validated relative redirect'
);
regulatory_assert_same(2, count($calls), 'HTTP client must perform one request per redirect hop');
regulatory_assert_same('https://www.samr.gov.cn/rkjcs/final.html', $calls[1][0], 'Redirect URL resolution failed');
foreach ($calls as [$url, $options]) {
    regulatory_assert_same(5, $options['connect_timeout'], 'Connect timeout must be 5 seconds');
    regulatory_assert(
        $options['timeout'] > 0 && $options['timeout'] <= 20,
        'Each request must stay within the 20-second total deadline'
    );
    regulatory_assert_same(5 * 1024 * 1024, $options['max_body_bytes'], 'Body limit must be 5 MiB');
    regulatory_assert(str_contains($options['user_agent'], 'LIMS-ZHJ-RegulatoryMonitor/'), 'User-Agent must be fixed and identifiable');
    $headerText = strtolower(implode("\n", $options['headers']));
    regulatory_assert(!str_contains($headerText, 'cookie:'), 'Cookie must not be forwarded across requests');
    regulatory_assert(!str_contains($headerText, 'authorization:'), 'Authorization must not be forwarded across requests');
}

$now = 100.0;
$dnsBudgets = [];
$requestBudgets = [];
$budgetResolver = static function (string $host, float $timeout) use (&$now, &$dnsBudgets): array {
    $dnsBudgets[] = $timeout;
    $now += 1.0;
    return ['8.8.8.8'];
};
$budgetTransport = static function (string $url, array $options) use (&$now, &$requestBudgets): array {
    $requestBudgets[] = $options['timeout'];
    if (count($requestBudgets) === 1) {
        $now += 12.0;
        return ['status' => 302, 'headers' => ['location' => '/within-budget'], 'body' => ''];
    }
    return ['status' => 200, 'headers' => [], 'body' => 'within total deadline'];
};
$budgetClient = new RegulatoryHttpClient(
    ['www.samr.gov.cn'],
    $budgetResolver,
    $budgetTransport,
    static function () use (&$now): float {
        return $now;
    }
);
regulatory_assert_same(
    'within total deadline',
    $budgetClient->fetch('https://www.samr.gov.cn/start'),
    'Redirect chain must complete inside one total deadline'
);
regulatory_assert_same([20.0, 7.0], $dnsBudgets, 'DNS must receive the remaining total timeout budget');
regulatory_assert_same([19.0, 6.0], $requestBudgets, 'Redirect hops must receive decreasing timeout budgets');

$now = 200.0;
$transportCalled = false;
$expiredDnsClient = new RegulatoryHttpClient(
    ['www.samr.gov.cn'],
    static function (string $host, float $timeout) use (&$now): array {
        $now += $timeout + 1.0;
        return ['8.8.8.8'];
    },
    static function (string $url, array $options) use (&$transportCalled): array {
        $transportCalled = true;
        return ['status' => 200, 'headers' => [], 'body' => 'must not run'];
    },
    static function () use (&$now): float {
        return $now;
    }
);
regulatory_assert_throws(
    static fn () => $expiredDnsClient->fetch('https://www.samr.gov.cn/start'),
    '总超时',
    'DNS resolution must stay inside the total timeout budget'
);
regulatory_assert_same(false, $transportCalled, 'Expired DNS budget must stop before transport');

foreach ([
    'https://evil.example/redirect-target' => '白名单',
    'https://www.cnas.org.cn/cross-source-target' => '白名单',
    'http://www.samr.gov.cn/insecure-target' => 'HTTPS',
] as $location => $needle) {
    $requestCount = 0;
    $transport = static function (string $url, array $options) use (&$requestCount, $location): array {
        $requestCount++;
        return ['status' => 302, 'headers' => ['location' => $location], 'body' => ''];
    };
    $client = make_http_client($allowedHosts, $officialDns, $transport);
    regulatory_assert_throws(
        static fn () => $client->fetch('https://www.samr.gov.cn/rkjcs/tzgg/index.html'),
        $needle,
        'Every redirect target must be revalidated'
    );
    regulatory_assert_same(1, $requestCount, 'Rejected redirect target must not receive a request');
}

$redirectCount = 0;
$loopingTransport = static function (string $url, array $options) use (&$redirectCount): array {
    $redirectCount++;
    return ['status' => 302, 'headers' => ['location' => '/loop'], 'body' => ''];
};
$client = make_http_client($allowedHosts, $officialDns, $loopingTransport);
regulatory_assert_throws(
    static fn () => $client->fetch('https://www.samr.gov.cn/start'),
    '重定向',
    'Redirects must be bounded'
);
regulatory_assert($redirectCount <= 4, 'Redirect limit must remain finite and small');

$tooLargeTransport = static fn (string $url, array $options): array => [
    'status' => 200,
    'headers' => [],
    'body' => str_repeat('x', (5 * 1024 * 1024) + 1),
];
$client = make_http_client($allowedHosts, $officialDns, $tooLargeTransport);
regulatory_assert_throws(
    static fn () => $client->fetch('https://www.samr.gov.cn/large'),
    '5 MiB',
    'Response body must be capped at 5 MiB'
);

echo "regulatory_source_registry_smoke passed\n";
