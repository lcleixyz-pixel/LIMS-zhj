<?php
declare(strict_types=1);

namespace app\model;

class EquipmentAuthorization extends BaseModel
{
    protected $name = 'equipment_authorizations';

    public function equipment()
    {
        return $this->belongsTo(Equipment::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
