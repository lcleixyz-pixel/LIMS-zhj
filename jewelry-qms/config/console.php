<?php
// +----------------------------------------------------------------------
// | 控制台配置
// +----------------------------------------------------------------------
use app\command\AiPurgeChat;
use app\command\CheckReminders;
use app\command\ComplianceAssess;
use app\command\CurrentFilesSeed;
use app\command\P0BuildControlledMigrationPackage;
use app\command\P0Preflight;
use app\command\QmsGovernedTrialAssemble;
use app\command\QmsGovernedTrialResolve;
use app\command\QmsFinalCandidateAssemble;
use app\command\QmsFinalCandidateSourceAssets;
use app\command\QmsFinalCandidateTraceSync;
use app\command\QmsQualityWorkbenchRefresh;
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
        P0BuildControlledMigrationPackage::class,
        P0Preflight::class,
        QmsGovernedTrialAssemble::class,
        QmsGovernedTrialResolve::class,
        QmsFinalCandidateAssemble::class,
        QmsFinalCandidateSourceAssets::class,
        QmsFinalCandidateTraceSync::class,
        QmsQualityWorkbenchRefresh::class,
        QmsManualProcedureAlignmentCheck::class,
        QmsPreimportPackage::class,
        RecordFormRebuildSchema::class,
        RecordFormReconstructionReview::class,
        RecordFormSeedSourceInstances::class,
    ],
];
