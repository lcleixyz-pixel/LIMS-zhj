<?php
declare(strict_types=1);

namespace app\model;

class QmsResponsibilityMatrix extends BaseModel
{
    protected $name = 'qms_responsibility_matrix';

    public function clause()
    {
        return $this->belongsTo(QmsClause::class, 'clause_id');
    }

    public function position()
    {
        return $this->belongsTo(QmsPosition::class, 'position_id');
    }
}
