<?php
declare(strict_types=1);

namespace app\controller;

use app\model\Employee as EmployeeModel;

class Employee extends CrudBase
{
    protected string $modelClass = EmployeeModel::class;
    protected string $viewPrefix = 'employee';
    protected string $pageTitle = '员工管理';
}
