<?php
declare(strict_types=1);

namespace app\controller;

use app\model\AuditPlan as AuditPlanModel;

class AuditPlan extends CrudBase
{
    protected string $modelClass = AuditPlanModel::class;
    protected string $viewPrefix = 'audit_plan';
    protected string $pageTitle = '内审计划';
}
