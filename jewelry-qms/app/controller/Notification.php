<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\Notification as NotificationModel;
use app\model\NotificationUser;
use think\facade\Session;
use think\facade\View;

class Notification extends BaseController
{
    public function index()
    {
        $userId = Session::get('user.id');
        $nuList = NotificationUser::where('user_id', $userId)
            ->order('status', 'asc')
            ->order('id', 'desc')
            ->paginate(20);

        $list = [];
        foreach ($nuList as $nu) {
            $n = NotificationModel::find($nu->notification_id);
            $list[] = [
                'id' => $nu->id,
                'status' => $nu->status,
                'title' => $n->title ?? '',
                'message' => $n->message ?? '',
                'type' => $n->type ?? '',
                'created' => $n->created ?? '',
                'link_controller' => $n->link_controller ?? null,
                'link_action' => $n->link_action ?? null,
                'link_id' => $n->link_id ?? null,
            ];
        }

        View::assign('items', $list);
        View::assign('pages', $nuList->render());
        View::assign('pageTitle', '通知中心');

        return View::fetch('notification/index');
    }

    public function read()
    {
        $id = $this->request->param('id');
        $userId = Session::get('user.id');
        $nu = NotificationUser::where('id', $id)->where('user_id', $userId)->find();
        if ($nu) {
            $nu->status = 1;
            $nu->read_at = date('Y-m-d H:i:s');
            $nu->save();
            $notification = NotificationModel::find($nu->notification_id);
            if ($notification && $notification->link_controller) {
                $url = '/' . qms_controller_url($notification->link_controller) . '/' . ($notification->link_action ?: 'index');
                if ($notification->link_id) {
                    $url .= '?id=' . $notification->link_id;
                }

                return redirect($url);
            }
        }

        return redirect('/notification/index');
    }

    public function markAllRead()
    {
        NotificationUser::where('user_id', Session::get('user.id'))
            ->where('status', 0)
            ->update(['status' => 1, 'read_at' => date('Y-m-d H:i:s')]);
        Session::flash('success', '已全部标为已读');

        return redirect('/notification/index');
    }
}
