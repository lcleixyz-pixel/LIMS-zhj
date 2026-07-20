<?php
declare(strict_types=1);

namespace app\model;

class QmsElementResponsibility extends BaseModel
{
    protected $name = 'qms_element_responsibilities';

    public function element()
    {
        return $this->belongsTo(QmsElement::class, 'element_id');
    }

    public function position()
    {
        return $this->belongsTo(QmsPosition::class, 'position_id');
    }
}
