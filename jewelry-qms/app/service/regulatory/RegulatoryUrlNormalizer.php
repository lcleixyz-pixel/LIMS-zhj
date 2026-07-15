<?php
declare(strict_types=1);

namespace app\service\regulatory;

use RuntimeException;

final class RegulatoryUrlNormalizer
{
    public static function normalize(string $reference, array $allowedHosts, ?string $baseUrl = null): string
    {
        self::assertNoControls($reference);
        self::assertValidPercentEncoding($reference);

        $absoluteUrl = $baseUrl === null
            ? $reference
            : self::resolveReference($baseUrl, $reference);
        self::assertNoControls($absoluteUrl);

        $parts = parse_url($absoluteUrl);
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
        $normalizedAllowedHosts = array_values(array_unique(array_map(
            static fn (mixed $allowedHost): string => strtolower(trim((string)$allowedHost)),
            $allowedHosts
        )));
        if ($host === '' || !in_array($host, $normalizedAllowedHosts, true)) {
            throw new RuntimeException('官方来源主机不在白名单');
        }

        $path = self::normalizePath((string)($parts['path'] ?? '/'));
        $query = self::normalizeQuery((string)($parts['query'] ?? ''));

        return 'https://' . $host . $path . ($query === '' ? '' : '?' . $query);
    }

    private static function resolveReference(string $baseUrl, string $reference): string
    {
        self::assertNoControls($baseUrl);
        self::assertValidPercentEncoding($baseUrl);
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

        $authority = strtolower((string)$base['scheme']) . '://' . strtolower((string)$base['host']);
        if (isset($base['port'])) {
            $authority .= ':' . (int)$base['port'];
        }

        $withoutFragment = explode('#', $reference, 2)[0];
        [$referencePath, $referenceQuery] = array_pad(explode('?', $withoutFragment, 2), 2, null);
        if ($referenceQuery !== null) {
            $querySuffix = '?' . $referenceQuery;
        } elseif ($referencePath === '' && isset($base['query'])) {
            $querySuffix = '?' . (string)$base['query'];
        } else {
            $querySuffix = '';
        }
        if ($referencePath === '') {
            $path = (string)($base['path'] ?? '/');
        } elseif (str_starts_with($referencePath, '/')) {
            $path = $referencePath;
        } else {
            $basePath = (string)($base['path'] ?? '/');
            $directory = str_ends_with($basePath, '/') ? $basePath : dirname($basePath) . '/';
            $path = $directory . $referencePath;
        }

        return $authority . $path . $querySuffix;
    }

    private static function normalizePath(string $path): string
    {
        self::assertNoControls($path);
        self::assertValidPercentEncoding($path);
        $path = str_replace(' ', '%20', self::normalizePercentEncoding($path));
        $trailingSlash = str_ends_with($path, '/');
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }

        $normalized = '/' . implode('/', $segments);
        if ($trailingSlash && $normalized !== '/') {
            $normalized .= '/';
        }

        return $normalized;
    }

    private static function normalizeQuery(string $query): string
    {
        if ($query === '') {
            return '';
        }
        self::assertNoControls($query);
        self::assertValidPercentEncoding($query);

        $parameters = [];
        foreach (explode('&', $query) as $index => $parameter) {
            if ($parameter === '') {
                continue;
            }
            [$rawKey, $rawValue] = array_pad(explode('=', $parameter, 2), 2, '');
            $key = rawurldecode(str_replace('+', ' ', $rawKey));
            $value = rawurldecode(str_replace('+', ' ', $rawValue));
            self::assertNoControls($key);
            self::assertNoControls($value);

            $trackingKey = strtolower($key);
            if (str_starts_with($trackingKey, 'utm_') || in_array($trackingKey, ['spm', 'from'], true)) {
                continue;
            }

            $parameters[] = [
                'key' => rawurlencode($key),
                'value' => rawurlencode($value),
                'index' => $index,
            ];
        }

        usort($parameters, static function (array $left, array $right): int {
            return [$left['key'], $left['value'], $left['index']]
                <=> [$right['key'], $right['value'], $right['index']];
        });

        return implode('&', array_map(
            static fn (array $parameter): string => $parameter['key'] . '=' . $parameter['value'],
            $parameters
        ));
    }

    private static function assertNoControls(string $value): void
    {
        $decoded = $value;
        for ($iteration = 0; $iteration < 6; $iteration++) {
            if (preg_match('/[\x00-\x1f\x7f]/', $decoded) === 1) {
                throw new RuntimeException('官方来源 URL 不允许 ASCII 控制字符');
            }
            $next = rawurldecode($decoded);
            if ($next === $decoded) {
                return;
            }
            $decoded = $next;
        }

        throw new RuntimeException('官方来源 URL 控制字符解码层级异常');
    }

    private static function assertValidPercentEncoding(string $value): void
    {
        if (preg_match('/%(?![0-9a-fA-F]{2})/', $value) === 1) {
            throw new RuntimeException('官方来源 URL 百分号编码无效');
        }
    }

    private static function normalizePercentEncoding(string $value): string
    {
        return (string)preg_replace_callback(
            '/%([0-9a-fA-F]{2})/',
            static function (array $matches): string {
                $character = chr((int)hexdec($matches[1]));
                if (preg_match('/[A-Za-z0-9\-._~]/', $character) === 1) {
                    return $character;
                }

                return '%' . strtoupper($matches[1]);
            },
            $value
        );
    }
}
