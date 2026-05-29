<?php
declare(strict_types=1);

namespace app\model;

class Equipment extends BaseModel
{
    protected $name = 'equipments';

    protected $displayField = 'name';

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function calibrations()
    {
        return $this->hasMany(Calibration::class);
    }

    public function equipmentMaintenances()
    {
        return $this->hasMany(EquipmentMaintenance::class);
    }

    public function equipmentTransfers()
    {
        return $this->hasMany(EquipmentTransfer::class);
    }
}
