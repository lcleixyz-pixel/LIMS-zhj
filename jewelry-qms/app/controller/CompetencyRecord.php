<?php
declare(strict_types=1);

namespace app\controller;

use app\model\CompetencyRecord as CompetencyRecordModel;
use think\facade\View;

class CompetencyRecord extends BusinessBase
{
    protected string $modelClass = CompetencyRecordModel::class;
    protected string $viewPrefix = 'competency_record';
    protected string $pageTitle = '能力确认';

    protected function assignFormContext(): void
    {
        $this->assignEmployees();
        $this->assignUsers();
        View::assign('resultOptions', [
            'qualified' => '合格',
            'unqualified' => '不合格',
            'supervised' => '监督下操作',
            'pending' => '待评价',
        ]);
    }
}
