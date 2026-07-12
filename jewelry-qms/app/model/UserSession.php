<?php
declare(strict_types=1);

namespace app\model;

class UserSession extends BaseModel
{
    protected $name = 'user_sessions';

    protected $autoWriteTimestamp = false;
}
