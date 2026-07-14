<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use app\service\regulatory\HtmlListSourceAdapter;
use app\service\regulatory\ManualOnlySourceAdapter;
use app\service\regulatory\RegulatoryHttpHeaderAccumulator;
use app\service\regulatory\RegulatoryHttpClient;
use app\service\regulatory\RegulatorySourceAdapterInterface;
use app\service\regulatory\RegulatorySourceRegistry;
use app\service\regulatory\RegulatoryUrlNormalizer;

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
        return ['status' => 200, 'headers' => ['content-type' => 'text/html; charset=UTF-8'], 'body' => 'offline fixture response'];
    };

    return new RegulatoryHttpClient($allowedHosts, $resolver, $transport, $clock);
}

function regulatory_assert_no_default_route(): void
{
    // This security regression intentionally requires a container started with
    // `docker run --network none`; a normal Compose network is not isolated enough.
    $routes = @file('/proc/net/route', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    regulatory_assert(is_array($routes), 'Real cURL proxy regression requires Linux /proc route inspection');
    foreach ($routes as $index => $route) {
        if ($index === 0) {
            continue;
        }
        $columns = preg_split('/\s+/', trim($route)) ?: [];
        if (($columns[1] ?? '') === '00000000') {
            throw new RuntimeException(
                'Real cURL proxy regression must run in Docker --network none (default route detected)'
            );
        }
    }
}

/** @return array{process: resource, pipes: array<int, resource>, address: string, log_path: string, temp_dir: string} */
function regulatory_start_fake_connect_proxy(): array
{
    $tempDir = sys_get_temp_dir() . '/regulatory-proxy-' . bin2hex(random_bytes(6));
    regulatory_assert(mkdir($tempDir, 0700), 'Unable to create fake proxy temp directory');
    $logPath = $tempDir . '/connect.log';
    $script = <<<'PHP'
$logPath = (string)($argv[1] ?? '');
$server = @stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if (!is_resource($server)) {
    fwrite(STDERR, 'proxy bind failed');
    exit(2);
}
echo stream_socket_get_name($server, false) . "\n";
flush();
$client = @stream_socket_accept($server, 5);
if (is_resource($client)) {
    stream_set_timeout($client, 1);
    $request = '';
    while (!feof($client)) {
        $line = fgets($client);
        if ($line === false) {
            break;
        }
        $request .= $line;
        if ($line === "\r\n" || $line === "\n") {
            break;
        }
    }
    file_put_contents($logPath, $request, LOCK_EX);
    fwrite($client, "HTTP/1.1 502 Fake Proxy Rejected CONNECT\r\nContent-Length: 0\r\n\r\n");
    fclose($client);
}
fclose($server);
PHP;
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open([PHP_BINARY, '-r', $script, $logPath], $descriptors, $pipes);
    if (!is_resource($process)) {
        @rmdir($tempDir);
        throw new RuntimeException('Unable to start fake CONNECT proxy');
    }
    fclose($pipes[0]);
    stream_set_timeout($pipes[1], 2);
    $address = trim((string)fgets($pipes[1]));
    if (!preg_match('/^127\.0\.0\.1:\d+$/', $address)) {
        $error = trim((string)stream_get_contents($pipes[2]));
        proc_terminate($process);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        @unlink($logPath);
        @rmdir($tempDir);
        throw new RuntimeException('Fake CONNECT proxy did not start: ' . $error);
    }

    return [
        'process' => $process,
        'pipes' => $pipes,
        'address' => $address,
        'log_path' => $logPath,
        'temp_dir' => $tempDir,
    ];
}

function regulatory_stop_fake_connect_proxy(array $proxy): void
{
    if (isset($proxy['process']) && is_resource($proxy['process'])) {
        $status = proc_get_status($proxy['process']);
        if ($status['running']) {
            proc_terminate($proxy['process']);
        }
    }
    foreach ((array)($proxy['pipes'] ?? []) as $pipe) {
        if (is_resource($pipe)) {
            fclose($pipe);
        }
    }
    if (isset($proxy['process']) && is_resource($proxy['process'])) {
        proc_close($proxy['process']);
    }
    if (isset($proxy['log_path'])) {
        @unlink((string)$proxy['log_path']);
    }
    if (isset($proxy['temp_dir'])) {
        @rmdir((string)$proxy['temp_dir']);
    }
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

$headerAccumulator = new RegulatoryHttpHeaderAccumulator();
foreach ([
    "HTTP/1.1 103 Early Hints\r\n",
    "Content-Type: text/html\r\n",
    "Location: https://www.samr.gov.cn/stale-redirect\r\n",
    "\r\n",
    "HTTP/1.1 200 OK\r\n",
    "Content-Type: application/pdf\r\n",
    "X-Final: yes\r\n",
    "\r\n",
    "Content-Type: text/html\r\n",
] as $headerLine) {
    $headerAccumulator->consume($headerLine);
}
regulatory_assert_same(
    ['application/pdf'],
    $headerAccumulator->headers()['content-type'] ?? null,
    'Final response Content-Type must replace interim headers and ignore trailers'
);
regulatory_assert(
    !isset($headerAccumulator->headers()['location']),
    'Interim Location must not leak into the final response headers'
);

$missingFinalHeaderAccumulator = new RegulatoryHttpHeaderAccumulator();
foreach ([
    "HTTP/1.1 103 Early Hints\r\n",
    "Content-Type: text/html\r\n",
    "\r\n",
    "HTTP/1.1 200 OK\r\n",
    "X-Final: yes\r\n",
    "\r\n",
] as $headerLine) {
    $missingFinalHeaderAccumulator->consume($headerLine);
}
regulatory_assert(
    !isset($missingFinalHeaderAccumulator->headers()['content-type']),
    'Missing final Content-Type must not inherit an interim HTML value'
);

$samrAllowedHosts = $registry->allowedHosts('samr_rkjcs_notice');
regulatory_assert_same(
    'https://www.samr.gov.cn/rkjcs/tzgg/item.html?a=1&b=2&case=ABC',
    RegulatoryUrlNormalizer::normalize(
        'HTTPS://WWW.SAMR.GOV.CN:443/rkjcs/tzgg/../tzgg/item.html?utm_source=x&case=ABC&spm=1&from=feed&b=2&a=1#fragment',
        $samrAllowedHosts
    ),
    'Absolute URL canonicalization must normalize host, port, path, query and tracking parameters'
);
regulatory_assert_same(
    'https://www.samr.gov.cn/rkjcs/tzgg/item.html?a=1&b=2',
    RegulatoryUrlNormalizer::normalize(
        './item.html?b=2&utm_medium=email&a=1#fragment',
        $samrAllowedHosts,
        $registry->source('samr_rkjcs_notice')['entry_url']
    ),
    'Relative URL canonicalization must match absolute URL behavior'
);
regulatory_assert_same(
    'https://www.samr.gov.cn/item?case=1&case=2&empty=&flag=',
    RegulatoryUrlNormalizer::normalize(
        'https://www.samr.gov.cn/item?flag&case=2&empty=&case=1',
        $samrAllowedHosts
    ),
    'Canonical query must retain business parameters, duplicates and stable ordering'
);
regulatory_assert_same(
    'https://www.samr.gov.cn/item?a=1&b=2',
    RegulatoryUrlNormalizer::normalize(
        '#fragment-only',
        $samrAllowedHosts,
        'https://www.samr.gov.cn/item?b=2&a=1'
    ),
    'Fragment-only relative reference must retain and canonicalize the base business query'
);
regulatory_assert_same(
    'https://www.samr.gov.cn/item?literal=a%2Bb&q=a%20b',
    RegulatoryUrlNormalizer::normalize(
        'https://www.samr.gov.cn/item?q=a+b&literal=a%2Bb',
        $samrAllowedHosts
    ),
    'Canonical query must preserve application/x-www-form-urlencoded plus semantics'
);
foreach ([
    "https://www.samr.gov.cn/item\r\nInjected: value",
    'https://www.samr.gov.cn/item?value=%0Dheader',
    './item?value=%250Aheader',
] as $unsafeUrl) {
    regulatory_assert_throws(
        static fn () => RegulatoryUrlNormalizer::normalize(
            $unsafeUrl,
            $samrAllowedHosts,
            $registry->source('samr_rkjcs_notice')['entry_url']
        ),
        '控制字符',
        'Canonical URL must reject raw or decoded ASCII control characters'
    );
}

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

$cnasRulesAdapter = $registry->adapterFor('cnas_lab_rules');
$cnasRulesHtml = (string)file_get_contents($fixtureDir . '/cnas_lab_rules.html');
$cnasRulesResult = $cnasRulesAdapter->parse($cnasRulesHtml, $registry->source('cnas_lab_rules'));
regulatory_assert_same(1, count($cnasRulesResult['items']), 'CNAS rules fixture must return one normalized item');
$cnasRulesItem = $cnasRulesResult['items'][0];
regulatory_assert_same('CNAS-RL01：2026《实验室认可规则》发布通知', $cnasRulesItem['title'], 'CNAS rules title normalization failed');
regulatory_assert_same(
    'https://www.cnas.org.cn/rkfw/sys/rkyq/rkzz/2026/rule-7.html',
    $cnasRulesItem['canonical_url'],
    'CNAS rules relative URL normalization failed'
);
regulatory_assert_same('2026-07-08', $cnasRulesItem['published_date'], 'CNAS rules date normalization failed');
regulatory_assert_same('CNAS-RL01:2026', $cnasRulesItem['announcement_number'], 'CNAS rules number normalization failed');
regulatory_assert(str_contains($cnasRulesItem['summary'], '过渡安排'), 'CNAS rules summary evidence missing');

$xinjiangAdapter = $registry->adapterFor('xinjiang_samr_notice');
$xinjiangHtml = (string)file_get_contents($fixtureDir . '/xinjiang_notice_list.html');
$xinjiangResult = $xinjiangAdapter->parse($xinjiangHtml, $registry->source('xinjiang_samr_notice'));
regulatory_assert_same(1, count($xinjiangResult['items']), 'Xinjiang fixture must return one normalized item');
$xinjiangItem = $xinjiangResult['items'][0];
regulatory_assert_same(
    '自治区市场监督管理局关于开展检验检测专项检查的通知',
    $xinjiangItem['title'],
    'Xinjiang title normalization failed'
);
regulatory_assert_same(
    'https://scjgj.xinjiang.gov.cn/xjaic/tzgg/202607/notice-18.html',
    $xinjiangItem['canonical_url'],
    'Xinjiang URL normalization failed'
);
regulatory_assert_same('2026-07-06', $xinjiangItem['published_date'], 'Xinjiang date normalization failed');
regulatory_assert_same(null, $xinjiangItem['announcement_number'], 'Xinjiang missing number must normalize to null');
regulatory_assert(str_contains($xinjiangItem['summary'], '专项检查'), 'Xinjiang summary evidence missing');

regulatory_assert_throws(
    static fn () => $htmlAdapter->parse(
        '<html><body><ul class="unexpected-list"><li><a href="/notice.html">结构漂移</a></li></ul></body></html>',
        $registry->source('samr_rkjcs_notice')
    ),
    '列表结构',
    'HTML parser must audit a configured XPath that matches zero nodes'
);
regulatory_assert_throws(
    static fn () => $htmlAdapter->parse(
        '<html><body><ul class="news-list"><li><span>缺少有效链接</span></li></ul></body></html>',
        $registry->source('samr_rkjcs_notice')
    ),
    '有效条目',
    'HTML parser must audit list nodes that produce zero valid items'
);

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
    ['application/pdf', '%PDF-1.7', 'Content-Type'],
    ['', '<html><body>missing content type</body></html>', 'Content-Type'],
] as [$contentType, $body, $needle]) {
    $transport = static fn (string $url, array $options): array => [
        'status' => 200,
        'headers' => $contentType === '' ? [] : ['content-type' => $contentType],
        'body' => $body,
    ];
    $client = make_http_client($allowedHosts, $officialDns, $transport);
    regulatory_assert_throws(
        static fn () => $client->fetch('https://www.samr.gov.cn/rkjcs/tzgg/index.html'),
        $needle,
        'HTTP client must reject a response without an explicit HTML Content-Type'
    );
}

$xhtmlTransport = static fn (string $url, array $options): array => [
    'status' => 200,
    'headers' => ['content-type' => 'Application/XHTML+XML; charset=UTF-8'],
    'body' => '<html xmlns="http://www.w3.org/1999/xhtml"><body>ok</body></html>',
];
$xhtmlClient = make_http_client($allowedHosts, $officialDns, $xhtmlTransport);
regulatory_assert(
    str_contains($xhtmlClient->fetch('https://www.samr.gov.cn/xhtml'), '<body>ok</body>'),
    'HTTP client must accept application/xhtml+xml with charset'
);

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
    '64:ff9b:1::' => 'IPv4-IPv6 local translation range start',
    '64:ff9b:1:ffff:ffff:ffff:ffff:ffff' => 'IPv4-IPv6 local translation range end',
    '64:ff9b:0:1::' => 'address immediately outside the globally reachable translation /96',
    '100::' => 'discard-only range start',
    '100::ffff:ffff:ffff:ffff' => 'discard-only range end',
    '100:0:0:1::' => 'dummy IPv6 range start',
    '100:0:0:1:ffff:ffff:ffff:ffff' => 'dummy IPv6 range end',
    '2001:1::4' => 'IETF protocol assignment without a globally reachable exception',
    '2001:2::' => 'benchmarking range start',
    '2001:2:0:ffff:ffff:ffff:ffff:ffff' => 'benchmarking range end',
    '2001:db8::' => 'documentation range start',
    '2001:db8:ffff:ffff:ffff:ffff:ffff:ffff' => 'documentation range end',
    '2002::' => '6to4 range start',
    '2002:ffff:ffff:ffff:ffff:ffff:ffff:ffff' => '6to4 range end',
    '3fff::' => 'documentation range start',
    '3fff:fff:ffff:ffff:ffff:ffff:ffff:ffff' => 'documentation range end',
    '5f00::' => 'SRv6 SID range start',
    '5f00:ffff:ffff:ffff:ffff:ffff:ffff:ffff' => 'SRv6 SID range end',
] as $address => $case) {
    $client = make_http_client($allowedHosts, ['www.samr.gov.cn' => [$address]]);
    regulatory_assert_throws(
        static fn () => $client->fetch('https://www.samr.gov.cn/rkjcs/tzgg/index.html'),
        'IP',
        'HTTP client must reject DNS resolution to ' . $case
    );
}

foreach ([
    '64:ff9b::' => 'globally reachable IPv4-IPv6 translation range start',
    '64:ff9b::ffff:ffff' => 'globally reachable IPv4-IPv6 translation range end',
    '2001:1::1' => 'PCP anycast exception',
    '2001:1::2' => 'TURN anycast exception',
    '2001:1::3' => 'DNS-SD anycast exception',
    '2001:3::' => 'AMT exception start',
    '2001:3:ffff:ffff:ffff:ffff:ffff:ffff' => 'AMT exception end',
    '2001:4:112::' => 'AS112 exception start',
    '2001:4:112:ffff:ffff:ffff:ffff:ffff' => 'AS112 exception end',
    '2001:20::' => 'ORCHIDv2 exception start',
    '2001:2f:ffff:ffff:ffff:ffff:ffff:ffff' => 'ORCHIDv2 exception end',
    '2001:30::' => 'DETs exception start',
    '2001:3f:ffff:ffff:ffff:ffff:ffff:ffff' => 'DETs exception end',
    '2001:200::' => 'address immediately outside the non-global IETF assignment /23',
    '3fff:1000::' => 'address immediately outside the documentation /20',
    '2001:4860:4860::8888' => 'ordinary public IPv6 address',
] as $address => $case) {
    $client = make_http_client($allowedHosts, ['www.samr.gov.cn' => [$address]]);
    regulatory_assert_same(
        'offline fixture response',
        $client->fetch('https://www.samr.gov.cn/rkjcs/tzgg/index.html'),
        'HTTP client must accept ' . $case
    );
}

regulatory_assert_no_default_route();
$proxy = [];
$proxyEnvironment = [];
$proxyVariables = ['HTTP_PROXY', 'HTTPS_PROXY', 'ALL_PROXY', 'http_proxy', 'https_proxy', 'all_proxy'];
$noProxyVariables = ['NO_PROXY', 'no_proxy'];
try {
    $proxy = regulatory_start_fake_connect_proxy();
    $proxyUrl = 'http://proxy-user:proxy-secret@' . $proxy['address'];
    foreach (array_merge($proxyVariables, $noProxyVariables) as $variable) {
        $proxyEnvironment[$variable] = getenv($variable);
    }
    foreach ($proxyVariables as $variable) {
        putenv($variable . '=' . $proxyUrl);
    }
    foreach ($noProxyVariables as $variable) {
        putenv($variable . '=');
    }

    $validatedAddresses = [];
    $realCurlClient = new RegulatoryHttpClient(
        ['www.samr.gov.cn'],
        static function (string $host, float $timeout) use (&$validatedAddresses): array {
            $validatedAddresses = ['8.8.8.8'];
            return $validatedAddresses;
        }
    );
    $realCurlFailure = null;
    try {
        $realCurlClient->fetch('https://www.samr.gov.cn/proxy-regression');
    } catch (Throwable $exception) {
        $realCurlFailure = $exception;
    }
    usleep(100_000);
    $proxyRequest = is_file($proxy['log_path'])
        ? (string)file_get_contents($proxy['log_path'])
        : '';

    regulatory_assert_same(['8.8.8.8'], $validatedAddresses, 'Real cURL regression must inject a validated public resolved_ips value');
    regulatory_assert($realCurlFailure instanceof Throwable, 'Fixed-IP request without external network must fail safely');
    regulatory_assert(
        str_contains($realCurlFailure->getMessage(), 'cURL 错误码 7'),
        'Real cURL must attempt the fixed IP directly (connect failure), not retry DNS or proxy resolution'
    );
    regulatory_assert_same('', $proxyRequest, 'Real cURL transport must not connect to an environment proxy');
} finally {
    foreach (array_merge($proxyVariables, $noProxyVariables) as $variable) {
        $previous = $proxyEnvironment[$variable] ?? false;
        putenv($previous === false ? $variable : $variable . '=' . $previous);
    }
    regulatory_stop_fake_connect_proxy($proxy);
}

$calls = [];
$redirectTransport = static function (string $url, array $options) use (&$calls): array {
    $calls[] = [$url, $options];
    if (count($calls) === 1) {
        return [
            'status' => 302,
            'headers' => ['location' => '../final.html?utm_source=redirect&b=2&a=1#section'],
            'body' => '',
        ];
    }
    return ['status' => 200, 'headers' => ['content-type' => 'text/html'], 'body' => 'redirected body'];
};
$client = make_http_client($allowedHosts, $officialDns, $redirectTransport);
regulatory_assert_same(
    'redirected body',
    $client->fetch('HTTPS://WWW.SAMR.GOV.CN:443/rkjcs/tzgg/index.html?utm_campaign=start#top'),
    'HTTP client must follow a validated relative redirect'
);
regulatory_assert_same(2, count($calls), 'HTTP client must perform one request per redirect hop');
regulatory_assert_same(
    'https://www.samr.gov.cn/rkjcs/tzgg/index.html',
    $calls[0][0],
    'Initial HTTP URL must use the shared canonicalization entry point'
);
regulatory_assert_same('https://www.samr.gov.cn/rkjcs/final.html?a=1&b=2', $calls[1][0], 'Redirect URL canonicalization failed');
foreach ($calls as [$url, $options]) {
    regulatory_assert_same(5, $options['connect_timeout'], 'Connect timeout must be 5 seconds');
    regulatory_assert(
        $options['timeout'] > 0 && $options['timeout'] <= 20,
        'Each request must stay within the 20-second total deadline'
    );
    regulatory_assert_same(5 * 1024 * 1024, $options['max_body_bytes'], 'Body limit must be 5 MiB');
    regulatory_assert(str_contains($options['user_agent'], 'LIMS-ZHJ-RegulatoryMonitor/'), 'User-Agent must be fixed and identifiable');
    regulatory_assert_same(['8.8.8.8'], $options['resolved_ips'], 'Transport options must carry the validated fixed IP');
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
    return ['status' => 200, 'headers' => ['content-type' => 'text/html'], 'body' => 'within total deadline'];
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
