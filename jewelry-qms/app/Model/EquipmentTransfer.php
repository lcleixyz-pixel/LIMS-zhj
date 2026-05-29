<?php
declare(strict_types=1);

namespace app\model;

class EquipmentTransfer extends BaseModel
{
    protected $name = 'equipment_transfers';

    public function equipment()
    {
        return $this->belongsTo(Equipment::class);
    }
}
