<?php
declare(strict_types=1);

namespace app\model;

class QualityReviewEvent extends BaseModel
{
    protected $name = 'quality_review_events';

    protected $updateTime = false;

    public function project()
    {
        return $this->belongsTo(QualityReviewProject::class, 'project_id');
    }

    public function task()
    {
        return $this->belongsTo(QualityReviewTask::class, 'task_id');
    }
}
