<?php
declare(strict_types=1);

namespace app\model;

class DocumentRevision extends BaseModel
{
    protected $name = 'document_revisions';

    protected $updateTime = false;

    public function document()
    {
        return $this->belongsTo(Document::class);
    }
}
