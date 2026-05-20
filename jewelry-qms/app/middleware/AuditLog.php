<?php
declare(strict_types=1);

namespace app\middleware;

use app\model\History;
use think\facade\Session;

class AuditLog
{
    public function handle($request, \Closure $next)
    {
        $response = $next($request);

        if (Session::has('user.id') && $request->isPost()) {
            $controller = $request->controller();
            $action = $request->action();
            $method = $request->method();
            $logActions = ['delete', 'restore', 'add', 'edit', 'submitReview', 'revise', 'approve', 'changePassword'];
            if (in_array(strtolower($action), $logActions, true)) {
                try {
                    History::create([
                        'id' => qms_uuid(),
                        'model_name' => $controller,
                        'controller_name' => $controller,
                        'action' => $action,
                        'record_id' => (string) $request->param('id', ''),
                        'user_id' => Session::get('user.id'),
                        'details' => $method . ' ' . $controller . '/' . $action,
                        'created' => date('Y-m-d H:i:s'),
                    ]);
                } catch (\Throwable $e) {
                }
            }
        }

        return $response;
    }
}
