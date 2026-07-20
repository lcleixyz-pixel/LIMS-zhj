<?php
declare(strict_types=1);

namespace app\model;

class QmsDocumentBlockLink extends BaseModel
{
    protected $name = 'qms_document_block_links';

    public function block()
    {
        return $this->belongsTo(QmsDocumentBlock::class, 'block_id');
    }

    public function element()
    {
        return $this->belongsTo(QmsElement::class, 'element_id');
    }

    public function clause()
    {
        return $this->belongsTo(QmsClause::class, 'clause_id');
    }

    public function manualSection()
    {
        return $this->belongsTo(QmsManualSection::class, 'manual_section_id');
    }

    public function procedureDocument()
    {
        return $this->belongsTo(Document::class, 'procedure_document_id');
    }

    public function recordFormTemplate()
    {
        return $this->belongsTo(RecordFormTemplate::class, 'record_form_template_id');
    }

    public function position()
    {
        return $this->belongsTo(QmsPosition::class, 'position_id');
    }
}
