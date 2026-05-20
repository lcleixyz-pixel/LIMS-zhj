<?php
declare(strict_types=1);

namespace app\controller;

use app\model\AuditChecklist as AuditChecklistModel;
use app\model\AuditSchedule;

class AuditChecklist extends BusinessBase
{
    protected string $modelClass = AuditChecklistModel::class;
    protected string $viewPrefix = 'audit_checklist';
    protected string $pageTitle = '审核检查表';

    protected function assignFormContext(): void
    {
        $scheduleId = $this->request->param('audit_schedule_id', '');
        \think\facade\View::assign('auditSchedules', AuditSchedule::where('soft_delete', 0)->select());
        \think\facade\View::assign('defaultScheduleId', $scheduleId);
        \think\facade\View::assign('resultOptions', [
            'conform' => '符合',
            'nonconform' => '不符合',
            'observation' => '观察项',
            'na' => '不适用',
        ]);
    }
}
