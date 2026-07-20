<?php
declare(strict_types=1);

namespace app\model;

class QmsStructuredDocument extends BaseModel
{
    protected $name = 'qms_structured_documents';

    protected $displayField = 'title';

    public function sourceAsset()
    {
        return $this->belongsTo(QmsDocumentAsset::class, 'source_asset_id');
    }

    public function document()
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function blocks()
    {
        return $this->hasMany(QmsDocumentBlock::class, 'structured_document_id');
    }
}
