<?php
declare(strict_types=1);

namespace app\model;

class QmsExternalChangeCandidate extends BaseModel
{
    protected $name = 'qms_external_change_candidates';

    protected $autoWriteTimestamp = 'datetime';

    protected $createTime = 'created';

    protected $updateTime = 'modified';

    protected $type = [
        'evidence_refs' => 'json',
        'impact_analysis' => 'json',
    ];
}
