<?php
declare(strict_types=1);

namespace app\model;

class QmsRegulatoryMonitorRun extends BaseModel
{
    protected $name = 'qms_regulatory_monitor_runs';

    protected $autoWriteTimestamp = 'datetime';

    protected $createTime = 'created';

    protected $updateTime = 'modified';

    protected $type = [
        'source_stats' => 'json',
        'candidate_stats' => 'json',
        'result_json' => 'json',
    ];
}
