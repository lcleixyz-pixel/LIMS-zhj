<?php
declare(strict_types=1);

namespace app\model;

class ReviewAction extends BaseModel
{
    protected $name = 'review_actions';

    public function managementReview()
    {
        return $this->belongsTo(ManagementReview::class);
    }
}
