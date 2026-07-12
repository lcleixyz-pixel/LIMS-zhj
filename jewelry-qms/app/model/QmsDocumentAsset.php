<?php
declare(strict_types=1);

namespace app\model;

class QmsDocumentAsset extends BaseModel
{
    protected $name = 'qms_document_assets';

    protected $displayField = 'normalized_name';

    public function document()
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function source()
    {
        return $this->belongsTo(QmsSource::class, 'source_id');
    }

    public function recordFormTemplate()
    {
        return $this->belongsTo(RecordFormTemplate::class, 'record_form_template_id');
    }
}
