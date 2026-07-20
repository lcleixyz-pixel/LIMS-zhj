<?php
declare(strict_types=1);

namespace app\model;

class QmsElementClauseLink extends BaseModel
{
    protected $name = 'qms_element_clause_links';

    public function element()
    {
        return $this->belongsTo(QmsElement::class, 'element_id');
    }

    public function clause()
    {
        return $this->belongsTo(QmsClause::class, 'clause_id');
    }
}
