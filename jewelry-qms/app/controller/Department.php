<?php
declare(strict_types=1);

namespace app\controller;

use app\model\Department as DepartmentModel;

class Department extends CrudBase
{
    protected string $modelClass = DepartmentModel::class;
    protected string $viewPrefix = 'department';
    protected string $pageTitle = '部门管理';
}
