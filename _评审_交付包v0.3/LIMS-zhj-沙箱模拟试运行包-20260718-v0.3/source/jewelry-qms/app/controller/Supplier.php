<?php
declare(strict_types=1);

namespace app\controller;

use app\model\Supplier as SupplierModel;
use app\model\SupplierEvaluation;
use think\facade\View;

class Supplier extends BusinessBase
{
    protected string $modelClass = SupplierModel::class;
    protected string $viewPrefix = 'supplier';
    protected string $pageTitle = '供应商管理';

    protected function assignFormContext(): void
    {
        $this->assignStatusLabels('supplier');
    }

    public function index()
    {
        $query = SupplierModel::where('soft_delete', 0);
        if ($this->request->get('status')) {
            $query->where('status', $this->request->get('status'));
        }
        $items = $query->order('created', 'desc')->paginate(20);
        $this->assignStatusLabels('supplier');
        View::assign('items', $items);
        View::assign('pages', $items->render());
        View::assign('pageTitle', $this->pageTitle);

        return View::fetch($this->viewPrefix . '/index');
    }

    public function view()
    {
        $id = $this->request->param('id');
        $record = SupplierModel::find($id);
        if (!$record) {
            abort(404);
        }
        $evaluations = SupplierEvaluation::where('supplier_id', $id)->where('soft_delete', 0)->order('evaluation_date', 'desc')->select();
        $this->assignFormContext();
        View::assign('record', $record);
        View::assign('evaluations', $evaluations);
        View::assign('pageTitle', $this->pageTitle . ' - 详情');

        return View::fetch($this->viewPrefix . '/view');
    }

    public function qualified()
    {
        $items = SupplierModel::where('soft_delete', 0)->where('status', 'qualified')->select();
        View::assign('items', $items);
        View::assign('pageTitle', '合格供应商名录');

        return View::fetch($this->viewPrefix . '/qualified');
    }
}
