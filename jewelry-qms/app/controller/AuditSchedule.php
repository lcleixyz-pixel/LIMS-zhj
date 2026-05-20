<?php
declare(strict_types=1);

namespace app\controller;

use app\model\AuditSchedule as AuditScheduleModel;

class AuditSchedule extends CrudBase
{
    protected string $modelClass = AuditScheduleModel::class;
    protected string $viewPrefix = 'audit_schedule';
    protected string $pageTitle = '审核日程';
}
