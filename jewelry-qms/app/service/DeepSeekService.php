<?php
declare(strict_types=1);

namespace app\service;

class DeepSeekService
{
    public static function chat(array $messages, array $options = []): array
    {
        $companyId = (string)($options['company_id'] ?? '');
        if ($companyId === '') {
            throw new \InvalidArgumentException('缺少 company_id');
        }

        $timeout = max(30, (int)($options['timeout'] ?? 180));
        set_time_limit($timeout + 30);

        $config = AiSettingsService::resolveAiConfig($companyId);
        $apiKey = trim((string)($options['api_key'] ?? $config['api_key'] ?? ''));
        if ($apiKey === '') {
            throw new \RuntimeException('DeepSeek API 未配置');
        }

        $model = AiSettingsService::normalizeModel((string)($options['model'] ?? $config['model'] ?? 'deepseek-v4-flash'));
        $baseUrl = AiSettingsService::normalizeBaseUrl((string)($options['base_url'] ?? $config['base_url'] ?? 'https://api.deepseek.com'));

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => (float)($options['temperature'] ?? $config['temperature'] ?? 0.1),
            'max_tokens' => (int)($options['max_tokens'] ?? $config['max_tokens'] ?? 4096),
        ];
        if (!empty($options['response_format'])) {
            $payload['response_format'] = $options['response_format'];
        }

        $response = self::httpPost(
            $baseUrl . '/chat/completions',
            json_encode($payload, JSON_UNESCAPED_UNICODE),
            [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            $timeout
        );

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('DeepSeek 返回无效 JSON');
        }
        if (isset($decoded['error']['message'])) {
            throw new \RuntimeException('DeepSeek API 错误：' . (string)$decoded['error']['message']);
        }

        return [
            'content' => (string)($decoded['choices'][0]['message']['content'] ?? ''),
            'usage' => $decoded['usage'] ?? null,
            'source' => (string)($config['source'] ?? 'none'),
        ];
    }

    public static function testPing(
        string $companyId,
        ?string $overrideApiKey = null,
        ?string $overrideModel = null,
        ?string $overrideBaseUrl = null
    ): array {
        $overrideApiKey = trim((string)($overrideApiKey ?? ''));
        $overrideModel = trim((string)($overrideModel ?? ''));
        $overrideBaseUrl = trim((string)($overrideBaseUrl ?? ''));
        $config = AiSettingsService::resolveAiConfig($companyId);
        $effectiveModel = AiSettingsService::normalizeModel(
            $overrideModel !== '' ? $overrideModel : (string)$config['model']
        );
        $effectiveBaseUrl = AiSettingsService::normalizeBaseUrl(
            $overrideBaseUrl !== '' ? $overrideBaseUrl : (string)$config['base_url']
        );

        if ($overrideModel !== '') {
            try {
                AiSettingsService::validateModel($overrideModel);
            } catch (\InvalidArgumentException $e) {
                return [
                    'ok' => false,
                    'message' => $e->getMessage(),
                    'source' => 'form',
                    'model' => $effectiveModel,
                    'base_url' => $effectiveBaseUrl,
                    'effective_masked_key' => $overrideApiKey !== ''
                        ? AiSettingsService::maskPlainSecret($overrideApiKey)
                        : AiSettingsService::getMaskedApiKey($companyId),
                ];
            }
        }

        $diagnosis = AiSettingsService::diagnoseConfiguration($companyId);
        if ($overrideApiKey === '' && !($diagnosis['ok'] ?? false)) {
            return [
                'ok' => false,
                'message' => (string)($diagnosis['message'] ?? 'DeepSeek API 未配置'),
                'source' => (string)($diagnosis['source'] ?? AiSettingsService::getConfigSource($companyId)),
                'model' => $effectiveModel,
                'base_url' => $effectiveBaseUrl,
                'effective_masked_key' => AiSettingsService::getMaskedApiKey($companyId),
            ];
        }

        try {
            $chatOptions = [
                'company_id' => $companyId,
                'max_tokens' => 16,
                'temperature' => 0,
                'model' => $effectiveModel,
                'base_url' => $effectiveBaseUrl,
            ];
            if ($overrideApiKey !== '') {
                $chatOptions['api_key'] = $overrideApiKey;
            }

            $result = self::chat([
                ['role' => 'user', 'content' => '回复 OK'],
            ], $chatOptions);

            return [
                'ok' => str_contains($result['content'], 'OK') || $result['content'] !== '',
                'message' => '连接成功',
                'source' => $overrideApiKey !== '' ? 'form' : (string)($config['source'] ?? 'none'),
                'model' => $effectiveModel,
                'base_url' => $effectiveBaseUrl,
                'effective_masked_key' => $overrideApiKey !== ''
                    ? AiSettingsService::maskPlainSecret($overrideApiKey)
                    : AiSettingsService::getMaskedApiKey($companyId),
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => $e->getMessage(),
                'source' => $overrideApiKey !== '' ? 'form' : AiSettingsService::getConfigSource($companyId),
                'model' => $effectiveModel,
                'base_url' => $effectiveBaseUrl,
                'effective_masked_key' => $overrideApiKey !== ''
                    ? AiSettingsService::maskPlainSecret($overrideApiKey)
                    : AiSettingsService::getMaskedApiKey($companyId),
            ];
        }
    }

    private static function httpPost(string $url, string $payload, array $headers, int $timeout = 180): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            if ($errno === CURLE_OPERATION_TIMEDOUT) {
                throw new \RuntimeException('DeepSeek API 响应超时（' . $timeout . ' 秒），请缩短问题或稍后重试');
            }

            throw new \RuntimeException('网络连接失败：' . ($error !== '' ? $error : '无法访问 DeepSeek API'));
        }

        $body = (string)$response;
        if ($status >= 400) {
            $decoded = json_decode($body, true);
            $apiMessage = is_array($decoded) ? (string)($decoded['error']['message'] ?? '') : '';
            if ($status === 401) {
                throw new \RuntimeException($apiMessage !== '' ? 'API Key 无效：' . $apiMessage : 'API Key 无效（401）');
            }

            throw new \RuntimeException(
                $apiMessage !== '' ? 'DeepSeek API 错误（HTTP ' . $status . '）：' . $apiMessage : 'DeepSeek API 错误（HTTP ' . $status . '）'
            );
        }

        return $body;
    }
}
