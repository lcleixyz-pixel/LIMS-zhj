<?php
declare(strict_types=1);

namespace app\controller;

use app\model\Capa as CapaModel;

class Capa extends CrudBase
{
    protected string $modelClass = CapaModel::class;
    protected string $viewPrefix = 'capa';
    protected string $pageTitle = 'CAPA';
}
