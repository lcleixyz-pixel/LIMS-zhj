<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\Equipment;
use app\model\EquipmentTransfer as TransferModel;
use app\model\Site;
use think\exception\HttpException;
use think\facade\Session;
use think\facade\View;

class EquipmentTransfer extends BaseController
{
    public function index()
    {
        $items = TransferModel::where('soft_delete', 0)
            ->order('transfer_date', 'desc')
            ->order('created', 'desc')
            ->paginate(20);

        View::assign('items', $items);
        View::assign('pages', $items->render());
        View::assign('siteMap', Site::where('soft_delete', 0)->column('name', 'id'));
        View::assign('equipmentMap', Equipment::where('soft_delete', 0)->column('name', 'id'));

        return View::fetch('equipment_transfer/index');
    }

    public function add()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $equipment = Equipment::where('soft_delete', 0)->find($data['equipment_id'] ?? '');
            if (!$equipment) {
                Session::flash('error', '请选择有效设备');

                return redirect('/equipment_transfer/add');
            }
            if (empty($data['to_site_id']) || !Site::where('soft_delete', 0)->find($data['to_site_id'])) {
                Session::flash('error', '请选择调入场所');

                return redirect('/equipment_transfer/add');
            }
            if (empty($data['transfer_date'])) {
                Session::flash('error', '请选择调拨日期');

                return redirect('/equipment_transfer/add');
            }

            $transfer = new TransferModel();
            $transfer->equipment_id = $equipment->id;
            $transfer->from_site_id = $equipment->site_id ?: null;
            $transfer->to_site_id = $data['to_site_id'];
            $transfer->transfer_date = $data['transfer_date'];
            $transfer->reason = trim((string)($data['reason'] ?? ''));
            $transfer->transferred_by = $data['transferred_by'] ?? null;
            $transfer->remarks = trim((string)($data['remarks'] ?? ''));
            $transfer->save();

            $equipment->site_id = $transfer->to_site_id;
            $equipment->save();

            Session::flash('success', '设备调拨已记录，设备归属场所已更新');

            return redirect('/equipment/view?id=' . $equipment->id);
        }

        $this->assignFormContext();

        return View::fetch('equipment_transfer/add');
    }

    public function view()
    {
        $record = TransferModel::find($this->request->param('id'));
        if (!$record) {
            throw new HttpException(404, '调拨记录不存在');
        }

        View::assign('record', $record);
        View::assign('equipment', Equipment::find($record->equipment_id));
        View::assign('fromSite', $record->from_site_id ? Site::find($record->from_site_id) : null);
        View::assign('toSite', Site::find($record->to_site_id));

        return View::fetch('equipment_transfer/view');
    }

    private function assignFormContext(): void
    {
        View::assign('equipments', Equipment::where('soft_delete', 0)->order('equipment_number', 'asc')->select());
        View::assign('sites', Site::where('soft_delete', 0)->where('status', 'active')->order('sort_order', 'asc')->select());
    }
}
