<?php

use think\facade\Config;

if (!function_exists('qms_uuid')) {
    function qms_uuid(): string
    {
        if (function_exists('uuid_create')) {
            return uuid_create(UUID_TYPE_RANDOM);
        }
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return sprintf(
            '%08s-%04s-%04s-%04s-%012s',
            bin2hex(substr($data, 0, 4)),
            bin2hex(substr($data, 4, 2)),
            bin2hex(substr($data, 6, 2)),
            bin2hex(substr($data, 8, 2)),
            bin2hex(substr($data, 10, 6))
        );
    }
}

if (!function_exists('qms_controller_url')) {
    function qms_controller_url(string $controllerName): string
    {
        return strtolower(trim(preg_replace('/(?<!^)[A-Z]/', '_$0', $controllerName), '_'));
    }
}

if (!function_exists('qms_status_label')) {
    function qms_status_label(string $module, string $status): string
    {
        $labels = Config::get('qms.statusLabels.' . $module, []);

        return $labels[$status] ?? $status;
    }
}

if (!function_exists('qms_next_number')) {
    function qms_next_number(string $prefix, string $modelClass, string $field = 'capa_number'): string
    {
        $year = date('Y');
        $pattern = $prefix . $year;
        $last = $modelClass::where($field, 'like', $pattern . '%')
            ->order($field, 'desc')
            ->value($field);
        $seq = 1;
        if ($last && preg_match('/(\d+)$/', (string) $last, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return $pattern . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('qms_can')) {
    function qms_can(string $controller): bool
    {
        return \app\service\RbacService::canAccess($controller);
    }
}

if (!function_exists('qms_json')) {
    function qms_json(array $payload)
    {
        $payload['csrf_token'] = token();

        return json($payload);
    }
}
