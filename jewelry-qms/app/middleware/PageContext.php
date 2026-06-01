<?php
declare(strict_types=1);

namespace app\middleware;

use app\service\AiSettingsService;
use app\service\PageContextBuilder;
use think\facade\Config;
use think\facade\Session;
use think\facade\View;

class PageContext
{
    protected array $except = [
        'login/index',
        'login/logout',
        'login/changepassword',
    ];

    public function handle($request, \Closure $next)
    {
        $controller = strtolower($request->controller());
        $action = strtolower($request->action());
        $route = $controller . '/' . $action;

        if (!in_array($route, $this->except, true) && Session::has('user.id')) {
            $companyId = (string)Config::get('qms.company_id');
            $recordId = trim((string)$request->param('id', ''));
            $module = qms_controller_url($request->controller());
            $pageRoute = $module . '/' . $action;

            $pageContext = PageContextBuilder::fromPageMeta(
                $companyId,
                $controller,
                $action,
                $recordId !== '' ? $recordId : null,
                'context',
                '',
                $pageRoute
            );

            View::assign([
                'qmsPageContext' => $pageContext['page'],
                'qmsCopilotEnabled' => qms_can('ai_chat'),
                'qmsAiConfigured' => AiSettingsService::isConfigured($companyId),
            ]);
        }

        return $next($request);
    }
}
