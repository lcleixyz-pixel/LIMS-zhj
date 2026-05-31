<?php
// +----------------------------------------------------------------------
// | 控制台配置
// +----------------------------------------------------------------------
use app\command\AiPurgeChat;
use app\command\CheckReminders;
use app\command\ComplianceAssess;
use app\command\CurrentFilesSeed;

return [
    // 指令定义
    'commands' => [
        AiPurgeChat::class,
        CheckReminders::class,
        ComplianceAssess::class,
        CurrentFilesSeed::class,
    ],
];
