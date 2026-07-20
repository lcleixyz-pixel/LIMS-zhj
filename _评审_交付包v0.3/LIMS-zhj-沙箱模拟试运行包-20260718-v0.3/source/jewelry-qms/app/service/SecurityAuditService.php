<?php
declare(strict_types=1);

namespace app\service;

use app\model\History;
use think\facade\Log;
use think\facade\Session;

final class SecurityAuditService
{
    public static function recordAccessDenied(object $request, string $reason): void
    {
        if (!Session::has('user.id')) {
            return;
        }

        try {
            $controller = (string)$request->controller();
            $action = (string)$request->action();
            $recordId = trim((string)$request->param('id', ''));
            if ($recordId === '') {
                $recordId = trim((string)$request->post('id', ''));
            }
            History::create([
                'id' => qms_uuid(),
                'model_name' => $controller,
                'controller_name' => $controller,
                'action' => 'access_denied',
                'record_id' => strlen($recordId) <= 36 ? $recordId : substr(hash('sha256', $recordId), 0, 36),
                'user_id' => (string)Session::get('user.id'),
                'details' => implode(' ', [
                    'outcome=failed',
                    'reason=' . preg_replace('/[^a-z_]+/', '_', strtolower($reason)),
                    strtoupper((string)$request->method()),
                    $controller . '/' . $action,
                ]),
                'created' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $exception) {
            Log::error('Security audit capture failed: ' . $exception->getMessage());
        }
    }
}
