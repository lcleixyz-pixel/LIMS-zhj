<?php

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
