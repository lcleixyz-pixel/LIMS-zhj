<?php
declare(strict_types=1);

namespace app\model;

class Department extends BaseModel
{
    protected $name = 'departments';

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
