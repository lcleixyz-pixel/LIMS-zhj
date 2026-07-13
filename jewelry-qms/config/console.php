<?php
// +----------------------------------------------------------------------
// | 控制台配置
// +----------------------------------------------------------------------
use app\command\AiPurgeChat;
use app\command\CheckReminders;
use app\command\ComplianceAssess;
use app\command\CurrentFilesSeed;
use app\command\QmsClauseRemediate;
use app\command\QmsManualProcedureAlignmentCheck;
use app\command\QmsPreimportPackage;
use app\command\RecordFormRebuildSchema;
use app\command\RecordFormReconstructionReview;
use app\command\RecordFormSeedSourceInstances;

return [
    // 指令定义
    'commands' => [
        AiPurgeChat::class,
        CheckReminders::class,
        ComplianceAssess::class,
        CurrentFilesSeed::class,
        QmsClauseRemediate::class,
        QmsManualProcedureAlignmentCheck::class,
        QmsPreimportPackage::class,
        RecordFormRebuildSchema::class,
        RecordFormReconstructionReview::class,
        RecordFormSeedSourceInstances::class,
    ],
];
