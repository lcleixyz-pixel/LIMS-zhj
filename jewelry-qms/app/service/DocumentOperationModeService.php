<?php
declare(strict_types=1);

namespace app\service;

use DomainException;
use think\facade\Config;

final class DocumentOperationModeService
{
    public const PAPER_GOVERNANCE = 'paper_governance';
    public const ELECTRONIC_CONTROLLED = 'electronic_controlled';

    public static function current(): string
    {
        $mode = trim((string)Config::get('qms.document_operation_mode', self::PAPER_GOVERNANCE));
        if (!in_array($mode, [self::PAPER_GOVERNANCE, self::ELECTRONIC_CONTROLLED], true)) {
            throw new DomainException('文件运行阶段配置无效');
        }
        if ($mode === self::ELECTRONIC_CONTROLLED
            && trim((string)getenv('QMS_ELECTRONIC_CONTROLLED_ACK')) !== 'APPROVED_AFTER_VALIDATION'
        ) {
            throw new DomainException('电子受控阶段尚未完成独立验证和批准，拒绝启用');
        }

        return $mode;
    }

    public static function presentation(): array
    {
        $mode = self::current();
        if ($mode === self::ELECTRONIC_CONTROLLED) {
            return [
                'mode' => $mode,
                'label' => '系统现行 · 电子受控',
                'notice' => '本页为系统发布的现行版本。来源文件仅用于版本追溯。',
                'is_electronic_controlled' => true,
            ];
        }

        return [
            'mode' => self::PAPER_GOVERNANCE,
            'label' => '治理中 · 纸质执行',
            'notice' => '本页用于治理、检索和链路核对，不替代纸质受控文件。',
            'is_electronic_controlled' => false,
        ];
    }
}
