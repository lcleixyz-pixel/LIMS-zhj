<?php
declare(strict_types=1);

namespace app\controller;

use app\model\CompetencyRecord as CompetencyRecordModel;

class CompetencyRecord extends CrudBase
{
    protected string $modelClass = CompetencyRecordModel::class;
    protected string $viewPrefix = 'competency_record';
    protected string $pageTitle = '能力确认';
}
