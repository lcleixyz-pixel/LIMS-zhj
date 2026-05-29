<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\Approval;
use app\model\AuditFinding;
use app\model\AuditPlan;
use app\model\Capa;
use app\model\CustomerComplaint;
use app\model\Document;
use app\model\Equipment;
use app\model\ManagementReview;
use app\model\Nonconformity;
use app\model\ReviewAction;
use app\model\Site;
use app\model\Training;
use think\facade\Session;
use think\facade\View;

class Dashboard extends BaseController
{
    protected $middleware = [
        \app\middleware\Auth::class,
        \app\middleware\Rbac::class,
        \app\middleware\AuditLog::class,
    ];

    public function index()
    {
        $userId = Session::get('user.id');
        $stats = [
            'docCount' => Document::where('soft_delete', 0)->count(),
            'pendingReview' => Document::whereIn('status', ['draft', 'reviewing'])->where('soft_delete', 0)->count(),
            'equipmentCount' => Equipment::where('soft_delete', 0)->count(),
            'calibrationExpiring' => Equipment::where('soft_delete', 0)
                ->where('calibration_required', 1)
                ->where('next_calibration_date', '<=', date('Y-m-d', strtotime('+30 days')))
                ->count(),
            'activeCapa' => Capa::where('status', '<>', 'closed')->where('soft_delete', 0)->count(),
            'auditPlanCount' => AuditPlan::where('soft_delete', 0)->count(),
            'openComplaints' => CustomerComplaint::where('status', '<>', 'closed')->where('soft_delete', 0)->count(),
            'openNc' => Nonconformity::where('status', '<>', 'closed')->where('soft_delete', 0)->count(),
            'openFindings' => AuditFinding::where('status', '<>', 'closed')->where('soft_delete', 0)->count(),
            'trainingCount' => Training::where('soft_delete', 0)->count(),
            'overdueCapa' => Capa::where('status', '<>', 'closed')
                ->where('due_date', '<', date('Y-m-d'))
                ->whereNotNull('due_date')
                ->where('soft_delete', 0)->count(),
            'pendingApprovals' => Approval::where('user_id', $userId)->where('status', 'pending')->where('soft_delete', 0)->count(),
            'overdueActions' => ReviewAction::where('status', 'overdue')->where('soft_delete', 0)->count(),
            'pendingReviews' => ManagementReview::where('status', 'planned')->where('soft_delete', 0)->count(),
        ];

        $upcomingCalibrations = [];
        $equipments = Equipment::where('soft_delete', 0)
            ->where('calibration_required', 1)
            ->where('next_calibration_date', '<=', date('Y-m-d', strtotime('+30 days')))
            ->order('next_calibration_date', 'asc')
            ->limit(8)
            ->select();

        foreach ($equipments as $eq) {
            $site = $eq->site_id ? Site::find($eq->site_id) : null;
            $daysUntil = $eq->next_calibration_date
                ? (int) ((strtotime($eq->next_calibration_date) - time()) / 86400)
                : 999;
            $upcomingCalibrations[] = [
                'equipment_name' => $eq->name,
                'equipment_code' => $eq->equipment_number,
                'site_name' => $site ? $site->name : '未指定',
                'last_calibration' => $eq->last_calibration_date,
                'next_calibration' => $eq->next_calibration_date,
                'days_until' => $daysUntil,
            ];
        }

        $todos = [];
        if ($stats['pendingApprovals'] > 0) {
            $todos[] = ['title' => '待审批文件', 'count' => $stats['pendingApprovals'], 'url' => '/document/index?status=reviewing'];
        }
        if ($stats['overdueCapa'] > 0) {
            $todos[] = ['title' => '超期CAPA', 'count' => $stats['overdueCapa'], 'url' => '/capa/index'];
        }
        if ($stats['calibrationExpiring'] > 0) {
            $todos[] = ['title' => '校准即将到期', 'count' => $stats['calibrationExpiring'], 'url' => '/equipment/index?due=1'];
        }
        if ($stats['openComplaints'] > 0) {
            $todos[] = ['title' => '未关闭投诉', 'count' => $stats['openComplaints'], 'url' => '/complaint/index'];
        }
        if ($stats['overdueActions'] > 0) {
            $todos[] = ['title' => '逾期管评决议', 'count' => $stats['overdueActions'], 'url' => '/review_action/index'];
        }

        View::assign('stats', $stats);
        View::assign('upcomingCalibrations', $upcomingCalibrations);
        View::assign('todos', $todos);

        return View::fetch('dashboard/index');
    }
}
