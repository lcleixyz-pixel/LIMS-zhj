<?php
declare(strict_types=1);

namespace app\model;

class AuditFinding extends BaseModel
{
    protected $name = 'audit_findings';

    public function auditSchedule()
    {
        return $this->belongsTo(AuditSchedule::class);
    }
}
