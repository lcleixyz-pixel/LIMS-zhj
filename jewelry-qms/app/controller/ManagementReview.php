<?php
declare(strict_types=1);

namespace app\controller;

use app\model\ManagementReview as ManagementReviewModel;

class ManagementReview extends CrudBase
{
    protected string $modelClass = ManagementReviewModel::class;
    protected string $viewPrefix = 'management_review';
    protected string $pageTitle = '管理评审';
}
