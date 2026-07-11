<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\Notification;
use think\facade\View;

class Calendar extends BaseController
{
    public function index()
    {
        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');
        $today = date('Y-m-d');

        $notifications = Notification::where('soft_delete', 0)
            ->where('publish', 1)
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$monthStart, $monthEnd])
            ->order('due_date', 'asc')
            ->select();

        $items = [];
        foreach ($notifications as $n) {
            $url = '#';
            if (!empty($n->link_controller)) {
                $url = '/' . qms_controller_url($n->link_controller) . '/' . ($n->link_action ?: 'index');
                if (!empty($n->link_id)) {
                    $url .= '?id=' . $n->link_id;
                }
            }

            $isOverdue = $n->due_date < $today;
            $daysLeft = (int) ((strtotime((string)$n->due_date) - strtotime($today)) / 86400);

            $items[] = [
                'id' => $n->id,
                'title' => $n->title ?: '',
                'message' => $n->message ?: '',
                'type' => $n->type ?: '',
                'due_date' => $n->due_date,
                'is_overdue' => $isOverdue,
                'days_left' => $daysLeft,
                'url' => $url,
            ];
        }

        View::assign('items', $items);
        View::assign('monthStart', $monthStart);
        View::assign('monthEnd', $monthEnd);
        View::assign('monthLabel', date('Y年n月'));
        View::assign('today', $today);
        View::assign('pageTitle', '本月体系待办');

        return View::fetch('calendar/index');
    }
}
