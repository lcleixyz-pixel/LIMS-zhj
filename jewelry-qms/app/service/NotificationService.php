<?php
declare(strict_types=1);

namespace app\service;

use app\model\Capa;
use app\model\Equipment;
use app\model\Notification;
use app\model\NotificationUser;
use app\model\User;
use think\facade\Config;

class NotificationService
{
    public static function notifyUsers(
        string $title,
        string $message,
        string $type,
        array $userIds,
        ?string $linkController = null,
        ?string $linkAction = 'index',
        ?string $linkId = null,
        ?string $dueDate = null
    ): void {
        $companyId = Config::get('qms.company_id');
        $notificationId = qms_uuid();

        Notification::create([
            'id' => $notificationId,
            'company_id' => $companyId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'link_controller' => $linkController,
            'link_action' => $linkAction,
            'link_id' => $linkId,
            'due_date' => $dueDate,
            'publish' => 1,
            'soft_delete' => 0,
            'created' => date('Y-m-d H:i:s'),
        ]);

        foreach (array_unique($userIds) as $userId) {
            if (!$userId) {
                continue;
            }
            NotificationUser::create([
                'id' => qms_uuid(),
                'notification_id' => $notificationId,
                'user_id' => $userId,
                'status' => 0,
            ]);
        }
    }

    public static function notifyRole(string $role, string $title, string $message, string $type, ?string $ctrl = null, ?string $linkId = null): void
    {
        $userIds = User::where('role', $role)->where('publish', 1)->where('soft_delete', 0)->column('id');
        self::notifyUsers($title, $message, $type, $userIds, $ctrl, 'index', $linkId);
    }

    public static function checkCalibrationDue(): int
    {
        $days = (int) Config::get('qms.notification.calibration_days', 30);
        $dueDate = date('Y-m-d', strtotime("+{$days} days"));
        $items = Equipment::where('soft_delete', 0)
            ->where('calibration_required', 1)
            ->where('next_calibration_date', '<=', $dueDate)
            ->select();

        $count = 0;
        foreach ($items as $eq) {
            self::notifyRole(
                'quality_manager',
                '校准到期提醒',
                "设备 {$eq->equipment_number} {$eq->name} 将于 {$eq->next_calibration_date} 到期校准",
                'calibration',
                'equipment',
                $eq->id
            );
            $count++;
        }

        return $count;
    }

    public static function checkCapaOverdue(): int
    {
        $items = Capa::where('soft_delete', 0)
            ->where('status', '<>', 'closed')
            ->where('due_date', '<', date('Y-m-d'))
            ->whereNotNull('due_date')
            ->select();

        $count = 0;
        foreach ($items as $capa) {
            if ($capa->assigned_to) {
                self::notifyUsers(
                    'CAPA超期提醒',
                    "CAPA {$capa->capa_number} 已超过计划完成日期 {$capa->due_date}",
                    'general',
                    [$capa->assigned_to],
                    'capa',
                    'view',
                    $capa->id,
                    $capa->due_date
                );
            }
            $count++;
        }

        return $count;
    }

    public static function notifyApprovalPending(string $userId, string $docTitle, string $recordId): void
    {
        self::notifyUsers(
            '文件待审批',
            "文件「{$docTitle}」等待您审批",
            'document',
            [$userId],
            'document',
            'view',
            $recordId
        );
    }
}
