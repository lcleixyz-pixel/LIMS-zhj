<?php
declare(strict_types=1);

namespace app\model;

class Capa extends BaseModel
{
    protected $name = 'capas';

    protected $displayField = 'capa_number';

    public function capaSource()
    {
        return $this->belongsTo(CapaSource::class, 'source_id');
    }

    public function assignedEmployee()
    {
        return $this->belongsTo(Employee::class, 'assigned_to');
    }
}
