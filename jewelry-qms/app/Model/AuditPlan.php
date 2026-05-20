<?php
declare(strict_types=1);

namespace app\model;

class AuditPlan extends BaseModel
{
    protected $name = 'audit_plans';

    public function auditSchedules()
    {
        return $this->hasMany(AuditSchedule::class);
    }
}
