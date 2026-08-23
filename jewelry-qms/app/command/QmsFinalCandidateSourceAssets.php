<?php
declare(strict_types=1);

namespace app\command;

use app\service\DocumentSourceAssetService;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

final class QmsFinalCandidateSourceAssets extends Command
{
    protected function configure(): void
    {
        $this->setName('qms:link-final-source-assets')
            ->addOption('source-dir', null, Option::VALUE_REQUIRED, '65份来源Word固定快照目录')
            ->addOption('apply', null, Option::VALUE_NONE, '写入8021来源资产关联；默认只检查')
            ->addOption('ack-8021-source-assets', null, Option::VALUE_NONE, '确认只补8021候选来源资产和结构化文件关联')
            ->setDescription('检查或补齐GOV-TRIAL/0.3的65份来源Word资产关联');
    }

    protected function execute(Input $input, Output $output): int
    {
        $sourceDir = trim((string)$input->getOption('source-dir'));
        $apply = (bool)$input->getOption('apply');
        if ($sourceDir === '') {
            $output->writeln('<error>必须提供 --source-dir</error>');
            return 1;
        }
        if ($apply && !(bool)$input->getOption('ack-8021-source-assets')) {
            $output->writeln('<error>写入前必须同时提供 --ack-8021-source-assets</error>');
            return 1;
        }

        try {
            $result = $apply
                ? DocumentSourceAssetService::applyFinalCandidateSnapshot($sourceDir)
                : DocumentSourceAssetService::previewFinalCandidateSnapshot($sourceDir);
            $output->writeln(json_encode(
                $result,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
            ));
            return ($result['validation']['ok'] ?? false) === true ? 0 : 1;
        } catch (\Throwable $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return 1;
        }
    }
}
