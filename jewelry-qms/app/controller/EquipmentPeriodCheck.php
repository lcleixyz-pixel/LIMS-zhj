<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use think\facade\Db;
use think\facade\Session;
use think\facade\View;

/**
 * 期间核查计划（基础入口）。
 */
class EquipmentPeriodCheck extends BaseController
{
    public function index()
    {
        $equipmentId = trim((string)$this->request->param('equipment_id', ''));
        $query = Db::name('equipment_period_checks')->where('soft_delete', 0);
        if ($equipmentId !== '') {
            $query->where('equipment_id', $equipmentId);
        }
        $items = $query->order('created', 'desc')->limit(50)->select()->toArray();

        View::assign('pageTitle', '期间核查计划');
        View::assign('items', $items);
        View::assign('equipmentId', $equipmentId);
        View::assign('mechanismNotice', '本页为期间核查计划基础入口，可登记计划日期与备注。');

        return View::fetch('equipment_period_check/index');
    }

    public function add()
    {
        if ($this->request->isPost()) {
            $equipmentId = trim((string)$this->request->post('equipment_id', ''));
            $planDate = trim((string)$this->request->post('plan_date', ''));
            $note = trim((string)$this->request->post('note', ''));
            if ($equipmentId === '' || $planDate === '') {
                Session::flash('validation_errors', ['设备与计划日期不能为空']);
                View::assign('form', $this->request->post());
                View::assign('pageTitle', '期间核查计划 - 新增');
                View::assign('mechanismNotice', '请填写设备与计划日期。');

                return View::fetch('equipment_period_check/add');
            }

            if (!$this->tableReady()) {
                Session::flash('warning', '期间核查计划表尚未迁移，仅保留入口，未写入数据。');

                return redirect('/equipment_period_check/index?equipment_id=' . rawurlencode($equipmentId));
            }

            $id = qms_uuid();
            Db::name('equipment_period_checks')->insert([
                'id' => $id,
                'company_id' => (string)\think\facade\Config::get('qms.company_id'),
                'equipment_id' => $equipmentId,
                'plan_date' => $planDate,
                'status' => 'planned',
                'note' => $note,
                'publish' => 1,
                'soft_delete' => 0,
                'created' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s'),
            ]);
            Session::flash('success', '期间核查计划草稿已保存。');

            return redirect('/equipment_period_check/index?equipment_id=' . rawurlencode($equipmentId));
        }

        View::assign('pageTitle', '期间核查计划 - 新增');
        View::assign('form', [
            'equipment_id' => trim((string)$this->request->param('equipment_id', '')),
            'plan_date' => date('Y-m-d'),
        ]);
        View::assign('mechanismNotice', '请填写设备与计划日期。');

        return View::fetch('equipment_period_check/add');
    }

    private function tableReady(): bool
    {
        try {
            Db::name('equipment_period_checks')->limit(1)->select();

            return true;
        } catch (\Throwable $exception) {
            return false;
        }
    }
}
