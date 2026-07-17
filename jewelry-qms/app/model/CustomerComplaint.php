<?php
declare(strict_types=1);

namespace app\model;

class CustomerComplaint extends BaseModel
{
    protected $name = 'customer_complaints';

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
