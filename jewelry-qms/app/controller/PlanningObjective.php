<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\Department;
use app\model\QmsPosition;
use app\model\QmsQualityObjective;
use app\model\QmsQualityPolicy;
use think\facade\Session;
use think\facade\View;

class PlanningObjective extends BaseController
{
    public function index()
    {
        $objectives = QmsQualityObjective::where('soft_delete', 0)
            ->order('year', 'desc')
            ->order('title', 'asc')
            ->paginate(30);

        View::assign('policies', QmsQualityPolicy::where('soft_delete', 0)->order('effective_date', 'desc')->select());
        View::assign('objectives', $objectives);
        View::assign('objectivePages', $objectives->render());
        View::assign('departments', Department::where('soft_delete', 0)->select());
        View::assign('positions', QmsPosition::where('soft_delete', 0)->order('code', 'asc')->select());

        return View::fetch('planning_objective/index');
    }

    public function createPolicy()
    {
        if (!$this->request->isPost()) {
            return redirect('/planning/objectives');
        }

        if ((int)$this->request->post('is_current', 0) === 1) {
            QmsQualityPolicy::where('soft_delete', 0)->update(['is_current' => 0]);
        }

        $policy = new QmsQualityPolicy();
        $policy->save([
            'title' => trim((string)$this->request->post('title', '质量方针')),
            'policy_text' => trim((string)$this->request->post('policy_text', '')),
            'version' => trim((string)$this->request->post('version', '')),
            'effective_date' => $this->request->post('effective_date') ?: null,
            'is_current' => (int)$this->request->post('is_current', 0),
            'management_review_input' => (int)$this->request->post('management_review_input', 1),
            'review_status' => $this->request->post('review_status', 'draft'),
        ]);

        Session::flash('success', '质量方针已登记。');

        return redirect('/planning/objectives');
    }

    public function createObjective()
    {
        if (!$this->request->isPost()) {
            return redirect('/planning/objectives');
        }

        $objective = new QmsQualityObjective();
        $objective->save([
            'year' => (int)$this->request->post('year', date('Y')),
            'department_id' => $this->request->post('department_id') ?: null,
            'position_id' => $this->request->post('position_id') ?: null,
            'title' => trim((string)$this->request->post('title', '')),
            'metric_name' => trim((string)$this->request->post('metric_name', '')),
            'target_value' => trim((string)$this->request->post('target_value', '')),
            'unit' => trim((string)$this->request->post('unit', '')),
            'statistic_cycle' => $this->request->post('statistic_cycle', 'annual'),
            'responsible_department' => trim((string)$this->request->post('responsible_department', '')),
            'responsible_position' => trim((string)$this->request->post('responsible_position', '')),
            'management_review_input' => (int)$this->request->post('management_review_input', 1),
            'review_status' => $this->request->post('review_status', 'draft'),
        ]);

        Session::flash('success', '质量目标已登记。');

        return redirect('/planning/objectives');
    }
}
