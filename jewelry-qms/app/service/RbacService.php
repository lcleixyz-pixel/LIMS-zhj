<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Config;
use think\facade\Session;

class RbacService
{
    public static function isRestrictedRecordOperator(): bool
    {
        return ActionAuthorizationService::isRestrictedRecordOperator();
    }

    public static function canAccess(string $controller): bool
    {
        if (self::isRestrictedRecordOperator()) {
            $allowed = (array)Config::get('qms.position_permissions.record_operator', []);
            if (!ActionAuthorizationService::canRecordOperatorFill()) {
                $allowed = array_values(array_diff($allowed, ['record_form_template']));
            }

            return self::controllerIn($controller, $allowed);
        }

        $role = Session::get('user.role', 'staff');
        $permissions = Config::get('qms.permissions', []);

        if (!isset($permissions[$role])) {
            return false;
        }

        $allowed = $permissions[$role];
        if (in_array('*', $allowed, true)) {
            return true;
        }

        return self::controllerIn($controller, $allowed);
    }

    public static function canWrite(string $controller): bool
    {
        if (self::isRestrictedRecordOperator()) {
            return ActionAuthorizationService::canRecordOperatorFill()
                && self::controllerIn($controller, ['record_form_instance']);
        }

        $role = Session::get('user.role', 'staff');
        // 体系文件类写操作（新建/编辑/删除文件）：仅 admin 和 quality_manager（2026-07-11 收紧）
        $documentWriteControllers = ['document', 'doc_category', 'doc_template'];
        if (self::controllerIn($controller, $documentWriteControllers)) {
            return in_array($role, ['admin', 'quality_manager'], true);
        }

        if ($role === 'staff') {
            // staff 可写：投诉、填写记录（日常行为，非"管理"）；体系文件另由上面规则拦
            return self::controllerIn($controller, ['complaint', 'record_form_instance']);
        }

        return self::canAccess($controller);
    }

    private static function controllerIn(string $controller, array $controllers): bool
    {
        $controller = self::normalizeController($controller);
        foreach ($controllers as $candidate) {
            if (self::normalizeController((string)$candidate) === $controller) {
                return true;
            }
        }

        return false;
    }

    private static function normalizeController(string $controller): string
    {
        return strtolower(str_replace(['_', '\\', '/'], '', trim($controller)));
    }
}
