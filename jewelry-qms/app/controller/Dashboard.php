<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\Document;
use app\model\Equipment;
use app\model\Capa;
use app\model\CustomerComplaint;
use app\model\Nonconformity;
use app\model\AuditFinding;
use think\facade\View;

class Dashboard extends BaseController
{
    public function index()
    {
        $stats = [
            'documents_total' => Document::where('soft_delete', 0)->count(),
            'documents_pending' => Document::whereIn('status', ['draft', 'reviewing'])->where('soft_delete', 0)->count(),
            'equipment_total' => Equipment::where('soft_delete', 0)->count(),
            'calibration_due' => Equipment::where('soft_delete', 0)
                ->where('calibration_required', 1)
                ->whereTime('next_calibration_date', '<=', date('Y-m-d', strtotime('+30 days')))
                ->count(),
            'capa_open' => Capa::where('status', '<>', 'closed')->where('soft_delete', 0)->count(),
            'complaints_open' => CustomerComplaint::where('status', '<>', 'closed')->where('soft_delete', 0)->count(),
            'nc_open' => Nonconformity::where('status', '<>', 'closed')->where('soft_delete', 0)->count(),
            'audit_findings_open' => AuditFinding::where('status', '<>', 'closed')->where('soft_delete', 0)->count(),
        ];

        $upcomingCalibrations = Equipment::where('soft_delete', 0)
            ->where('next_calibration_date', '<=', date('Y-m-d', strtotime('+30 days')))
            ->order('next_calibration_date', 'asc')
            ->limit(5)
            ->select();

        View::assign('stats', $stats);
        View::assign('upcomingCalibrations', $upcomingCalibrations);
        return View::fetch('dashboard/index');
    }
}
