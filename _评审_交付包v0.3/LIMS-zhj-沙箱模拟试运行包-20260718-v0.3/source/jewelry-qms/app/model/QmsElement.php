<?php
declare(strict_types=1);

namespace app\model;

class QmsElement extends BaseModel
{
    protected $name = 'qms_elements';

    protected $displayField = 'name';

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function ownerPosition()
    {
        return $this->belongsTo(QmsPosition::class, 'owner_position_id');
    }
}
