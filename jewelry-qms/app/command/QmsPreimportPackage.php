<?php
declare(strict_types=1);

namespace app\command;

use app\service\QmsPreimportPackageService;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

class QmsPreimportPackage extends Command
{
    protected function configure(): void
    {
        $this->setName('qms:preimport-package')
            ->setDescription('校验或写入 QMS 候选文件和记录模板预导入包')
            ->addOption('package-dir', null, Option::VALUE_REQUIRED, 'lims_preimport_package 目录')
            ->addOption('review-dir', null, Option::VALUE_REQUIRED, 'human_review_pack 目录；apply 前用于确认人工评审是否全部通过')
            ->addOption('field-catalog-dir', null, Option::VALUE_REQUIRED, 'record_template_field_catalog 目录；用于校验记录模板字段字典是否与预导入 schema 一致')
            ->addOption('release-plan-dir', null, Option::VALUE_REQUIRED, 'controlled_release_rehearsal 目录；用于校验受控发布、培训和旧版处置演练包')
            ->addOption('release-execution-dir', null, Option::VALUE_REQUIRED, 'release_execution_template_pack 目录；用于校验发布执行记录候选模板包')
            ->addOption('manual-revision-dir', null, Option::VALUE_REQUIRED, 'manual_revision_path_pack 目录；用于校验质量手册既有文件修订/换版路径')
            ->addOption('staff-training-dir', null, Option::VALUE_REQUIRED, 'staff_training_implementation_pack 目录；用于校验机构人员学习实施包')
            ->addOption('stage2-review-dir', null, Option::VALUE_REQUIRED, 'stage2_structured_review_workbench 目录；用于校验第二阶段结构化导入人工复核工作台')
            ->addOption('stage2-review-preview-dir', null, Option::VALUE_REQUIRED, 'stage2_structured_review_decision_preview 目录；用于校验第二阶段复核意见回填预览包')
            ->addOption('governance-readiness-dir', null, Option::VALUE_REQUIRED, 'governance_readiness_dashboard 目录；用于汇总校验全量治理闸门和阻断任务')
            ->addOption('governance-closure-dir', null, Option::VALUE_REQUIRED, 'governance_closure_workbench 目录；用于校验治理关闭证据和拟关闭回填状态')
            ->addOption('governance-closure-execution-dir', null, Option::VALUE_REQUIRED, 'governance_closure_execution_pack 目录；用于校验治理关闭闭环执行批次和签核状态')
            ->addOption('governance-closure-pilot-dir', null, Option::VALUE_REQUIRED, 'governance_closure_pilot_pack 目录；用于校验治理关闭最小试点批次和证据填写状态')
            ->addOption('governance-closure-pilot-return-dir', null, Option::VALUE_REQUIRED, 'governance_closure_pilot_return_preview 目录；用于校验试点结果回填到治理关闭工作台前的预览状态')
            ->addOption('governance-closure-pilot-source-update-dir', null, Option::VALUE_REQUIRED, 'governance_closure_pilot_source_update_rehearsal 目录；用于校验试点结果回填源工作台前的逐字段补丁预演状态')
            ->addOption('governance-closure-pilot-operator-workbook-dir', null, Option::VALUE_REQUIRED, 'governance_closure_pilot_operator_workbook 目录；用于校验最小试点人工执行工作簿状态')
            ->addOption('governance-closure-pilot-operator-handback-dir', null, Option::VALUE_REQUIRED, 'governance_closure_pilot_operator_handback 目录；用于校验最小试点真实执行交回状态')
            ->addOption('governance-closure-pilot-operator-completion-simulation-dir', null, Option::VALUE_REQUIRED, 'governance_closure_pilot_operator_completion_simulation 目录；用于校验最小试点人工执行模拟完成包状态')
            ->addOption('governance-closure-preview-dir', null, Option::VALUE_REQUIRED, 'governance_closure_decision_preview 目录；用于校验治理关闭意见回填预览结果')
            ->addOption('governance-readiness-refresh-dir', null, Option::VALUE_REQUIRED, 'governance_readiness_refresh_preview 目录；用于校验治理就绪刷新预览结果')
            ->addOption('stage2-check', null, Option::VALUE_NONE, '额外检查结构化文件、手册块和追溯关系的第二阶段导入准备度')
            ->addOption('apply', null, Option::VALUE_NONE, '正式写入候选文件、候选记录模板和外来依据')
            ->addOption('apply-rehearsal', null, Option::VALUE_NONE, '模拟 apply 完整闸门但不写数据库；可用于人审通过模拟包验证')
            ->addOption('ack-human-reviewed', null, Option::VALUE_NONE, '确认已经完成人工评审后允许 apply')
            ->addOption('write-preview-dir', null, Option::VALUE_REQUIRED, '输出 LIMS 第一阶段写库行级预览包目录；只允许 dry-run 或 apply-rehearsal')
            ->addOption('stage2-preview-dir', null, Option::VALUE_REQUIRED, '输出 LIMS 第二阶段结构化导入行级预览包目录；只允许 dry-run 或 apply-rehearsal')
            ->addOption('json-out', null, Option::VALUE_REQUIRED, '输出 JSON 报告路径')
            ->addOption('md-out', null, Option::VALUE_REQUIRED, '输出 Markdown 报告路径');
    }

    protected function execute(Input $input, Output $output): int
    {
        $packageDir = (string)$input->getOption('package-dir');
        if ($packageDir === '') {
            $output->writeln('<error>必须提供 --package-dir。</error>');
            return 1;
        }

        $apply = (bool)$input->getOption('apply');
        $applyRehearsal = (bool)$input->getOption('apply-rehearsal');
        if ($apply && $applyRehearsal) {
            $output->writeln('<error>--apply 与 --apply-rehearsal 不能同时使用。</error>');
            return 1;
        }
        $writePreviewDir = $input->getOption('write-preview-dir') ? (string)$input->getOption('write-preview-dir') : null;
        if ($apply && $writePreviewDir) {
            $output->writeln('<error>--write-preview-dir 只允许 dry-run 或 --apply-rehearsal 使用，不允许与正式 --apply 同时使用。</error>');
            return 1;
        }
        $stage2PreviewDir = $input->getOption('stage2-preview-dir') ? (string)$input->getOption('stage2-preview-dir') : null;
        if ($apply && $stage2PreviewDir) {
            $output->writeln('<error>--stage2-preview-dir 只允许 dry-run 或 --apply-rehearsal 使用，不允许与正式 --apply 同时使用。</error>');
            return 1;
        }
        $ackHumanReviewed = (bool)$input->getOption('ack-human-reviewed');
        $reviewDir = $input->getOption('review-dir') ? (string)$input->getOption('review-dir') : null;
        $fieldCatalogDir = $input->getOption('field-catalog-dir') ? (string)$input->getOption('field-catalog-dir') : null;
        $releasePlanDir = $input->getOption('release-plan-dir') ? (string)$input->getOption('release-plan-dir') : null;
        $releaseExecutionDir = $input->getOption('release-execution-dir') ? (string)$input->getOption('release-execution-dir') : null;
        $manualRevisionDir = $input->getOption('manual-revision-dir') ? (string)$input->getOption('manual-revision-dir') : null;
        $staffTrainingDir = $input->getOption('staff-training-dir') ? (string)$input->getOption('staff-training-dir') : null;
        $stage2ReviewDir = $input->getOption('stage2-review-dir') ? (string)$input->getOption('stage2-review-dir') : null;
        $stage2ReviewPreviewDir = $input->getOption('stage2-review-preview-dir') ? (string)$input->getOption('stage2-review-preview-dir') : null;
        $governanceReadinessDir = $input->getOption('governance-readiness-dir') ? (string)$input->getOption('governance-readiness-dir') : null;
        $governanceClosureDir = $input->getOption('governance-closure-dir') ? (string)$input->getOption('governance-closure-dir') : null;
        $governanceClosureExecutionDir = $input->getOption('governance-closure-execution-dir') ? (string)$input->getOption('governance-closure-execution-dir') : null;
        $governanceClosurePilotDir = $input->getOption('governance-closure-pilot-dir') ? (string)$input->getOption('governance-closure-pilot-dir') : null;
        $governanceClosurePilotReturnDir = $input->getOption('governance-closure-pilot-return-dir') ? (string)$input->getOption('governance-closure-pilot-return-dir') : null;
        $governanceClosurePilotSourceUpdateDir = $input->getOption('governance-closure-pilot-source-update-dir') ? (string)$input->getOption('governance-closure-pilot-source-update-dir') : null;
        $governanceClosurePilotOperatorWorkbookDir = $input->getOption('governance-closure-pilot-operator-workbook-dir') ? (string)$input->getOption('governance-closure-pilot-operator-workbook-dir') : null;
        $governanceClosurePilotOperatorHandbackDir = $input->getOption('governance-closure-pilot-operator-handback-dir') ? (string)$input->getOption('governance-closure-pilot-operator-handback-dir') : null;
        $governanceClosurePilotOperatorCompletionSimulationDir = $input->getOption('governance-closure-pilot-operator-completion-simulation-dir') ? (string)$input->getOption('governance-closure-pilot-operator-completion-simulation-dir') : null;
        $governanceClosurePreviewDir = $input->getOption('governance-closure-preview-dir') ? (string)$input->getOption('governance-closure-preview-dir') : null;
        $governanceReadinessRefreshDir = $input->getOption('governance-readiness-refresh-dir') ? (string)$input->getOption('governance-readiness-refresh-dir') : null;
        $stage2Check = (bool)$input->getOption('stage2-check');

        try {
            if ($applyRehearsal) {
                $summary = QmsPreimportPackageService::rehearseApply($packageDir, $ackHumanReviewed, $reviewDir, $stage2Check, $fieldCatalogDir, $releasePlanDir, $releaseExecutionDir, $manualRevisionDir, $staffTrainingDir, $stage2ReviewDir, $stage2ReviewPreviewDir, $governanceReadinessDir, $governanceClosureDir, $governanceClosurePreviewDir, $governanceReadinessRefreshDir, $governanceClosureExecutionDir, $governanceClosurePilotDir, $governanceClosurePilotReturnDir, $governanceClosurePilotSourceUpdateDir, $governanceClosurePilotOperatorWorkbookDir, $governanceClosurePilotOperatorHandbackDir, $governanceClosurePilotOperatorCompletionSimulationDir);
            } elseif ($apply) {
                $summary = QmsPreimportPackageService::apply($packageDir, $ackHumanReviewed, $reviewDir, $stage2Check, $fieldCatalogDir, $releasePlanDir, $releaseExecutionDir, $manualRevisionDir, $staffTrainingDir, $stage2ReviewDir, $stage2ReviewPreviewDir, $governanceReadinessDir, $governanceClosureDir, $governanceClosurePreviewDir, $governanceReadinessRefreshDir, $governanceClosureExecutionDir, $governanceClosurePilotDir, $governanceClosurePilotReturnDir, $governanceClosurePilotSourceUpdateDir, $governanceClosurePilotOperatorWorkbookDir, $governanceClosurePilotOperatorHandbackDir, $governanceClosurePilotOperatorCompletionSimulationDir);
            } else {
                $summary = QmsPreimportPackageService::inspect($packageDir, $reviewDir, $stage2Check, $fieldCatalogDir, $releasePlanDir, $releaseExecutionDir, $manualRevisionDir, $staffTrainingDir, $stage2ReviewDir, $stage2ReviewPreviewDir, $governanceReadinessDir, $governanceClosureDir, $governanceClosurePreviewDir, $governanceReadinessRefreshDir, $governanceClosureExecutionDir, $governanceClosurePilotDir, $governanceClosurePilotReturnDir, $governanceClosurePilotSourceUpdateDir, $governanceClosurePilotOperatorWorkbookDir, $governanceClosurePilotOperatorHandbackDir, $governanceClosurePilotOperatorCompletionSimulationDir);
            }
            if ($writePreviewDir) {
                $summary['write_preview'] = QmsPreimportPackageService::writePreviewPackage($summary, $writePreviewDir);
            }
            if ($stage2PreviewDir) {
                $summary['stage2_preview'] = QmsPreimportPackageService::writeStage2PreviewPackage($summary, $stage2PreviewDir);
            }
            QmsPreimportPackageService::writeReports(
                $summary,
                $input->getOption('json-out') ? (string)$input->getOption('json-out') : null,
                $input->getOption('md-out') ? (string)$input->getOption('md-out') : null
            );
        } catch (\Throwable $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return 1;
        }

        if ($applyRehearsal) {
            $output->writeln('QMS preimport package apply rehearsal.');
        } else {
            $output->writeln($apply ? 'QMS preimport package apply evaluated.' : 'QMS preimport package dry-run.');
        }
        $output->writeln('status: ' . (string)($summary['status'] ?? '-'));
        foreach ((array)($summary['counts'] ?? []) as $key => $value) {
            $output->writeln($key . ': ' . (string)$value);
        }
        foreach ((array)($summary['readiness'] ?? []) as $key => $value) {
            $output->writeln($key . ': ' . (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value));
        }
        foreach ((array)($summary['review_pack'] ?? []) as $key => $value) {
            if ($key === 'findings') {
                continue;
            }
            $output->writeln('review_pack.' . $key . ': ' . (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value));
        }
        foreach ((array)($summary['stage2_readiness'] ?? []) as $key => $value) {
            if ($key === 'findings') {
                continue;
            }
            $output->writeln('stage2.' . $key . ': ' . (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value));
        }
        foreach ((array)($summary['field_catalog'] ?? []) as $key => $value) {
            if ($key === 'findings') {
                continue;
            }
            $output->writeln('field_catalog.' . $key . ': ' . (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value));
        }
        foreach ((array)($summary['release_plan'] ?? []) as $key => $value) {
            if ($key === 'findings') {
                continue;
            }
            $output->writeln('release_plan.' . $key . ': ' . (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value));
        }
        foreach ((array)($summary['release_execution'] ?? []) as $key => $value) {
            if ($key === 'findings') {
                continue;
            }
            $output->writeln('release_execution.' . $key . ': ' . (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value));
        }
        foreach ((array)($summary['manual_revision'] ?? []) as $key => $value) {
            if ($key === 'findings') {
                continue;
            }
            $output->writeln('manual_revision.' . $key . ': ' . (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value));
        }
        foreach ((array)($summary['staff_training'] ?? []) as $key => $value) {
            if ($key === 'findings') {
                continue;
            }
            $output->writeln('staff_training.' . $key . ': ' . (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value));
        }
        foreach ((array)($summary['stage2_review'] ?? []) as $key => $value) {
            if ($key === 'findings') {
                continue;
            }
            $output->writeln('stage2_review.' . $key . ': ' . (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value));
        }
        foreach ((array)($summary['stage2_review_preview'] ?? []) as $key => $value) {
            if ($key === 'findings') {
                continue;
            }
            $output->writeln('stage2_review_preview.' . $key . ': ' . (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value));
        }
        foreach ((array)($summary['governance_readiness'] ?? []) as $key => $value) {
            if ($key === 'findings') {
                continue;
            }
            $output->writeln('governance_readiness.' . $key . ': ' . (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value));
        }
        foreach ((array)($summary['governance_closure'] ?? []) as $key => $value) {
            if ($key === 'findings') {
                continue;
            }
            $output->writeln('governance_closure.' . $key . ': ' . (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value));
        }
        foreach ((array)($summary['governance_closure_execution'] ?? []) as $key => $value) {
            if ($key === 'findings') {
                continue;
            }
            $output->writeln('governance_closure_execution.' . $key . ': ' . (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value));
        }
        foreach ((array)($summary['governance_closure_pilot'] ?? []) as $key => $value) {
            if ($key === 'findings') {
                continue;
            }
            $output->writeln('governance_closure_pilot.' . $key . ': ' . (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value));
        }
        foreach ((array)($summary['governance_closure_pilot_return'] ?? []) as $key => $value) {
            if ($key === 'findings') {
                continue;
            }
            $output->writeln('governance_closure_pilot_return.' . $key . ': ' . (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value));
        }
        foreach ((array)($summary['governance_closure_pilot_source_update'] ?? []) as $key => $value) {
            if ($key === 'findings') {
                continue;
            }
            $output->writeln('governance_closure_pilot_source_update.' . $key . ': ' . (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value));
        }
        foreach ((array)($summary['governance_closure_pilot_operator_workbook'] ?? []) as $key => $value) {
            if ($key === 'findings') {
                continue;
            }
            $output->writeln('governance_closure_pilot_operator_workbook.' . $key . ': ' . (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value));
        }
        foreach ((array)($summary['governance_closure_pilot_operator_handback'] ?? []) as $key => $value) {
            if ($key === 'findings') {
                continue;
            }
            $output->writeln('governance_closure_pilot_operator_handback.' . $key . ': ' . (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value));
        }
        foreach ((array)($summary['governance_closure_pilot_operator_completion_simulation'] ?? []) as $key => $value) {
            if ($key === 'findings') {
                continue;
            }
            $output->writeln('governance_closure_pilot_operator_completion_simulation.' . $key . ': ' . (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value));
        }
        foreach ((array)($summary['governance_closure_preview'] ?? []) as $key => $value) {
            if ($key === 'findings') {
                continue;
            }
            $output->writeln('governance_closure_preview.' . $key . ': ' . (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value));
        }
        foreach ((array)($summary['governance_readiness_refresh'] ?? []) as $key => $value) {
            if ($key === 'findings') {
                continue;
            }
            $output->writeln('governance_readiness_refresh.' . $key . ': ' . (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value));
        }
        foreach ((array)($summary['rehearsal_plan'] ?? []) as $key => $value) {
            $output->writeln('rehearsal_plan.' . $key . ': ' . (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value));
        }
        foreach ((array)($summary['write_preview'] ?? []) as $key => $value) {
            $output->writeln('write_preview.' . $key . ': ' . (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value));
        }
        foreach ((array)($summary['stage2_preview'] ?? []) as $key => $value) {
            $output->writeln('stage2_preview.' . $key . ': ' . (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value));
        }
        if (!empty($summary['applied'])) {
            foreach ((array)$summary['applied'] as $key => $value) {
                $output->writeln('applied.' . $key . ': ' . (string)$value);
            }
        }
        if ($input->getOption('json-out')) {
            $output->writeln('report_json: ' . (string)$input->getOption('json-out'));
        }
        if ($input->getOption('md-out')) {
            $output->writeln('report_md: ' . (string)$input->getOption('md-out'));
        }
        foreach ((array)($summary['findings'] ?? []) as $finding) {
            $line = '[' . (string)($finding['severity'] ?? '-') . '] '
                . (string)($finding['id'] ?? '-') . ': ' . (string)($finding['message'] ?? '');
            if (($finding['severity'] ?? '') === 'high') {
                $output->writeln('<error>' . $line . '</error>');
            } else {
                $output->writeln('<comment>' . $line . '</comment>');
            }
        }

        return in_array((string)($summary['status'] ?? ''), ['failed', 'blocked'], true) ? 1 : 0;
    }
}
