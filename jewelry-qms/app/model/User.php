<?php
declare(strict_types=1);

namespace app\model;

class User extends BaseModel
{
    protected $name = 'users';

    protected $displayField = 'name';

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
