<?php
declare(strict_types=1);

namespace app\model;

class QmsActivityResponsibility extends BaseModel
{
    protected $name = 'qms_activity_responsibilities';

    protected $type = [
        'eligibility_rule' => 'json',
        'rule_codes' => 'json',
        'source_refs' => 'json',
    ];

    public function activity()
    {
        return $this->belongsTo(QmsResponsibilityActivity::class, 'activity_id');
    }

    public function fixedPosition()
    {
        return $this->belongsTo(QmsPosition::class, 'fixed_position_id');
    }

    public function assignments()
    {
        return $this->hasMany(QmsResponsibilityAssignment::class, 'responsibility_id');
    }
}
