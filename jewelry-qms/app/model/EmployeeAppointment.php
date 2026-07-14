<?php
declare(strict_types=1);

namespace app\model;

class EmployeeAppointment extends BaseModel
{
    protected $name = 'employee_appointments';

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function position()
    {
        return $this->belongsTo(QmsPosition::class, 'position_id');
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function chainVersion()
    {
        return $this->belongsTo(QmsResponsibilityChainVersion::class, 'source_chain_version_id');
    }

    public function responsibility()
    {
        return $this->belongsTo(QmsActivityResponsibility::class, 'source_responsibility_id');
    }

    public function responsibilityApproval()
    {
        return $this->belongsTo(QmsResponsibilityApproval::class, 'source_approval_id');
    }
}
