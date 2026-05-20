<?php
declare(strict_types=1);

namespace app\controller;

use app\model\Supplier as SupplierModel;

class Supplier extends CrudBase
{
    protected string $modelClass = SupplierModel::class;
    protected string $viewPrefix = 'supplier';
    protected string $pageTitle = '供应商管理';
}
