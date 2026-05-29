<?php
declare(strict_types=1);

namespace app\controller;

use app\model\ReferenceMaterial as ReferenceMaterialModel;

class ReferenceMaterial extends BusinessBase
{
    protected string $modelClass = ReferenceMaterialModel::class;
    protected string $viewPrefix = 'reference_material';
    protected string $pageTitle = '标准物质台账';

    protected function assignFormContext(): void
    {
        $this->assignStatusLabels('reference_material');
    }
}
