<?php
declare(strict_types=1);

namespace app\model;

class Nonconformity extends BaseModel
{
    protected $name = 'nonconformities';

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
