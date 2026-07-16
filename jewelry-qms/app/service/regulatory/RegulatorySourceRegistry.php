<?php
declare(strict_types=1);

namespace app\service\regulatory;

use RuntimeException;

final class RegulatorySourceRegistry
{
    private array $config;

    public function __construct()
    {
        $path = dirname(__DIR__, 3) . '/config/regulatory_sources.php';
        $config = require $path;
        if (!is_array($config) || !is_array($config['sources'] ?? null)) {
            throw new RuntimeException('法规来源配置无效');
        }
        $this->config = $config;
    }

    public function all(): array
    {
        $sources = [];
        foreach ($this->config['sources'] as $key => $source) {
            $sources[(string)$key] = ['key' => (string)$key] + $source;
        }

        return $sources;
    }

    public function source(string $key): array
    {
        $sources = $this->all();
        if (!isset($sources[$key])) {
            throw new RuntimeException('法规来源未批准或不存在：' . $key);
        }

        return $sources[$key];
    }

    public function adapterFor(string $sourceKey): RegulatorySourceAdapterInterface
    {
        $mode = (string)$this->source($sourceKey)['mode'];
        return match ($mode) {
            'html_list' => new HtmlListSourceAdapter(),
            'manual_only' => new ManualOnlySourceAdapter(),
            default => throw new RuntimeException('法规来源模式不受支持：' . $mode),
        };
    }

    public function allowedHosts(string $sourceKey): array
    {
        $source = $this->source($sourceKey);
        $hosts = array_map(
            static fn (mixed $host): string => strtolower((string)$host),
            (array)($source['allowed_hosts'] ?? [])
        );

        return array_values(array_unique($hosts));
    }

    public function httpClientFor(
        string $sourceKey,
        ?callable $dnsResolver = null,
        ?callable $transport = null,
        ?callable $clock = null
    ): RegulatoryHttpClient {
        $source = $this->source($sourceKey);
        if ((string)$source['mode'] === 'manual_only') {
            throw new RuntimeException('人工核验来源不得创建 HTTP 客户端');
        }

        return new RegulatoryHttpClient(
            $this->allowedHosts($sourceKey),
            $dnsResolver,
            $transport,
            $clock
        );
    }
}
