<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\service\QmsElementService;
use think\facade\View;

class PlanningTraceability extends BaseController
{
    public function index()
    {
        View::assign('columnLabels', QmsElementService::traceabilityColumnLabels());
        View::assign('rows', QmsElementService::traceabilityMatrix());
        View::assign('chainLabel', '外部条款 → 无编号要素 → 手册章节/程序文件 → 记录表格/运行模块 → 岗位职责/运行证据');

        return View::fetch('planning_traceability/index');
    }
}
