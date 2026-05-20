<?php
declare(strict_types=1);

namespace app\model;

class Employee extends BaseModel
{
    protected $name = 'employees';

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function designation()
    {
        return $this->belongsTo(Designation::class);
    }
}
