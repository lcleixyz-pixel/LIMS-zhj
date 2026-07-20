<?php
declare(strict_types=1);

namespace app\model;

class Calibration extends BaseModel
{
    protected $name = 'calibrations';

    public function equipment()
    {
        return $this->belongsTo(Equipment::class);
    }
}
