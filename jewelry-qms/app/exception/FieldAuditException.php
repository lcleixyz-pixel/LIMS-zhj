<?php
declare(strict_types=1);

namespace app\exception;

final class FieldAuditException extends \RuntimeException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('系统未能保存完整变更记录，请联系管理员', 0, $previous);
    }
}
