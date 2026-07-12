<?php
declare(strict_types=1);

namespace app\model;

class QmsElementDocument extends BaseModel
{
    protected $name = 'qms_element_documents';

    public function element()
    {
        return $this->belongsTo(QmsElement::class, 'element_id');
    }

    public function document()
    {
        return $this->belongsTo(Document::class, 'document_id');
    }
}
