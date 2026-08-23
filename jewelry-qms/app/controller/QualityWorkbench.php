<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\service\QualityWorkbenchService;
use RuntimeException;
use think\facade\Db;
use think\facade\Session;
use think\facade\View;

class QualityWorkbench extends BaseController
{
    public function index()
    {
        $service = new QualityWorkbenchService();
        $userId = (string)Session::get('user.id', '');
        View::assign('summary', $service->dashboardSummary($userId));
        View::assign('recentReads', (array)Session::get('recent_document_reads', []));
        View::assign('myReminders', Db::name('notification_users')->alias('nu')
            ->leftJoin('notifications notification', 'notification.id=nu.notification_id')
            ->where('nu.user_id', $userId)
            ->where('notification.soft_delete', 0)
            ->field('nu.id,nu.status,notification.title,notification.message,notification.created')
            ->order('nu.status', 'asc')
            ->order('notification.created', 'desc')
            ->limit(8)
            ->select()
            ->toArray());

        return View::fetch('quality_workbench/index');
    }

    public function projects()
    {
        $service = new QualityWorkbenchService();
        View::assign('data', $service->listProjects((string)$this->request->get('status', '')));

        return View::fetch('quality_workbench/projects');
    }

    public function view()
    {
        $service = new QualityWorkbenchService();
        try {
            View::assign('data', $service->projectDetail((string)$this->request->get('id', '')));
        } catch (RuntimeException $exception) {
            Session::flash('warning', $exception->getMessage());

            return redirect('/quality-workbench/projects');
        }

        return View::fetch('quality_workbench/view');
    }

    public function transitionTask()
    {
        $service = new QualityWorkbenchService();
        try {
            $result = $service->transitionTask(
                (string)$this->request->post('task_id', ''),
                (string)$this->request->post('action', ''),
                (string)$this->request->post('note', '')
            );
            Session::flash('success', '任务状态已更新。');

            return redirect('/quality-workbench/projects/view?id=' . urlencode((string)$result['project_id']));
        } catch (RuntimeException $exception) {
            Session::flash('warning', $exception->getMessage());

            return redirect((string)$this->request->post('redirect_to', '/quality-workbench'));
        }
    }

    public function acceptProject()
    {
        $service = new QualityWorkbenchService();
        $projectId = (string)$this->request->post('project_id', '');
        try {
            $service->acceptProject(
                $projectId,
                (string)$this->request->post('decision', ''),
                (string)$this->request->post('note', '')
            );
            Session::flash('success', '项目验收动作已记录。');
        } catch (RuntimeException $exception) {
            Session::flash('warning', $exception->getMessage());
        }

        return redirect('/quality-workbench/projects/view?id=' . urlencode($projectId));
    }

    public function refresh()
    {
        $service = new QualityWorkbenchService();
        try {
            $result = $service->refreshSystemProjects(true);
            Session::flash('success', '质量工作台已刷新：项目 ' . (string)($result['validation']['project_count'] ?? 0) . ' 个，任务 ' . (string)($result['validation']['task_count'] ?? 0) . ' 条。');
        } catch (\Throwable $exception) {
            Session::flash('warning', $exception->getMessage());
        }

        return redirect('/quality-workbench');
    }
}
