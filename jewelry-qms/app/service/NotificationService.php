<?php
declare(strict_types=1);

namespace app\service;

use app\model\Capa;
use app\model\CompetencyRecord;
use app\model\Document;
use app\model\Equipment;
use app\model\Notification;
use app\model\User;
use think\facade\Config;
use think\facade\Db;
use think\facade\Log;

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
        ?string $dueDate = null,
        ?string $notificationKey = null
    ): void {
        $companyId = Config::get('qms.company_id');
        $keySupported = self::notificationKeySupported();
        $notificationKey = $notificationKey !== null && trim($notificationKey) !== ''
            ? trim($notificationKey)
            : null;
        if (!$keySupported) {
            $notificationKey = null;
        }

        $notification = null;
        if ($keySupported && $notificationKey !== null) {
            $notification = Notification::where('company_id', $companyId)
                ->where('notification_key', $notificationKey)
                ->where('soft_delete', 0)
                ->find();
        }

        if ($notification === null) {
            try {
                $payload = [
                    'id' => qms_uuid(),
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
                ];
                if ($keySupported) {
                    $payload['notification_key'] = $notificationKey;
                }
                $notification = Notification::create($payload);
            } catch (\Throwable $exception) {
                if ($notificationKey === null || !self::isDuplicateKeyError($exception)) {
                    throw $exception;
                }

                $notification = Notification::where('company_id', $companyId)
                    ->where('notification_key', $notificationKey)
                    ->where('soft_delete', 0)
                    ->find();
                if ($notification === null) {
                    throw $exception;
                }
            }
        }

        self::attachRecipients((string)$notification->id, $userIds);
    }

    public static function notifyRole(
        string $role,
        string $title,
        string $message,
        string $type,
        ?string $ctrl = null,
        ?string $linkId = null,
        ?string $dueDate = null,
        ?string $notificationKey = null
    ): void {
        self::notifyUsers(
            $title,
            $message,
            $type,
            self::roleUserIds($role),
            $ctrl,
            'index',
            $linkId,
            $dueDate,
            $notificationKey
        );
    }

    public static function checkCalibrationDue(): int
    {
        $days = (int) Config::get('qms.notification.calibration_days', 30);
        $dueDate = date('Y-m-d', strtotime("+{$days} days"));
        $items = Equipment::where('soft_delete', 0)
            ->where('calibration_required', 1)
            ->whereNotNull('next_calibration_date')
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
                $eq->id,
                $eq->next_calibration_date,
                'calibration_due:' . $eq->id . ':' . self::monthPeriod((string)$eq->next_calibration_date)
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
                    $capa->due_date,
                    'capa_overdue:' . $capa->id . ':' . self::weekPeriod(date('Y-m-d'))
                );
            }
            $count++;
        }

        return $count;
    }

    public static function checkDocumentReviewDue(): int
    {
        $days = (int) Config::get('qms.notification.document_review_days', 30);
        $dueDate = date('Y-m-d', strtotime("+{$days} days"));
        $items = Document::where('soft_delete', 0)
            ->where('status', '<>', 'obsolete')
            ->whereNotNull('review_date')
            ->where('review_date', '<=', $dueDate)
            ->select();

        $count = 0;
        $userIds = self::roleUserIds('quality_manager');
        foreach ($items as $document) {
            self::notifyUsers(
                '文件评审到期提醒',
                "文件 {$document->doc_number} {$document->title} 将于 {$document->review_date} 到期评审",
                'document',
                $userIds,
                'document',
                'view',
                $document->id,
                $document->review_date,
                'doc_review_due:' . $document->id . ':' . self::monthPeriod((string)$document->review_date)
            );
            $count++;
        }

        return $count;
    }

    public static function checkCompetencyDue(): int
    {
        $days = (int) Config::get('qms.notification.competency_days', 30);
        $dueDate = date('Y-m-d', strtotime("+{$days} days"));
        $items = CompetencyRecord::where('soft_delete', 0)
            ->whereNotNull('valid_until')
            ->where('valid_until', '<=', $dueDate)
            ->select();

        $count = 0;
        $userIds = self::roleUserIds('quality_manager');
        foreach ($items as $record) {
            self::notifyUsers(
                '能力确认到期提醒',
                "能力确认记录 {$record->test_item} 将于 {$record->valid_until} 到期",
                'training',
                $userIds,
                'competency_record',
                'view',
                $record->id,
                $record->valid_until,
                'competency_due:' . $record->id . ':' . self::monthPeriod((string)$record->valid_until)
            );
            $count++;
        }

        return $count;
    }

    public static function runReminderChecks(string $type = 'all'): array
    {
        $checks = [
            'calibration' => fn (): int => self::checkCalibrationDue(),
            'capa' => fn (): int => self::checkCapaOverdue(),
            'doc_review' => fn (): int => self::checkDocumentReviewDue(),
            'competency' => fn (): int => self::checkCompetencyDue(),
        ];

        if ($type !== 'all' && !isset($checks[$type])) {
            throw new \InvalidArgumentException('Unsupported reminder type: ' . $type);
        }

        $selected = $type === 'all' ? $checks : [$type => $checks[$type]];
        $summary = [];
        foreach ($selected as $name => $callback) {
            try {
                $summary[$name] = $callback();
            } catch (\Throwable $exception) {
                Log::error('[QMS Reminder] ' . $name . ' failed: ' . $exception->getMessage());
                $summary[$name] = 0;
                $summary[$name . '_error'] = $exception->getMessage();
            }
        }

        return $summary;
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

    protected static function attachRecipients(string $notificationId, array $userIds): void
    {
        foreach (array_unique($userIds) as $userId) {
            if (!$userId) {
                continue;
            }

            Db::execute(
                'INSERT IGNORE INTO `notification_users` (`id`, `notification_id`, `user_id`, `status`) VALUES (?, ?, ?, 0)',
                [qms_uuid(), $notificationId, $userId]
            );
        }
    }

    protected static function roleUserIds(string $role): array
    {
        return User::where('role', $role)->where('publish', 1)->where('soft_delete', 0)->column('id');
    }

    protected static function monthPeriod(string $date): string
    {
        $timestamp = strtotime($date) ?: time();
        return date('Y-m', $timestamp);
    }

    protected static function weekPeriod(string $date): string
    {
        $timestamp = strtotime($date) ?: time();
        return date('o-\WW', $timestamp);
    }

    protected static function isDuplicateKeyError(\Throwable $exception): bool
    {
        $message = $exception->getMessage();
        return str_contains($message, 'Duplicate entry') || str_contains($message, '1062');
    }

    protected static function notificationKeySupported(): bool
    {
        static $supported = null;
        if ($supported === null) {
            $supported = (new Notification())->hasColumn('notification_key');
        }

        return $supported;
    }
}
