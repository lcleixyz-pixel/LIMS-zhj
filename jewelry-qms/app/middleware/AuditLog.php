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
            $logActions = [
                'add',
                'approve',
                'changepassword',
                'create',
                'delete',
                'edit',
                'exportpdf',
                'restore',
                'revise',
                'seedbatch',
                'seedsamples',
                'submitreview',
                'updatereview',
                'save',
                'test',
                'purge',
                'send',
            ];
            if (in_array(strtolower($action), $logActions, true)) {
                try {
                    History::create([
                        'id' => qms_uuid(),
                        'model_name' => $controller,
                        'controller_name' => $controller,
                        'action' => $action,
                        'record_id' => $this->resolveRecordId($request, $response),
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

    private function resolveRecordId($request, $response): string
    {
        $recordId = trim((string)$request->param('id', ''));
        if ($recordId !== '') {
            return $recordId;
        }

        if (!method_exists($response, 'getHeader')) {
            return '';
        }

        $location = (string)($response->getHeader('Location') ?? '');
        if ($location === '') {
            return '';
        }

        $query = (string)(parse_url($location, PHP_URL_QUERY) ?? '');
        if ($query === '') {
            return '';
        }

        parse_str($query, $params);

        return trim((string)($params['id'] ?? ''));
    }
}
