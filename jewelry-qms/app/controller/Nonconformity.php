<?php
declare(strict_types=1);

namespace app\controller;

use app\model\Nonconformity as NonconformityModel;

class Nonconformity extends CrudBase
{
    protected string $modelClass = NonconformityModel::class;
    protected string $viewPrefix = 'nonconformity';
    protected string $pageTitle = '不符合工作';
}
