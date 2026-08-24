<?php
declare(strict_types=1);

namespace app\service;

final class NavigationPresentationService
{
    private const NOTICE = '纸质文件为正式依据 · 系统用于快速查阅与治理核对';

    private const DAILY_DOCUMENT_ACTIONS = [
        'index',
        'read',
        'candidate_preview',
        'source_download',
    ];

    private const DAILY_RECORD_ACTIONS = [
        'index',
        'create',
        'edit',
        'view',
        'print',
        'download_current_package',
        'download_current_pdf',
        'export_pdf',
        'download_pdf',
        'download_preview_pdf',
    ];

    public static function context(
        string $controller,
        string $action,
        bool $canGovern,
        array $parameters = []
    ): array {
        $controller = self::snake($controller);
        $action = self::snake($action);
        $layer = self::isDailyRoute($controller, $action, $parameters, $canGovern)
            ? 'daily'
            : 'governance';

        return [
            'layer' => $layer,
            'active' => self::activeArea($controller, $layer),
            'can_govern' => $canGovern,
            'notice' => self::NOTICE,
        ];
    }

    private static function isDailyRoute(
        string $controller,
        string $action,
        array $parameters,
        bool $canGovern
    ): bool {
        if (!$canGovern) {
            return true;
        }
        if ($controller === 'quality_workbench') {
            return $action === 'index';
        }
        if ($controller === 'document') {
            if ($action === 'index'
                && ((string)($parameters['history'] ?? '') === '1'
                    || (string)($parameters['pending_for_me'] ?? '') === '1')
            ) {
                return false;
            }

            return in_array($action, self::DAILY_DOCUMENT_ACTIONS, true);
        }
        if ($controller === 'record_form_instance') {
            return in_array($action, self::DAILY_RECORD_ACTIONS, true);
        }

        return false;
    }

    private static function activeArea(string $controller, string $layer): string
    {
        if ($layer === 'governance') {
            return 'governance';
        }

        return match ($controller) {
            'document' => 'documents',
            'record_form_instance' => 'records',
            default => 'work',
        };
    }

    private static function snake(string $value): string
    {
        $value = basename(str_replace('\\', '/', trim($value)));
        $value = preg_replace('/(?<!^)[A-Z]/', '_$0', $value) ?? $value;

        return strtolower(str_replace('-', '_', $value));
    }
}
