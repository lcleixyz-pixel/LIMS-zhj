<?php
declare(strict_types=1);

namespace app\service;

/**
 * 阻止非授权路径将文档状态直写为 approved / published（effective 别名）等终态。
 */
class DocumentStatusGuardService
{
    public const PROTECTED_STATUSES = ['approved', 'published', 'effective'];

    /** @var list<string> 允许写入受保护状态的控制器动作（小写 controller/action） */
    private const AUTHORIZED_ROUTES = [
        'approval/approve',
        'document/submitreview',
        'document/obsolete',
        'docusealwebhook/handle',
    ];

    /**
     * @param array<string, mixed> $payload 拟写入字段
     * @return array{allowed: bool, result: string, reason: string, status: string}
     */
    public function guardWrite(
        array $payload,
        string $controller = '',
        string $action = '',
        bool $authorizedOverride = false
    ): array {
        $status = strtolower(trim((string)($payload['status'] ?? '')));
        if ($status === '' || !in_array($status, self::PROTECTED_STATUSES, true)) {
            return [
                'allowed' => true,
                'result' => 'ok',
                'reason' => '',
                'status' => $status,
            ];
        }

        if ($authorizedOverride) {
            return [
                'allowed' => true,
                'result' => 'ok',
                'reason' => 'authorized_override',
                'status' => $status,
            ];
        }

        $route = strtolower(trim($controller)) . '/' . strtolower(trim($action));
        if (in_array($route, self::AUTHORIZED_ROUTES, true)) {
            return [
                'allowed' => true,
                'result' => 'ok',
                'reason' => 'authorized_route',
                'status' => $status,
            ];
        }

        return [
            'allowed' => false,
            'result' => 'blocked',
            'reason' => 'non_authorized_status_write:' . $status,
            'status' => $status,
        ];
    }

    /**
     * 从 payload 中剔除受保护 status，供调用方降级保存。
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function stripProtectedStatus(array $payload): array
    {
        if (isset($payload['status']) && in_array(strtolower((string)$payload['status']), self::PROTECTED_STATUSES, true)) {
            unset($payload['status']);
        }

        return $payload;
    }
}
