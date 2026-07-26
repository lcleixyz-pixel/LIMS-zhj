<?php
declare(strict_types=1);

namespace app\service;

use RuntimeException;

class RecordFormPrintService
{
    public static function render(string $templateKey, array $template, array $values): string
    {
        if ($templateKey === '' || preg_match('/\A[a-zA-Z0-9_-]+\z/', $templateKey) !== 1) {
            throw new RuntimeException('非法打印模板标识：' . ($templateKey === '' ? '空' : $templateKey));
        }

        $root = function_exists('root_path') ? \root_path() : dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;
        $path = $root . 'app' . DIRECTORY_SEPARATOR . 'record_form_print' . DIRECTORY_SEPARATOR . $templateKey . '.php';
        if (!is_file($path)) {
            throw new RuntimeException('打印模板不存在：' . $templateKey);
        }

        $template = self::normalizeTemplate($template);
        $bufferLevel = ob_get_level();
        ob_start();
        try {
            include $path;

            return (string)ob_get_clean();
        } catch (\Throwable $exception) {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }

            throw $exception;
        }
    }

    private static function normalizeTemplate(array $template): array
    {
        $schema = $template['field_schema'] ?? [];
        if (is_string($schema)) {
            $schema = trim($schema);
            if ($schema === '') {
                $template['field_schema'] = [];

                return $template;
            }

            $decoded = json_decode($schema, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException('打印模板字段配置错误：' . json_last_error_msg());
            }
            if (!is_array($decoded)) {
                throw new RuntimeException('打印模板字段配置错误：字段配置根节点必须是数组');
            }

            $template['field_schema'] = $decoded;
        }

        return $template;
    }

    public static function tablePaginationCss(): string
    {
        return <<<'CSS'
        table { page-break-inside: auto; }
        thead { display: table-header-group; }
        tr, th, td {
            break-inside: avoid;
            page-break-inside: avoid;
        }
CSS;
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

        if (!is_array($rows)) {
            return [];
        }

        return array_values(array_filter($rows, static fn ($row): bool => is_array($row)));
    }

    public static function cell(array $row, string $key): string
    {
        $value = $row[$key] ?? '';
        if (is_array($value)) {
            return '';
        }

        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }

    public static function displayDocNumber(array $template, array $values): string
    {
        $docNumber = (string)($template['doc_number'] ?? '');
        $usageSite = trim((string)($values['usage_site'] ?? ''));

        if ($usageSite === 'hetian' && preg_match('/\AXZTC\/BG-(\d{2}-\d{2})\z/', $docNumber, $matches) === 1) {
            return 'XZTCH-BG-' . $matches[1];
        }

        return $docNumber;
    }
}
