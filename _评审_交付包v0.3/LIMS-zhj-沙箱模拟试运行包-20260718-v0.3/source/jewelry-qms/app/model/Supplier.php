<?php
declare(strict_types=1);

namespace app\model;

class Supplier extends BaseModel
{
    protected $name = 'suppliers';

    public function supplierEvaluations()
    {
        return $this->hasMany(SupplierEvaluation::class);
    }
}
