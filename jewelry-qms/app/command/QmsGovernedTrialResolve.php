<?php
declare(strict_types=1);

namespace app\command;

use app\service\GovernedTrialResolvedDocumentService;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

final class QmsGovernedTrialResolve extends Command
{
    protected function configure(): void
    {
        $this->setName('qms:resolve-governed-trial')
            ->addOption('apply', null, Option::VALUE_NONE, '生成交接包并写入8021隔离试运行数据库；默认只检查')
            ->addOption('ack-signed-governance', null, Option::VALUE_NONE, '确认按已签认治理来源生成解析稿')
            ->addOption('output', null, Option::VALUE_REQUIRED, '可选：指定解析稿交接目录')
            ->setDescription('生成GOV-TRIAL/0.2连续解析稿；冲突未关闭时保留草稿并禁止提交审核');
    }

    protected function execute(Input $input, Output $output): int
    {
        $apply = (bool)$input->getOption('apply');
        $ack = (bool)$input->getOption('ack-signed-governance');
        $outputPath = trim((string)$input->getOption('output'));
        if ($apply && !$ack) {
            $output->writeln('<error>写入前必须同时提供 --ack-signed-governance</error>');
            return 1;
        }

        try {
            $result = $apply
                ? GovernedTrialResolvedDocumentService::apply($outputPath !== '' ? $outputPath : null)
                : GovernedTrialResolvedDocumentService::inspect();
            $output->writeln(json_encode(
                $result,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
            ));

            return 0;
        } catch (\Throwable $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return 1;
        }
    }
}
