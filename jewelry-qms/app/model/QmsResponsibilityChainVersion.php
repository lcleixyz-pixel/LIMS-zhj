<?php
declare(strict_types=1);

namespace app\model;

class QmsResponsibilityChainVersion extends BaseModel
{
    protected $name = 'qms_responsibility_chain_versions';

    public function activities()
    {
        return $this->hasMany(QmsResponsibilityActivity::class, 'chain_version_id');
    }

    public function approvals()
    {
        return $this->hasMany(QmsResponsibilityApproval::class, 'chain_version_id');
    }
}
