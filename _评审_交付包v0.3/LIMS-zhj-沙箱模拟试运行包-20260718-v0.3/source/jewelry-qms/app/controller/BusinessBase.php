<?php
declare(strict_types=1);

namespace app\controller;

use app\model\Department;
use app\model\Employee;
use app\model\User;
use think\facade\Config;
use think\facade\View;

abstract class BusinessBase extends CrudBase
{
    protected function assignDepartments(): void
    {
        View::assign('departments', Department::where('soft_delete', 0)->where('publish', 1)->select());
    }

    protected function assignEmployees(): void
    {
        View::assign('employees', Employee::where('soft_delete', 0)->where('publish', 1)->select());
    }

    protected function assignUsers(): void
    {
        View::assign('users', User::where('soft_delete', 0)->where('publish', 1)->select());
    }

    protected function assignStatusLabels(string $module): void
    {
        $labels = Config::get('qms.statusLabels.' . $module, []);
        $options = [];
        foreach ($labels as $value => $label) {
            $options[] = [
                'value' => $value,
                'label' => $label,
            ];
        }

        View::assign('statusLabels', $labels);
        View::assign('statusOptions', $options);
    }

    protected function assignCommonForm(): void
    {
        $this->assignDepartments();
        $this->assignEmployees();
        $this->assignUsers();
    }
}
