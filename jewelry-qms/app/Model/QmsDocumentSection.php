<?php
declare(strict_types=1);

namespace app\model;

class QmsDocumentSection extends BaseModel
{
    protected $name = 'qms_document_sections';

    protected $displayField = 'title';

    public function document()
    {
        return $this->belongsTo(Document::class, 'document_id');
    }
}
