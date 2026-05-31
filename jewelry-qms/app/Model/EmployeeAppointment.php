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
}
