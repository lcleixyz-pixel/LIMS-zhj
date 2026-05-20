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

        $controller = strtolower($controller);

        return in_array($controller, $allowed, true);
    }

    public static function canWrite(string $controller): bool
    {
        $role = Session::get('user.role', 'staff');
        if ($role === 'staff') {
            return in_array(strtolower($controller), ['complaint', 'document'], true);
        }

        return self::canAccess($controller);
    }
}
