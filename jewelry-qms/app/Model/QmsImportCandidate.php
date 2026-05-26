<?php
declare(strict_types=1);

namespace app\model;

class QmsImportCandidate extends BaseModel
{
    protected $name = 'qms_import_candidates';

    public function batch()
    {
        return $this->belongsTo(QmsImportBatch::class, 'batch_id');
    }
}
