<?php
declare(strict_types=1);

namespace app\model;

class QmsBusinessModule extends BaseModel
{
    protected $name = 'qms_business_modules';

    public function primaryElement()
    {
        return $this->belongsTo(QmsElement::class, 'primary_element_id');
    }
}
