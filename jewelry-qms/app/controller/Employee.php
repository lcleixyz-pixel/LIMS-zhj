<?php
declare(strict_types=1);

namespace app\controller;

use app\model\Designation;
use app\model\Employee as EmployeeModel;
use app\model\Site;
use app\service\TrainingEvidenceService;
use think\exception\HttpException;
use think\facade\Db;
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
        View::assign('employeeAppointments', Db::name('employee_appointments')
            ->alias('a')
            ->leftJoin('qms_positions p', 'p.id = a.position_id')
            ->leftJoin('sites s', 's.id = a.site_id')
            ->where('a.employee_id', $id)
            ->where('a.soft_delete', 0)
            ->field('a.appointment_type,a.position_name,a.appointment_scope,a.appointed_at,a.valid_until,a.source_document_number,a.status,p.name position_display,s.name site_name')
            ->order('a.appointment_type', 'asc')
            ->order('a.position_name', 'asc')
            ->select()
            ->toArray());
        View::assign('pageTitle', $this->pageTitle . ' - 详情');

        return View::fetch($this->viewPrefix . '/view');
    }
}
