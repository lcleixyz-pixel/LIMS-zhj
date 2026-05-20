<?php
declare(strict_types=1);

namespace app\model;

class AuditSchedule extends BaseModel
{
    protected $name = 'audit_schedules';

    public function auditPlan()
    {
        return $this->belongsTo(AuditPlan::class);
    }

    public function auditFindings()
    {
        return $this->hasMany(AuditFinding::class);
    }

    public function auditChecklists()
    {
        return $this->hasMany(AuditChecklist::class);
    }
}
