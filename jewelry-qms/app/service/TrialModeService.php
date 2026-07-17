<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Config;

final class TrialModeService
{
    public static function isEnabled(): bool
    {
        return filter_var(Config::get('qms.trial_mode.enabled', false), FILTER_VALIDATE_BOOL);
    }

    public static function trialBatch(): string
    {
        $batch = trim((string)Config::get('qms.trial_mode.batch', ''));

        return $batch !== '' ? $batch : 'GR14-' . date('Ym');
    }

    public static function isTemplateUsable(object|array $template): bool
    {
        $status = self::value($template, 'status');
        if ($status === 'published') {
            return true;
        }

        return self::isEnabled() && $status === 'trial_ready';
    }

    public static function isSimulationTemplate(object|array $template): bool
    {
        return self::value($template, 'status') === 'trial_ready';
    }

    public static function simulationNumber(string $value): string
    {
        $value = trim($value);
        if (str_starts_with(strtoupper($value), 'SIM-')) {
            return $value;
        }

        return 'SIM-' . ltrim($value, '-');
    }

    public static function watermarkHtml(string $html, bool $isSimulation): string
    {
        if (!$isSimulation) {
            return $html;
        }

        $watermark = '<div class="qms-trial-watermark" style="position:fixed;z-index:9999;'
            . 'top:12px;right:18px;padding:7px 12px;border:2px solid #b45309;color:#92400e;'
            . 'background:#fffbeb;font-weight:700;">试运行/非正式受控副本</div>';
        if (stripos($html, '<body') !== false) {
            return (string)preg_replace('/(<body\b[^>]*>)/i', '$1' . $watermark, $html, 1);
        }

        return $watermark . $html;
    }

    /**
     * @return list<string>
     */
    public static function readinessErrors(object|array $template): array
    {
        $errors = [];
        if (trim(self::value($template, 'field_schema')) === '' || trim(self::value($template, 'field_schema')) === '[]') {
            $errors[] = '字段契约为空';
        }
        if (trim(self::value($template, 'procedure_doc_id')) === '') {
            $errors[] = '未关联来源程序';
        }
        foreach ([
            'applicable_sites' => '适用场所',
            'responsible_position_code' => '责任岗位',
            'retention_period' => '保存期限',
            'version' => '版本',
            'print_template_key' => '打印模板',
        ] as $field => $label) {
            if (trim(self::value($template, $field)) === '') {
                $errors[] = $label . '未填写';
            }
        }

        return $errors;
    }

    private static function value(object|array $record, string $field): string
    {
        if (is_array($record)) {
            return (string)($record[$field] ?? '');
        }

        return (string)($record->{$field} ?? '');
    }
}
