<?php
declare(strict_types=1);

namespace app\model;

class DocumentDistribution extends BaseModel
{
    protected $name = 'document_distributions';

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
