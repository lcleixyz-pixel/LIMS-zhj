<?php
declare(strict_types=1);

namespace app\controller;

use app\model\Equipment;
use app\service\ActionAuthorizationService;
use app\BaseController;
use think\facade\Config;
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
        $tableReady = $this->tableReady();
        $items = [];
        if ($tableReady) {
            $query = Db::name('equipment_period_checks')->alias('pc')
                ->leftJoin('equipments e', 'e.id = pc.equipment_id')
                ->where('pc.company_id', (string)Config::get('qms.company_id'))
                ->where('pc.soft_delete', 0)
                ->field('pc.*,e.equipment_number,e.name AS equipment_name');
            $visibleEquipmentIds = $this->visibleEquipmentIds();
            if ($visibleEquipmentIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('pc.equipment_id', $visibleEquipmentIds);
            }
            if ($equipmentId !== '') {
                $query->where('pc.equipment_id', $equipmentId);
            }
            $items = $query->order('pc.created', 'desc')->limit(50)->select()->toArray();
        }

        View::assign([
            'pageTitle' => '期间核查计划',
            'items' => $items,
            'equipmentId' => $equipmentId,
            'tableReady' => $tableReady,
            'mechanismNotice' => $tableReady
                ? '登记设备期间核查的计划日期；实际核查结果仍应使用对应受控记录表单留证。'
                : '功能尚未初始化：期间核查计划数据表尚未迁移，请联系系统管理员完成 8021 初始化。',
        ]);

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
                $this->assignForm((array)$this->request->post(), $this->tableReady());

                return View::fetch('equipment_period_check/add');
            }

            if (!$this->tableReady()) {
                Session::flash('warning', '功能尚未初始化，计划未保存；请联系系统管理员完成 8021 初始化。');

                return redirect('/equipment_period_check/index?equipment_id=' . rawurlencode($equipmentId));
            }
            if (!in_array($equipmentId, $this->visibleEquipmentIds(), true)) {
                Session::flash('validation_errors', ['所选设备不存在或不在当前岗位可见范围内']);
                $this->assignForm((array)$this->request->post(), true);

                return View::fetch('equipment_period_check/add');
            }

            $id = qms_uuid();
            Db::name('equipment_period_checks')->insert([
                'id' => $id,
                'company_id' => (string)Config::get('qms.company_id'),
                'equipment_id' => $equipmentId,
                'plan_date' => $planDate,
                'status' => 'planned',
                'note' => $note,
                'publish' => 1,
                'soft_delete' => 0,
                'created' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s'),
                'created_by' => (string)Session::get('user.id', ''),
            ]);
            Session::flash('success', '期间核查计划草稿已保存。');

            return redirect('/equipment_period_check/index?equipment_id=' . rawurlencode($equipmentId));
        }

        $this->assignForm([
            'equipment_id' => trim((string)$this->request->param('equipment_id', '')),
            'plan_date' => date('Y-m-d'),
        ], $this->tableReady());

        return View::fetch('equipment_period_check/add');
    }

    private function assignForm(array $form, bool $tableReady): void
    {
        $equipmentQuery = Equipment::where('company_id', (string)Config::get('qms.company_id'))
            ->where('soft_delete', 0)
            ->where('publish', 1);
        $visibleEquipmentIds = $this->visibleEquipmentIds();
        if ($visibleEquipmentIds === []) {
            $equipmentQuery->whereRaw('1 = 0');
        } else {
            $equipmentQuery->whereIn('id', $visibleEquipmentIds);
        }

        View::assign([
            'pageTitle' => '期间核查计划 - 新增',
            'form' => $form,
            'equipments' => $equipmentQuery->order('equipment_number', 'asc')->select(),
            'tableReady' => $tableReady,
            'mechanismNotice' => $tableReady
                ? '请选择设备和计划日期；核查结果应另行使用受控记录表单留证。'
                : '功能尚未初始化：当前只能查看说明，不能保存计划。',
        ]);
    }

    /**
     * @return list<string>
     */
    private function visibleEquipmentIds(): array
    {
        $query = Equipment::where('company_id', (string)Config::get('qms.company_id'))
            ->where('soft_delete', 0)
            ->where('publish', 1);
        $visibleSiteIds = ActionAuthorizationService::equipmentVisibleSiteIds();
        if ($visibleSiteIds !== null) {
            if ($visibleSiteIds === []) {
                return [];
            }
            $query->whereIn('site_id', $visibleSiteIds);
        }

        return array_values(array_map('strval', $query->column('id')));
    }

    private function tableReady(): bool
    {
        try {
            $rows = Db::query(
                "SELECT COUNT(*) AS total
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'equipment_period_checks'"
            );

            return (int)($rows[0]['total'] ?? 0) === 1;
        } catch (\Throwable $exception) {
            return false;
        }
    }
}
