<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\service\AiChatService;
use app\service\AiSettingsService;
use app\service\PageContextBuilder;
use think\exception\HttpException;
use think\facade\Config;
use think\facade\Session;

class AiChat extends BaseController
{
    public function sessions()
    {
        $companyId = (string)Config::get('qms.company_id');
        $userId = (string)Session::get('user.id');

        return json([
            'code' => 0,
            'data' => AiChatService::listSessions($companyId, $userId),
        ]);
    }

    public function create()
    {
        $companyId = (string)Config::get('qms.company_id');
        $userId = (string)Session::get('user.id');
        $contextMode = (string)$this->request->post('context_mode', 'context');
        $pageMeta = $this->request->post('page_meta/a', []);

        try {
            if (!AiSettingsService::isConfigured($companyId)) {
                throw new \RuntimeException('DeepSeek API 未配置');
            }

            $pageContext = PageContextBuilder::fromRequestPayload($companyId, $pageMeta, $contextMode);
            $session = AiChatService::createSession($companyId, $userId, $pageContext, $contextMode);

            return qms_json(['code' => 0, 'msg' => '会话已创建', 'data' => $session]);
        } catch (\Throwable $e) {
            return qms_json(['code' => 1, 'msg' => $e->getMessage()]);
        }
    }

    public function messages()
    {
        $companyId = (string)Config::get('qms.company_id');
        $userId = (string)Session::get('user.id');
        $sessionId = (string)$this->request->get('session_id', '');

        try {
            return json([
                'code' => 0,
                'data' => AiChatService::getMessages($companyId, $sessionId, $userId),
            ]);
        } catch (HttpException $e) {
            return json(['code' => 404, 'msg' => $e->getMessage()]);
        } catch (\Throwable $e) {
            return json(['code' => 1, 'msg' => $e->getMessage()]);
        }
    }

    public function send()
    {
        set_time_limit(300);

        $companyId = (string)Config::get('qms.company_id');
        $userId = (string)Session::get('user.id');
        $pageMeta = $this->request->post('page_meta/a', []);
        $contextMode = (string)$this->request->post('context_mode', 'context');

        try {
            if (!AiSettingsService::isConfigured($companyId)) {
                throw new \RuntimeException('DeepSeek API 未配置');
            }

            $pageContext = PageContextBuilder::fromRequestPayload($companyId, $pageMeta, $contextMode);
            $sessionId = trim((string)$this->request->post('session_id', ''));
            if ($sessionId === '') {
                $session = AiChatService::createSession($companyId, $userId, $pageContext, $contextMode);
                $sessionId = (string)$session['id'];
            }

            $result = AiChatService::sendMessage(
                $companyId,
                $sessionId,
                $userId,
                (string)$this->request->post('content'),
                $pageContext,
                $contextMode
            );
            $result['session_id'] = $sessionId;

            return qms_json(['code' => 0, 'msg' => 'ok', 'data' => $result]);
        } catch (HttpException $e) {
            return qms_json(['code' => 404, 'msg' => $e->getMessage()]);
        } catch (\Throwable $e) {
            return qms_json(['code' => 1, 'msg' => $e->getMessage()]);
        }
    }

    public function purge()
    {
        $companyId = (string)Config::get('qms.company_id');
        $userId = (string)Session::get('user.id');
        $scope = (string)$this->request->post('scope', 'mine');
        $role = (string)Session::get('user.role', 'staff');

        try {
            if ($scope === 'all') {
                if ($role !== 'admin') {
                    throw new \RuntimeException('仅管理员可清空全部会话');
                }
                $deleted = AiChatService::clearAllSessions($companyId, $userId);
            } else {
                $deleted = AiChatService::clearUserSessions($companyId, $userId);
            }

            return qms_json(['code' => 0, 'msg' => '已删除 ' . $deleted . ' 个会话', 'data' => ['deleted' => $deleted]]);
        } catch (\Throwable $e) {
            return qms_json(['code' => 1, 'msg' => $e->getMessage()]);
        }
    }
}
