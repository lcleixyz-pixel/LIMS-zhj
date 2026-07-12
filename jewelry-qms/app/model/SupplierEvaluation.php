<?php
declare(strict_types=1);

namespace app\model;

class SupplierEvaluation extends BaseModel
{
    protected $name = 'supplier_evaluations';

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
