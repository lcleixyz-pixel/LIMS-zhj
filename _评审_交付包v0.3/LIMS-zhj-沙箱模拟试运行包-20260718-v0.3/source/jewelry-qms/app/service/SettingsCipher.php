<?php
declare(strict_types=1);

namespace app\service;

class SettingsCipher
{
    public static function canEncrypt(): bool
    {
        return self::deriveKeyMaterial() !== '';
    }

    public static function encrypt(string $plain): string
    {
        $key = self::deriveKey();
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new \RuntimeException('配置加密失败');
        }

        return 'v1:' . base64_encode($iv) . ':' . base64_encode($tag) . ':' . base64_encode($cipher);
    }

    public static function decrypt(string $stored): ?string
    {
        if (!str_starts_with($stored, 'v1:')) {
            return null;
        }

        $parts = explode(':', $stored, 4);
        if (count($parts) !== 4) {
            return null;
        }

        $iv = base64_decode($parts[1], true);
        $tag = base64_decode($parts[2], true);
        $cipher = base64_decode($parts[3], true);
        if ($iv === false || $tag === false || $cipher === false) {
            return null;
        }

        try {
            $key = self::deriveKey();
        } catch (\Throwable) {
            return null;
        }

        $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

        return $plain === false ? null : $plain;
    }

    private static function deriveKeyMaterial(): string
    {
        $material = (string)env('QMS_SETTINGS_CIPHER_KEY', '');
        if ($material === '') {
            $material = (string)env('APP_KEY', '');
        }

        return $material;
    }

    private static function deriveKey(): string
    {
        $material = self::deriveKeyMaterial();
        if ($material === '') {
            throw new \RuntimeException('请配置 APP_KEY 或使用环境变量 DEEPSEEK_API_KEY');
        }

        $key = hash('sha256', $material, true);
        if (strlen($key) !== 32) {
            throw new \RuntimeException('配置加密密钥无效');
        }

        return $key;
    }
}
