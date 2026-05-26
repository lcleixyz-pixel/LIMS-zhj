<?php
declare(strict_types=1);

namespace app\model;

class RecordFormTemplate extends BaseModel
{
    protected $name = 'record_form_templates';

    protected $displayField = 'name';

    public function document()
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function instances()
    {
        return $this->hasMany(RecordFormInstance::class, 'template_id');
    }
}
