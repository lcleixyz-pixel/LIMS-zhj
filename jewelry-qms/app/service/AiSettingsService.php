<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Config;
use think\facade\Db;

class AiSettingsService
{
    private const SECRET_KEYS = ['ai.deepseek.api_key'];

    public static function ensureSchema(): void
    {
        $migration = dirname(__DIR__, 2) . '/database/migrations/20260601_ai_chat_assistant.sql';
        if (!is_file($migration)) {
            return;
        }

        $sql = (string)file_get_contents($migration);
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            if ($statement !== '') {
                Db::execute($statement);
            }
        }
    }

    public static function get(string $companyId, string $key, ?string $default = null): ?string
    {
        self::ensureSchema();
        $row = Db::name('system_settings')
            ->where('company_id', $companyId)
            ->where('setting_key', $key)
            ->find();
        if (!$row) {
            return $default;
        }
        if ((string)$row['value_type'] === 'secret') {
            return null;
        }

        $value = trim((string)($row['setting_value'] ?? ''));
        if ($value === '') {
            return $default;
        }

        return $value;
    }

    public static function availableModels(): array
    {
        return [
            'deepseek-v4-flash' => 'DeepSeek V4 Flash（推荐，替代 deepseek-chat）',
            'deepseek-v4-pro' => 'DeepSeek V4 Pro（更强推理）',
            'deepseek-chat' => 'deepseek-chat（兼容，2026/07/24 弃用）',
            'deepseek-reasoner' => 'deepseek-reasoner（兼容，2026/07/24 弃用）',
        ];
    }

    public static function normalizeModel(string $model): string
    {
        $model = trim($model);

        return $model !== '' ? $model : 'deepseek-v4-flash';
    }

    public static function normalizeBaseUrl(string $baseUrl): string
    {
        $baseUrl = trim($baseUrl);

        return $baseUrl !== '' ? rtrim($baseUrl, '/') : 'https://api.deepseek.com';
    }

    public static function validateModel(string $model): void
    {
        $model = self::normalizeModel($model);
        if (!array_key_exists($model, self::availableModels())) {
            throw new \InvalidArgumentException(
                '不支持的模型：' . $model . '。可选：' . implode('、', array_keys(self::availableModels()))
            );
        }
    }

    public static function set(string $companyId, string $key, string $value, string $type = 'string', ?string $userId = null): void
    {
        self::ensureSchema();
        if ($type === 'secret') {
            throw new \InvalidArgumentException('请使用 setSecret 保存密钥');
        }

        self::upsert($companyId, $key, $value, $type, $userId);
    }

    public static function setSecret(string $companyId, string $key, string $plainValue, ?string $userId = null): void
    {
        self::ensureSchema();
        $plainValue = trim($plainValue);
        if ($plainValue === '') {
            return;
        }
        if (!SettingsCipher::canEncrypt()) {
            throw new \RuntimeException('请配置 APP_KEY 或使用环境变量 DEEPSEEK_API_KEY');
        }

        self::upsert($companyId, $key, SettingsCipher::encrypt($plainValue), 'secret', $userId);
    }

    public static function getMaskedApiKey(string $companyId): string
    {
        $plain = self::resolvePlainApiKey($companyId);
        if ($plain === '') {
            return '';
        }

        return self::maskSecret($plain);
    }

    public static function getConfigSource(string $companyId): string
    {
        if (trim((string)env('DEEPSEEK_API_KEY', '')) !== '') {
            return 'env';
        }
        if (self::getSecret($companyId, 'ai.deepseek.api_key') !== null) {
            return 'database';
        }

        return 'none';
    }

    public static function isConfigured(string $companyId): bool
    {
        return self::resolvePlainApiKey($companyId) !== '';
    }

    public static function resolveAiConfig(string $companyId): array
    {
        $defaults = Config::get('qms.ai', []);
        $plainKey = self::resolvePlainApiKey($companyId);
        $source = self::getConfigSource($companyId);
        if ($source === 'none' && $plainKey !== '') {
            $source = trim((string)env('DEEPSEEK_API_KEY', '')) !== '' ? 'env' : 'database';
        }

        return [
            'source' => $source,
            'api_key' => $plainKey,
            'model' => self::normalizeModel((string)self::get(
                $companyId,
                'ai.deepseek.model',
                (string)($defaults['model'] ?? 'deepseek-v4-flash')
            )),
            'base_url' => self::normalizeBaseUrl((string)self::get(
                $companyId,
                'ai.deepseek.base_url',
                (string)($defaults['base_url'] ?? 'https://api.deepseek.com')
            )),
            'max_tokens' => (int)($defaults['max_tokens'] ?? 4096),
            'temperature' => (float)($defaults['temperature'] ?? 0.1),
        ];
    }

    public static function testConnection(
        string $companyId,
        ?string $overrideApiKey = null,
        ?string $overrideModel = null,
        ?string $overrideBaseUrl = null
    ): array {
        return DeepSeekService::testPing($companyId, $overrideApiKey, $overrideModel, $overrideBaseUrl);
    }

    public static function diagnoseConfiguration(string $companyId): array
    {
        self::ensureSchema();
        $source = self::getConfigSource($companyId);
        if (trim((string)env('DEEPSEEK_API_KEY', '')) !== '') {
            return ['ok' => true, 'source' => 'env', 'message' => ''];
        }

        $row = Db::name('system_settings')
            ->where('company_id', $companyId)
            ->where('setting_key', 'ai.deepseek.api_key')
            ->find();
        if (!$row) {
            return [
                'ok' => false,
                'source' => 'none',
                'message' => '未配置 API Key。请填写 Key 后先点「保存」，或直接点「测试连接」验证表单中的 Key。',
            ];
        }

        if ((string)($row['value_type'] ?? '') === 'secret') {
            $plain = SettingsCipher::decrypt((string)($row['setting_value'] ?? ''));
            if ($plain === null || $plain === '') {
                if (!SettingsCipher::canEncrypt()) {
                    return [
                        'ok' => false,
                        'source' => 'database',
                        'message' => '数据库中已有 Key，但缺少 APP_KEY 无法解密。请在 .env 中设置 APP_KEY 后重新保存 Key，或使用环境变量 DEEPSEEK_API_KEY。',
                    ];
                }

                return [
                    'ok' => false,
                    'source' => 'database',
                    'message' => '已保存的 Key 解密失败（APP_KEY 可能已变更）。请重新输入 API Key 并保存。',
                ];
            }
        }

        if (self::resolvePlainApiKey($companyId) === '') {
            return [
                'ok' => false,
                'source' => $source,
                'message' => 'DeepSeek API 未配置',
            ];
        }

        return ['ok' => true, 'source' => $source, 'message' => ''];
    }

    public static function maskPlainSecret(string $plain): string
    {
        return self::maskSecret($plain);
    }

    public static function getRetentionDays(string $companyId): int
    {
        $value = (int)(self::get($companyId, 'ai.chat.retention_days', '90') ?? 90);

        return max(1, $value);
    }

    private static function getSecret(string $companyId, string $key): ?string
    {
        if (!in_array($key, self::SECRET_KEYS, true)) {
            return null;
        }

        $row = Db::name('system_settings')
            ->where('company_id', $companyId)
            ->where('setting_key', $key)
            ->where('value_type', 'secret')
            ->find();
        if (!$row) {
            return null;
        }

        return SettingsCipher::decrypt((string)($row['setting_value'] ?? ''));
    }

    private static function resolvePlainApiKey(string $companyId): string
    {
        $envKey = trim((string)env('DEEPSEEK_API_KEY', ''));
        if ($envKey !== '') {
            return $envKey;
        }

        return (string)(self::getSecret($companyId, 'ai.deepseek.api_key') ?? '');
    }

    private static function upsert(string $companyId, string $key, string $value, string $type, ?string $userId): void
    {
        $row = Db::name('system_settings')
            ->where('company_id', $companyId)
            ->where('setting_key', $key)
            ->find();
        $now = date('Y-m-d H:i:s');
        $payload = [
            'company_id' => $companyId,
            'setting_key' => $key,
            'setting_value' => $value,
            'value_type' => $type,
            'modified_by' => $userId,
            'modified' => $now,
        ];
        if ($row) {
            Db::name('system_settings')->where('id', (string)$row['id'])->update($payload);
            return;
        }

        $payload['id'] = qms_uuid();
        $payload['created'] = $now;
        Db::name('system_settings')->insert($payload);
    }

    private static function maskSecret(string $plain): string
    {
        if (strlen($plain) <= 8) {
            return '****';
        }

        return substr($plain, 0, 3) . '****' . substr($plain, -4);
    }
}
