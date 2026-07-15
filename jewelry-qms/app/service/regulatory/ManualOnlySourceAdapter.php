<?php
declare(strict_types=1);

namespace app\service\regulatory;

final class ManualOnlySourceAdapter implements RegulatorySourceAdapterInterface
{
    public function supports(string $mode): bool
    {
        return $mode === 'manual_only';
    }

    public function parse(string $body, array $source): array
    {
        return [
            'items' => [],
            'requires_manual_verification' => true,
            'message' => '该来源仅允许人工核验；不得自动请求、执行 JS 或尝试绕过验证码。',
        ];
    }
}
