<?php
declare(strict_types=1);

namespace app\model;

class Approval extends BaseModel
{
    protected $name = 'approvals';

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
