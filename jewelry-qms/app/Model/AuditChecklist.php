<?php
declare(strict_types=1);

namespace app\model;

class AuditChecklist extends BaseModel
{
    protected $name = 'audit_checklists';

    public function auditSchedule()
    {
        return $this->belongsTo(AuditSchedule::class);
    }
}
