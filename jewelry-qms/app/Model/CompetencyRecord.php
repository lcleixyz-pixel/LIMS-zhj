<?php
declare(strict_types=1);

namespace app\model;

class CompetencyRecord extends BaseModel
{
    protected $name = 'competency_records';

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
