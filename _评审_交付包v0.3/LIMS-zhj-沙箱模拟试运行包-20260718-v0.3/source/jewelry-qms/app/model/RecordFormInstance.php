<?php
declare(strict_types=1);

namespace app\model;

class RecordFormInstance extends BaseModel
{
    protected $name = 'record_form_instances';

    protected $displayField = 'record_title';

    public function template()
    {
        return $this->belongsTo(RecordFormTemplate::class, 'template_id');
    }
}
