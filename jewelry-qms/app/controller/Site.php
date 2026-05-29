<?php
declare(strict_types=1);

namespace app\controller;

use app\model\Site as SiteModel;
use think\facade\View;

class Site extends CrudBase
{
    protected string $modelClass = SiteModel::class;
    protected string $viewPrefix = 'site';
    protected string $pageTitle = '场所管理';

    protected function assignFormContext(): void
    {
        View::assign('siteTypes', ['main' => '主场所', 'branch' => '分场所']);
        View::assign('siteStatuses', ['active' => '启用', 'inactive' => '停用']);
    }
}
