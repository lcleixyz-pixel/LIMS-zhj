<?php
declare(strict_types=1);

namespace app\controller;

use app\model\Designation;
use app\model\Employee as EmployeeModel;
use app\model\Site;
use app\service\TrainingEvidenceService;
use think\exception\HttpException;
use think\facade\View;

class Employee extends CrudBase
{
    protected string $modelClass = EmployeeModel::class;
    protected string $viewPrefix = 'employee';
    protected string $pageTitle = '员工管理';

    protected function assignFormContext(): void
    {
        View::assign('sites', Site::where('soft_delete', 0)->where('status', 'active')->order('sort_order', 'asc')->select());
        View::assign('designations', Designation::where('soft_delete', 0)->select());
    }

    public function view()
    {
        $id = (string)$this->request->param('id', '');
        $record = EmployeeModel::find($id);
        if (!$record) {
            throw new HttpException(404, '员工不存在');
        }

        View::assign('record', $record);
        View::assign('employeeCertificates', TrainingEvidenceService::employeeCertificateRows($id));
        View::assign('supervisionRecords', TrainingEvidenceService::supervisionRecordInstances($id));
        View::assign('pageTitle', $this->pageTitle . ' - 详情');

        return View::fetch($this->viewPrefix . '/view');
    }
}
