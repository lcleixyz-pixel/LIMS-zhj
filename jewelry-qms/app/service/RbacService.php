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
        if ($role === 'staff') {
            return in_array($controller, ['complaint', 'document'], true);
        }

        return self::canAccess($controller);
    }

    private static function normalizeController(string $controller): string
    {
        return strtolower(str_replace(['_', '\\', '/'], '', trim($controller)));
    }
}
