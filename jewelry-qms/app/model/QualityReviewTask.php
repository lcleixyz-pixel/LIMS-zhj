<?php
declare(strict_types=1);

namespace app\model;

class QualityReviewTask extends BaseModel
{
    protected $name = 'quality_review_tasks';

    protected $displayField = 'title';

    public function project()
    {
        return $this->belongsTo(QualityReviewProject::class, 'project_id');
    }

    public function events()
    {
        return $this->hasMany(QualityReviewEvent::class, 'task_id');
    }
}
