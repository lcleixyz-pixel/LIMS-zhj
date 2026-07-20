<?php
declare(strict_types=1);

namespace app\model;

class QmsPositionAlias extends BaseModel
{
    protected $name = 'qms_position_aliases';

    public function position()
    {
        return $this->belongsTo(QmsPosition::class, 'position_id');
    }

    public function site()
    {
        return $this->belongsTo(Site::class, 'site_id');
    }
}
