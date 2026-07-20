<?php
declare(strict_types=1);

namespace app\model;

class TrainingRecord extends BaseModel
{
    protected $name = 'training_records';

    public function training()
    {
        return $this->belongsTo(Training::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
