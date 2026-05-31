<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\service\AiSettingsService;
use app\service\SettingsCipher;
use think\facade\Config;
use think\facade\Session;
use think\facade\View;

class AiSettings extends BaseController
{
    public function index()
    {
        AiSettingsService::ensureSchema();
        $companyId = (string)Config::get('qms.company_id');
        $source = AiSettingsService::getConfigSource($companyId);
        $envOverride = $source === 'env';

        View::assign('pageTitle', 'AI 服务配置');
        View::assign('source', $source);
        View::assign('envOverride', $envOverride);
        View::assign('maskedKey', AiSettingsService::getMaskedApiKey($companyId));
        View::assign('configured', AiSettingsService::isConfigured($companyId));
        View::assign('model', AiSettingsService::normalizeModel((string)AiSettingsService::get(
            $companyId,
            'ai.deepseek.model',
            (string)Config::get('qms.ai.model', 'deepseek-v4-flash')
        )));
        View::assign('baseUrl', AiSettingsService::normalizeBaseUrl((string)AiSettingsService::get(
            $companyId,
            'ai.deepseek.base_url',
            (string)Config::get('qms.ai.base_url', 'https://api.deepseek.com')
        )));
        View::assign('availableModels', AiSettingsService::availableModels());
        View::assign('retentionDays', AiSettingsService::getRetentionDays($companyId));
        View::assign('configDiagnosis', AiSettingsService::diagnoseConfiguration($companyId));
        View::assign('canEncryptSecrets', SettingsCipher::canEncrypt());

        return View::fetch('ai_settings/index');
    }

    public function save()
    {
        $companyId = (string)Config::get('qms.company_id');
        $userId = Session::get('user.id') ? (string)Session::get('user.id') : null;
        $apiKey = trim((string)$this->request->post('api_key', ''));
        $model = AiSettingsService::normalizeModel((string)$this->request->post('model', 'deepseek-v4-flash'));
        $baseUrl = AiSettingsService::normalizeBaseUrl((string)$this->request->post('base_url', 'https://api.deepseek.com'));
        $retentionDays = max(1, (int)$this->request->post('retention_days', 90));

        try {
            AiSettingsService::validateModel($model);
            if ($apiKey !== '') {
                AiSettingsService::setSecret($companyId, 'ai.deepseek.api_key', $apiKey, $userId);
            }
            AiSettingsService::set($companyId, 'ai.deepseek.model', $model, 'string', $userId);
            AiSettingsService::set($companyId, 'ai.deepseek.base_url', $baseUrl, 'string', $userId);
            AiSettingsService::set($companyId, 'ai.chat.retention_days', (string)$retentionDays, 'string', $userId);

            $message = 'AI 配置已保存';
            if (AiSettingsService::getConfigSource($companyId) === 'env') {
                $message .= '（当前生效：环境变量 DEEPSEEK_API_KEY）';
            }

            if ($this->request->isAjax()) {
                return qms_json(['code' => 0, 'msg' => $message, 'data' => ['source' => AiSettingsService::getConfigSource($companyId)]]);
            }

            Session::flash('success', $message);
        } catch (\Throwable $e) {
            if ($this->request->isAjax()) {
                return qms_json(['code' => 1, 'msg' => $e->getMessage()]);
            }
            Session::flash('error', $e->getMessage());
        }

        return redirect('/ai_settings/index');
    }

    public function test()
    {
        $companyId = (string)Config::get('qms.company_id');
        $overrideApiKey = trim((string)$this->request->post('api_key', ''));
        $overrideModel = trim((string)$this->request->post('model', ''));
        $overrideBaseUrl = trim((string)$this->request->post('base_url', ''));

        try {
            $result = AiSettingsService::testConnection(
                $companyId,
                $overrideApiKey !== '' ? $overrideApiKey : null,
                $overrideModel !== '' ? $overrideModel : null,
                $overrideBaseUrl !== '' ? $overrideBaseUrl : null
            );

            return qms_json([
                'code' => $result['ok'] ? 0 : 1,
                'msg' => (string)$result['message'],
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            return qms_json(['code' => 1, 'msg' => $e->getMessage()]);
        }
    }
}
