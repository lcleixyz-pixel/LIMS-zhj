<?php
declare(strict_types=1);

namespace app\service;

use RuntimeException;

class RecordFormPrintService
{
    public static function render(string $templateKey, array $template, array $values): string
    {
        $safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '', $templateKey);
        $root = function_exists('root_path') ? \root_path() : dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;
        $path = $root . 'app' . DIRECTORY_SEPARATOR . 'record_form_print' . DIRECTORY_SEPARATOR . $safeKey . '.php';
        if (!is_file($path)) {
            throw new RuntimeException('打印模板不存在：' . $safeKey);
        }

        ob_start();
        include $path;

        return (string)ob_get_clean();
    }

    public static function value(array $values, string $key, string $default = ''): string
    {
        $value = $values[$key] ?? $default;
        if (is_array($value)) {
            return $default;
        }

        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }

    public static function rows(array $values, string $key): array
    {
        $rows = $values[$key] ?? [];

        return is_array($rows) ? array_values($rows) : [];
    }

    public static function cell(array $row, string $key): string
    {
        return htmlspecialchars((string)($row[$key] ?? ''), ENT_QUOTES, 'UTF-8');
    }
}
