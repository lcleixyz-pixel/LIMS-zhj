<?php
declare(strict_types=1);

namespace app\model;

class QmsElementClauseMapping extends BaseModel
{
    protected $name = 'qms_element_clause_mappings';

    public function element()
    {
        return $this->belongsTo(QmsRequirementElement::class, 'element_id');
    }

    public function source()
    {
        return $this->belongsTo(QmsSource::class, 'source_id');
    }
}
