<?php
declare(strict_types=1);

namespace app\model;

class QmsBusinessModuleElement extends BaseModel
{
    protected $name = 'qms_business_module_elements';

    public function module()
    {
        return $this->belongsTo(QmsBusinessModule::class, 'module_id');
    }

    public function element()
    {
        return $this->belongsTo(QmsElement::class, 'element_id');
    }
}
