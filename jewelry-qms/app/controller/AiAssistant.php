<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\service\AiAssistantService;
use think\facade\Config;
use think\facade\Session;
use think\facade\View;

class AiAssistant extends BaseController
{
    public function index()
    {
        AiAssistantService::ensureSchema();
        $companyId = (string)Config::get('qms.company_id');
        $relativeDir = (string)$this->request->get('dir', '');

        View::assign('pageTitle', 'AI 文档助理');
        View::assign('targetTypes', AiAssistantService::targetTypes());
        View::assign('sourceItems', AiAssistantService::listSourceFiles($relativeDir));
        View::assign('currentDir', $relativeDir);
        View::assign('parentDir', $this->parentDir($relativeDir));
        View::assign('configured', AiAssistantService::isConfigured());
        View::assign('recentLogs', AiAssistantService::history($companyId, 10));
        View::assign('workspaceRoot', AiAssistantService::workspaceSourceRoot());

        return View::fetch('ai_assistant/index');
    }

    public function extract()
    {
        $companyId = (string)Config::get('qms.company_id');
        $userId = Session::get('user.id');
        $targetType = (string)$this->request->post('target_type', '');
        $sourceMode = (string)$this->request->post('source_mode', 'workspace');
        $relativePath = (string)$this->request->post('relative_path', '');

        try {
            if ($sourceMode === 'upload') {
                $file = $this->request->file('file');
                if (!$file) {
                    throw new \InvalidArgumentException('请选择上传文件');
                }
                $saved = $file->move(runtime_path() . 'ai_uploads', qms_uuid() . '.' . strtolower($file->extension()));
                if (!$saved) {
                    throw new \RuntimeException('文件上传失败');
                }
                $filePath = $saved->getPathname();
            } else {
                if ($relativePath === '') {
                    throw new \InvalidArgumentException('请选择现用文件目录中的文档');
                }
                $filePath = AiAssistantService::resolveSourcePath($relativePath);
            }

            $result = AiAssistantService::extractRecords(
                $filePath,
                $targetType,
                $companyId,
                $userId ? (string)$userId : null
            );

            if ($this->request->isAjax()) {
                return json(['code' => 0, 'msg' => '提取完成', 'data' => $result]);
            }

            Session::flash('success', 'AI 提取完成，请确认后入库');
            Session::flash('ai_preview', $result);

            return redirect('/ai_assistant/index?dir=' . urlencode(dirname(str_replace('\\', '/', $relativePath)) === '.' ? '' : dirname(str_replace('\\', '/', $relativePath))));
        } catch (\Throwable $e) {
            if ($this->request->isAjax()) {
                return json(['code' => 1, 'msg' => $e->getMessage()]);
            }

            Session::flash('error', $e->getMessage());

            return redirect('/ai_assistant/index');
        }
    }

    public function confirm()
    {
        $logId = (string)$this->request->post('log_id', '');
        $payloadJson = (string)$this->request->post('payload_json', '');
        $userId = Session::get('user.id');

        try {
            $payload = json_decode($payloadJson, true);
            if (!is_array($payload)) {
                throw new \InvalidArgumentException('提交数据无效');
            }

            $created = AiAssistantService::confirmAndInsert(
                $logId,
                $payload,
                $userId ? (string)$userId : ''
            );

            Session::flash('success', '已确认入库，共写入 ' . $created . ' 条记录');

            return redirect('/ai_assistant/history');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());

            return redirect('/ai_assistant/index');
        }
    }

    public function reject()
    {
        $logId = (string)$this->request->post('log_id', '');
        $userId = Session::get('user.id');

        try {
            AiAssistantService::rejectExtraction($logId, $userId ? (string)$userId : '');
            Session::flash('success', '已拒绝本次提取结果');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        return redirect('/ai_assistant/history');
    }

    public function history()
    {
        $companyId = (string)Config::get('qms.company_id');
        View::assign('pageTitle', 'AI 提取历史');
        View::assign('logs', AiAssistantService::history($companyId, 50));
        View::assign('targetTypes', AiAssistantService::targetTypes());

        return View::fetch('ai_assistant/history');
    }

    public function preview()
    {
        $logId = (string)$this->request->get('id', '');
        $log = AiAssistantService::getLog($logId);
        if (!$log) {
            Session::flash('error', '提取记录不存在');

            return redirect('/ai_assistant/history');
        }

        View::assign('pageTitle', 'AI 提取预览');
        View::assign('log', $log);
        View::assign('targetTypes', AiAssistantService::targetTypes());
        View::assign('payloadJson', json_encode($log['extracted_json'] ?? [], JSON_UNESCAPED_UNICODE));

        return View::fetch('ai_assistant/preview');
    }

    private function parentDir(string $relativeDir): string
    {
        $relativeDir = trim(str_replace('\\', '/', $relativeDir), '/');
        if ($relativeDir === '') {
            return '';
        }

        $parts = explode('/', $relativeDir);
        array_pop($parts);

        return implode('/', $parts);
    }
}
