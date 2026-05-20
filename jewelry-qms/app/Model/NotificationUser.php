<?php
declare(strict_types=1);

namespace app\model;

class NotificationUser extends BaseModel
{
    protected $name = 'notification_users';

    public function notification()
    {
        return $this->belongsTo(Notification::class);
    }
}
