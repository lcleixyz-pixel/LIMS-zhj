<?php
declare(strict_types=1);

namespace app\model;

class TrainingPlan extends BaseModel
{
    protected $name = 'training_plans';

    public function trainings()
    {
        return $this->hasMany(Training::class);
    }
}
