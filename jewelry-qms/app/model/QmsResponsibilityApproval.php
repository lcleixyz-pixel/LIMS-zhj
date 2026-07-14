<?php
declare(strict_types=1);

namespace app\model;

class QmsResponsibilityApproval extends BaseModel
{
    protected $name = 'qms_responsibility_approvals';

    protected $type = [
        'signature_metadata' => 'json',
    ];

    public function chainVersion()
    {
        return $this->belongsTo(QmsResponsibilityChainVersion::class, 'chain_version_id');
    }

    public function assignment()
    {
        return $this->belongsTo(QmsResponsibilityAssignment::class, 'assignment_id');
    }
}
