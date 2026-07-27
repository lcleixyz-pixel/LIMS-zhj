<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Db;

final class NotificationPresentationService
{
    public static function present(array $row): array
    {
        $type = trim((string)($row['type'] ?? 'general'));
        $controller = strtolower(str_replace(['_', '-', '\\'], '', (string)($row['link_controller'] ?? '')));
        $row['type_label'] = self::typeLabel($type);
        $row['status_label'] = (int)($row['status'] ?? 0) === 0 ? '尚未查看' : '已查看';
        $row['action_label'] = match ($controller) {
            'recordforminstance', 'governedchange' => '查看并处理',
            'document' => '查看文件',
            'equipment', 'equipmentperiodcheck' => '查看设备事项',
            'training', 'competencyrecord' => '查看人员事项',
            default => trim((string)($row['link_controller'] ?? '')) !== '' ? '查看详情' : '我知道了',
        };
        $row['object_label'] = self::objectLabel($controller, (string)($row['link_id'] ?? ''));

        return $row;
    }

    public static function typeLabel(string $type): string
    {
        return match (trim($type)) {
            'calibration' => '设备校准',
            'training' => '人员培训',
            'document' => '体系文件',
            'audit' => '审核与评审',
            default => '业务通知',
        };
    }

    private static function objectLabel(string $controller, string $id): string
    {
        if ($id === '') {
            return '';
        }

        try {
            if ($controller === 'recordforminstance') {
                $row = Db::name('record_form_instances')
                    ->field('doc_number,record_title')
                    ->where('id', $id)
                    ->find();
                if (is_array($row)) {
                    return self::joinLabel((string)($row['doc_number'] ?? ''), (string)($row['record_title'] ?? ''));
                }
            }
            if ($controller === 'document') {
                $row = Db::name('documents')
                    ->field('doc_number,title')
                    ->where('id', $id)
                    ->where('soft_delete', 0)
                    ->find();
                if (is_array($row)) {
                    return self::joinLabel((string)($row['doc_number'] ?? ''), (string)($row['title'] ?? ''));
                }
            }
            if ($controller === 'equipment') {
                $row = Db::name('equipments')
                    ->field('equipment_number,name')
                    ->where('id', $id)
                    ->where('soft_delete', 0)
                    ->find();
                if (is_array($row)) {
                    return self::joinLabel((string)($row['equipment_number'] ?? ''), (string)($row['name'] ?? ''));
                }
            }
        } catch (\Throwable $exception) {
            return '';
        }

        return '';
    }

    private static function joinLabel(string $number, string $title): string
    {
        $number = trim($number);
        $title = trim($title);
        if ($number === '') {
            return $title;
        }
        if ($title === '') {
            return $number;
        }

        return $number . ' · ' . $title;
    }
}
