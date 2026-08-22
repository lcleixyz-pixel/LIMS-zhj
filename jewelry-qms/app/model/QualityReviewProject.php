<?php
declare(strict_types=1);

namespace app\model;

class QualityReviewProject extends BaseModel
{
    protected $name = 'quality_review_projects';

    protected $displayField = 'title';

    public function tasks()
    {
        return $this->hasMany(QualityReviewTask::class, 'project_id');
    }

    public function events()
    {
        return $this->hasMany(QualityReviewEvent::class, 'project_id');
    }
}
