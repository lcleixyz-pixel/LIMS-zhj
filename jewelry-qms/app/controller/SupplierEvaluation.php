<?php
declare(strict_types=1);

namespace app\controller;

use app\model\SupplierEvaluation as SupplierEvaluationModel;

class SupplierEvaluation extends CrudBase
{
    protected string $modelClass = SupplierEvaluationModel::class;
    protected string $viewPrefix = 'supplier_evaluation';
    protected string $pageTitle = '供应商评价';
}
