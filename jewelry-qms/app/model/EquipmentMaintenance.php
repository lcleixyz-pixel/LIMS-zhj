<?php
declare(strict_types=1);

namespace app\model;

class EquipmentMaintenance extends BaseModel
{
    protected $name = 'equipment_maintenances';

    public function equipment()
    {
        return $this->belongsTo(Equipment::class);
    }
}
