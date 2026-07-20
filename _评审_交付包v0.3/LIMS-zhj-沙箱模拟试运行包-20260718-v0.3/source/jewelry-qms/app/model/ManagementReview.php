<?php
declare(strict_types=1);

namespace app\model;

class ManagementReview extends BaseModel
{
    protected $name = 'management_reviews';

    public function reviewActions()
    {
        return $this->hasMany(ReviewAction::class);
    }
}
