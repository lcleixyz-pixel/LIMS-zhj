<?php
declare(strict_types=1);

namespace app\model;

class QmsDocumentBlock extends BaseModel
{
    protected $name = 'qms_document_blocks';

    protected $displayField = 'title';

    public function structuredDocument()
    {
        return $this->belongsTo(QmsStructuredDocument::class, 'structured_document_id');
    }

    public function document()
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function links()
    {
        return $this->hasMany(QmsDocumentBlockLink::class, 'block_id');
    }
}
