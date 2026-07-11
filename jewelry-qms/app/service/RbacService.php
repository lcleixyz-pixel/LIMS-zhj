<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Config;
use think\facade\Session;

class RbacService
{
    public static function canAccess(string $controller): bool
    {
        $role = Session::get('user.role', 'staff');
        $permissions = Config::get('qms.permissions', []);

        if (!isset($permissions[$role])) {
            return false;
        }

        $allowed = $permissions[$role];
        if (in_array('*', $allowed, true)) {
            return true;
        }

        $controller = self::normalizeController($controller);
        foreach ($allowed as $item) {
            if (self::normalizeController((string)$item) === $controller) {
                return true;
            }
        }

        return false;
    }

    public static function canWrite(string $controller): bool
    {
        $role = Session::get('user.role', 'staff');
        $controller = self::normalizeController($controller);

        // 体系文件类写操作（新建/编辑/删除文件）：仅 admin 和 quality_manager（2026-07-11 收紧）
        // 名单项归一化（与 canAccess 一致），否则 doc_category/record_form_instance 等带下划线永不匹配
        $documentWriteControllers = array_map([self::class, 'normalizeController'], ['document', 'doc_category', 'doc_template']);
        if (in_array($controller, $documentWriteControllers, true)) {
            return in_array($role, ['admin', 'quality_manager'], true);
        }

        if ($role === 'staff') {
            // staff 可写：投诉、填写记录（日常行为，非"管理"）；体系文件另由上面规则拦
            $staffWrite = array_map([self::class, 'normalizeController'], ['complaint', 'record_form_instance']);
            return in_array($controller, $staffWrite, true);
        }

        return self::canAccess($controller);
    }

    private static function normalizeController(string $controller): string
    {
        return strtolower(str_replace(['_', '\\', '/'], '', trim($controller)));
    }
}
