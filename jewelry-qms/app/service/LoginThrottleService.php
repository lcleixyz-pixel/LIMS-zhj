<?php
declare(strict_types=1);

namespace app\service;

/**
 * 登录失败限流：同 IP 连续失败 5 次锁定 15 分钟。
 * 默认使用 runtime/cache 文件存储，便于单测注入自定义 store。
 */
class LoginThrottleService
{
    public const MAX_ATTEMPTS = 5;
    public const LOCK_SECONDS = 900;

    private string $storageDir;

    public function __construct(?string $storageDir = null)
    {
        $this->storageDir = $storageDir ?? (runtime_path() . 'login_throttle');
        if (!is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0775, true);
        }
    }

    public function isLocked(string $ip): bool
    {
        $state = $this->read($ip);
        if (($state['locked_until'] ?? 0) > time()) {
            return true;
        }

        return false;
    }

    public function remainingLockSeconds(string $ip): int
    {
        $state = $this->read($ip);
        $until = (int)($state['locked_until'] ?? 0);
        $remain = $until - time();

        return $remain > 0 ? $remain : 0;
    }

    public function recordFailure(string $ip): array
    {
        $state = $this->read($ip);
        if (($state['locked_until'] ?? 0) > time()) {
            return $state;
        }

        $failures = (int)($state['failures'] ?? 0) + 1;
        $state = [
            'failures' => $failures,
            'locked_until' => $failures >= self::MAX_ATTEMPTS ? time() + self::LOCK_SECONDS : 0,
        ];
        $this->write($ip, $state);

        return $state;
    }

    public function clear(string $ip): void
    {
        $path = $this->pathFor($ip);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function read(string $ip): array
    {
        $path = $this->pathFor($ip);
        if (!is_file($path)) {
            return ['failures' => 0, 'locked_until' => 0];
        }
        $raw = (string)@file_get_contents($path);
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return ['failures' => 0, 'locked_until' => 0];
        }

        return [
            'failures' => (int)($data['failures'] ?? 0),
            'locked_until' => (int)($data['locked_until'] ?? 0),
        ];
    }

    private function write(string $ip, array $state): void
    {
        @file_put_contents($this->pathFor($ip), json_encode($state), LOCK_EX);
    }

    private function pathFor(string $ip): string
    {
        return $this->storageDir . '/' . hash('sha256', $ip) . '.json';
    }
}
