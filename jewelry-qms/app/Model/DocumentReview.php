<?php
declare(strict_types=1);

namespace app\model;

class DocumentReview extends BaseModel
{
    protected $name = 'document_reviews';

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
