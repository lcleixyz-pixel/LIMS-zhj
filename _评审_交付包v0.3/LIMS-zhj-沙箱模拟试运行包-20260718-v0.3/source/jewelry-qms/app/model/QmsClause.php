<?php
declare(strict_types=1);

namespace app\model;

class QmsClause extends BaseModel
{
    protected $name = 'qms_clauses';

    protected $displayField = 'title';

    public function source()
    {
        return $this->belongsTo(QmsSource::class, 'source_id');
    }
}
