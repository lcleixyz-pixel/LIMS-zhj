<?php
declare(strict_types=1);

namespace app\controller;

use app\model\AuditFinding as AuditFindingModel;

class AuditFinding extends CrudBase
{
    protected string $modelClass = AuditFindingModel::class;
    protected string $viewPrefix = 'audit_finding';
    protected string $pageTitle = '审核发现';
}
