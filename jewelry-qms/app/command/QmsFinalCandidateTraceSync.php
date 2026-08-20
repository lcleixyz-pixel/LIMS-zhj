<?php
declare(strict_types=1);

namespace app\command;

use app\service\FinalCandidateTraceSyncService;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

final class QmsFinalCandidateTraceSync extends Command
{
    protected function configure(): void
    {
        $this->setName('qms:sync-final-candidate-trace')
            ->addOption('output', null, Option::VALUE_REQUIRED, '链路同步报告目录')
            ->addOption('apply', null, Option::VALUE_NONE, '写入8021测试环境；默认只检查')
            ->addOption('ack-8021-trace', null, Option::VALUE_NONE, '确认本次仅同步8021测试正式链路')
            ->setDescription('同步GOV-TRIAL/0.3测试正式制度的要素、章节和条款链路；不自动挂接记录表单');
    }

    protected function execute(Input $input, Output $output): int
    {
        $apply = (bool)$input->getOption('apply');
        $ack = (bool)$input->getOption('ack-8021-trace');
        $outputDir = trim((string)$input->getOption('output'));
        if ($apply && !$ack) {
            $output->writeln('<error>写入前必须同时提供 --ack-8021-trace</error>');
            return 1;
        }

        try {
            $result = $apply
                ? FinalCandidateTraceSyncService::apply($outputDir !== '' ? $outputDir : null)
                : FinalCandidateTraceSyncService::preview();
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
