<?php
declare(strict_types=1);

namespace app\service\regulatory;

use RuntimeException;

final class RegulatoryHttpClient
{
    private const CONNECT_TIMEOUT = 5;
    private const TIMEOUT = 20;
    private const MAX_BODY_BYTES = 5 * 1024 * 1024;
    private const MAX_REDIRECTS = 3;
    private const USER_AGENT = 'LIMS-ZHJ-RegulatoryMonitor/1.0 (+offline-governance-contact)';

    private array $allowedHosts;
    private $dnsResolver;
    private $transport;
    private $clock;

    public function __construct(
        array $allowedHosts,
        ?callable $dnsResolver = null,
        ?callable $transport = null,
        ?callable $clock = null
    ) {
        $this->allowedHosts = array_values(array_unique(array_map(
            static fn (mixed $host): string => strtolower(trim((string)$host)),
            $allowedHosts
        )));
        $this->dnsResolver = $dnsResolver ?? [$this, 'resolveDns'];
        $this->transport = $transport ?? [$this, 'curlTransport'];
        $this->clock = $clock ?? static fn (): float => hrtime(true) / 1_000_000_000;
    }

    public function fetch(string $url): string
    {
        $currentUrl = $url;
        $redirects = 0;
        $deadline = ($this->clock)() + self::TIMEOUT;

        while (true) {
            $validated = $this->validateUrl($currentUrl, $deadline);
            $remaining = $this->remainingBudget($deadline);
            $options = [
                'connect_timeout' => self::CONNECT_TIMEOUT,
                'timeout' => $remaining,
                'max_body_bytes' => self::MAX_BODY_BYTES,
                'user_agent' => self::USER_AGENT,
                'headers' => ['Accept: text/html,application/xhtml+xml'],
                'resolved_ips' => $validated['resolved_ips'],
            ];
            $response = ($this->transport)($currentUrl, $options);
            if (!is_array($response)) {
                throw new RuntimeException('官方来源请求返回格式无效');
            }
            $this->remainingBudget($deadline);

            $status = (int)($response['status'] ?? 0);
            $headers = is_array($response['headers'] ?? null) ? $response['headers'] : [];
            $body = (string)($response['body'] ?? '');
            if (strlen($body) > self::MAX_BODY_BYTES) {
                throw new RuntimeException('官方来源响应体超过 5 MiB 上限');
            }

            if (in_array($status, [301, 302, 303, 307, 308], true)) {
                $location = $this->headerValue($headers, 'location');
                if ($location === null || trim($location) === '') {
                    throw new RuntimeException('官方来源重定向缺少 Location');
                }
                if ($redirects >= self::MAX_REDIRECTS) {
                    throw new RuntimeException('官方来源重定向次数超过上限');
                }
                $redirects++;
                $currentUrl = self::resolveUrl($currentUrl, trim($location));
                continue;
            }

            if ($status < 200 || $status >= 300) {
                throw new RuntimeException('官方来源请求失败，HTTP 状态码：' . $status);
            }

            return $body;
        }
    }

    /** @return array{host: string, resolved_ips: array<int, string>} */
    public function validateUrl(string $url, ?float $deadline = null): array
    {
        $deadline ??= ($this->clock)() + self::TIMEOUT;
        if ($url === '' || preg_match('/[\x00-\x20\x7f]/', $url) === 1) {
            throw new RuntimeException('官方来源 URL 格式无效');
        }

        $parts = parse_url($url);
        if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https') {
            throw new RuntimeException('官方来源仅允许 HTTPS');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new RuntimeException('官方来源 URL 不允许 userinfo');
        }
        if (isset($parts['port']) && (int)$parts['port'] !== 443) {
            throw new RuntimeException('官方来源 URL 端口不在允许范围');
        }

        $host = strtolower((string)($parts['host'] ?? ''));
        if ($host === '' || !in_array($host, $this->allowedHosts, true)) {
            throw new RuntimeException('官方来源主机不在白名单');
        }

        $addresses = ($this->dnsResolver)($host, $this->remainingBudget($deadline));
        $this->remainingBudget($deadline);
        if (!is_array($addresses) || $addresses === []) {
            throw new RuntimeException('官方来源 DNS 未解析到可用 IP');
        }
        $addresses = array_values(array_unique(array_map('strval', $addresses)));
        foreach ($addresses as $address) {
            if (!$this->isPublicIp($address)) {
                throw new RuntimeException('官方来源 DNS 解析到禁止访问的 IP');
            }
        }

        return ['host' => $host, 'resolved_ips' => $addresses];
    }

    private function remainingBudget(float $deadline): float
    {
        $remaining = $deadline - ($this->clock)();
        if ($remaining <= 0) {
            throw new RuntimeException('官方来源请求超过 20 秒总超时');
        }

        return min((float)self::TIMEOUT, $remaining);
    }

    private function isPublicIp(string $address): bool
    {
        if (filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false) {
            return false;
        }

        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false
            && !$this->ipInCidr($address, '2000::/3')
        ) {
            return false;
        }

        foreach ([
            '100.64.0.0/10',
            '192.0.0.0/24',
            '192.0.2.0/24',
            '198.18.0.0/15',
            '198.51.100.0/24',
            '203.0.113.0/24',
            '192.88.99.0/24',
            '224.0.0.0/4',
            '64:ff9b:1::/48',
            '100::/64',
            '2001::/23',
            '2001:db8::/32',
            '2002::/16',
        ] as $blockedRange) {
            if ($this->ipInCidr($address, $blockedRange)) {
                return false;
            }
        }

        return true;
    }

    private function ipInCidr(string $address, string $cidr): bool
    {
        [$network, $prefixLength] = explode('/', $cidr, 2);
        $addressBytes = @inet_pton($address);
        $networkBytes = @inet_pton($network);
        if ($addressBytes === false || $networkBytes === false || strlen($addressBytes) !== strlen($networkBytes)) {
            return false;
        }

        $bits = (int)$prefixLength;
        $wholeBytes = intdiv($bits, 8);
        $remainingBits = $bits % 8;
        if ($wholeBytes > 0 && substr($addressBytes, 0, $wholeBytes) !== substr($networkBytes, 0, $wholeBytes)) {
            return false;
        }
        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xff << (8 - $remainingBits)) & 0xff;
        return (ord($addressBytes[$wholeBytes]) & $mask) === (ord($networkBytes[$wholeBytes]) & $mask);
    }

    public static function resolveUrl(string $baseUrl, string $reference): string
    {
        if ($reference === '') {
            throw new RuntimeException('相对 URL 不能为空');
        }
        if (str_starts_with($reference, '//')) {
            return 'https:' . $reference;
        }
        $referenceParts = parse_url($reference);
        if (is_array($referenceParts) && isset($referenceParts['scheme'])) {
            return $reference;
        }

        $base = parse_url($baseUrl);
        if (!is_array($base) || !isset($base['scheme'], $base['host'])) {
            throw new RuntimeException('基础 URL 格式无效');
        }

        $authority = $base['scheme'] . '://' . $base['host'];
        if (isset($base['port'])) {
            $authority .= ':' . $base['port'];
        }

        $query = '';
        $fragmentPosition = strpos($reference, '#');
        if ($fragmentPosition !== false) {
            $reference = substr($reference, 0, $fragmentPosition);
        }
        $queryPosition = strpos($reference, '?');
        if ($queryPosition !== false) {
            $query = substr($reference, $queryPosition);
            $reference = substr($reference, 0, $queryPosition);
        }

        if ($reference === '') {
            $path = (string)($base['path'] ?? '/');
        } elseif (str_starts_with($reference, '/')) {
            $path = $reference;
        } else {
            $basePath = (string)($base['path'] ?? '/');
            $directory = str_ends_with($basePath, '/') ? $basePath : dirname($basePath) . '/';
            $path = $directory . $reference;
        }

        return $authority . self::removeDotSegments($path) . $query;
    }

    private static function removeDotSegments(string $path): string
    {
        $segments = explode('/', $path);
        $normalized = [];
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($normalized);
                continue;
            }
            $normalized[] = $segment;
        }

        return '/' . implode('/', $normalized);
    }

    private function headerValue(array $headers, string $name): ?string
    {
        foreach ($headers as $key => $value) {
            if (is_string($key) && strtolower($key) === strtolower($name)) {
                return is_array($value) ? (string)end($value) : (string)$value;
            }
            if (is_int($key) && is_string($value)) {
                [$headerName, $headerValue] = array_pad(explode(':', $value, 2), 2, '');
                if (strtolower(trim($headerName)) === strtolower($name)) {
                    return trim($headerValue);
                }
            }
        }

        return null;
    }

    private function resolveDns(string $host, float $timeout): array
    {
        if (!function_exists('proc_open')) {
            throw new RuntimeException('官方来源 DNS 安全解析器不可用');
        }

        $script = <<<'PHP'
$host = (string)($argv[1] ?? '');
$records = @dns_get_record($host, DNS_A | DNS_AAAA);
$addresses = [];
if (is_array($records)) {
    foreach ($records as $record) {
        if (isset($record['ip'])) {
            $addresses[] = (string)$record['ip'];
        }
        if (isset($record['ipv6'])) {
            $addresses[] = (string)$record['ipv6'];
        }
    }
}
echo json_encode(array_values(array_unique($addresses)), JSON_UNESCAPED_SLASHES);
PHP;
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = @proc_open([PHP_BINARY, '-r', $script, $host], $descriptors, $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('官方来源 DNS 安全解析器启动失败');
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $deadline = microtime(true) + max(0.001, $timeout);
        $output = '';
        $timedOut = false;

        while (true) {
            $output .= (string)stream_get_contents($pipes[1]);
            stream_get_contents($pipes[2]);
            $status = proc_get_status($process);
            if (!$status['running']) {
                break;
            }

            $remaining = $deadline - microtime(true);
            if ($remaining <= 0) {
                $timedOut = true;
                proc_terminate($process);
                break;
            }

            $read = [$pipes[1], $pipes[2]];
            $write = null;
            $except = null;
            $waitMicros = max(1, min(100_000, (int)floor($remaining * 1_000_000)));
            @stream_select($read, $write, $except, 0, $waitMicros);
        }

        $output .= (string)stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        if ($timedOut) {
            throw new RuntimeException('官方来源 DNS 解析超过总超时预算');
        }

        $addresses = json_decode($output, true);
        if (!is_array($addresses)) {
            return [];
        }

        return array_values(array_map('strval', $addresses));
    }

    private function curlTransport(string $url, array $options): array
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuntimeException('官方来源请求初始化失败');
        }

        $body = '';
        $tooLarge = false;
        $responseHeaders = [];
        $host = (string)parse_url($url, PHP_URL_HOST);
        $resolve = [];
        foreach ($options['resolved_ips'] as $address) {
            $resolveAddress = str_contains($address, ':') ? '[' . $address . ']' : $address;
            $resolve[] = $host . ':443:' . $resolveAddress;
        }

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT_MS => min(
                (int)$options['connect_timeout'] * 1000,
                max(1, (int)floor((float)$options['timeout'] * 1000))
            ),
            CURLOPT_TIMEOUT_MS => max(1, (int)floor((float)$options['timeout'] * 1000)),
            CURLOPT_NOSIGNAL => true,
            CURLOPT_USERAGENT => (string)$options['user_agent'],
            CURLOPT_HTTPHEADER => (array)$options['headers'],
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_RESOLVE => $resolve,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
                $length = strlen($line);
                if (str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $name = strtolower(trim($name));
                    if ($name !== '') {
                        $responseHeaders[$name][] = trim($value);
                    }
                }
                return $length;
            },
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body, &$tooLarge, $options): int {
                if (strlen($body) + strlen($chunk) > (int)$options['max_body_bytes']) {
                    $tooLarge = true;
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);

        $executed = curl_exec($handle);
        $errorCode = curl_errno($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        if ($tooLarge) {
            throw new RuntimeException('官方来源响应体超过 5 MiB 上限');
        }
        if ($executed === false || $errorCode !== CURLE_OK) {
            throw new RuntimeException('官方来源请求失败（cURL 错误码 ' . $errorCode . '）');
        }

        return ['status' => $status, 'headers' => $responseHeaders, 'body' => $body];
    }
}
