<?php
declare(strict_types=1);

namespace app\model;

class QmsResponsibilityActivity extends BaseModel
{
    protected $name = 'qms_responsibility_activities';

    protected $type = [
        'source_refs' => 'json',
    ];

    public function chainVersion()
    {
        return $this->belongsTo(QmsResponsibilityChainVersion::class, 'chain_version_id');
    }

    public function responsibilities()
    {
        return $this->hasMany(QmsActivityResponsibility::class, 'activity_id');
    }
}
