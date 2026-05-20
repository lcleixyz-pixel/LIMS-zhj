<?php
declare(strict_types=1);

namespace app\controller;

use app\model\Supplier;
use app\model\SupplierEvaluation as SupplierEvaluationModel;
use think\facade\Session;
use think\facade\View;

class SupplierEvaluation extends BusinessBase
{
    protected string $modelClass = SupplierEvaluationModel::class;
    protected string $viewPrefix = 'supplier_evaluation';
    protected string $pageTitle = '供应商评价';

    protected function assignFormContext(): void
    {
        $this->assignUsers();
        View::assign('suppliers', Supplier::where('soft_delete', 0)->select());
        View::assign('typeOptions', [
            'initial' => '准入评价',
            'periodic' => '年度复评',
            'reevaluation' => '重新评价',
        ]);
        View::assign('conclusionOptions', [
            'acceptable' => '可接受',
            'conditional' => '有条件接受',
            'unacceptable' => '不可接受',
        ]);
    }

    public function add()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $model = $this->getModel();
            $model->save($data);
            $supplier = Supplier::find($data['supplier_id'] ?? '');
            if ($supplier) {
                $conclusion = $data['conclusion'] ?? '';
                if ($conclusion === 'acceptable') {
                    $supplier->status = 'qualified';
                } elseif ($conclusion === 'unacceptable') {
                    $supplier->status = 'removed';
                } elseif ($conclusion === 'conditional') {
                    $supplier->status = 'suspended';
                }
                $supplier->save();
            }
            Session::flash('success', '评价已保存，供应商状态已更新');

            return redirect($this->listRedirectUrl());
        }
        View::assign('pageTitle', $this->pageTitle . ' - 新增');
        $this->assignFormContext();

        return View::fetch($this->viewPrefix . '/add');
    }
}
