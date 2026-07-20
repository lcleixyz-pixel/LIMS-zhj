<?php
declare(strict_types=1);

namespace app\model;

class EmployeeCertificate extends BaseModel
{
    protected $name = 'employee_certificates';

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
