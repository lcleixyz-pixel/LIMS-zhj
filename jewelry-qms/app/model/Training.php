<?php
declare(strict_types=1);

namespace app\model;

class Training extends BaseModel
{
    protected $name = 'trainings';

    public function trainingPlan()
    {
        return $this->belongsTo(TrainingPlan::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function trainingRecords()
    {
        return $this->hasMany(TrainingRecord::class);
    }
}
