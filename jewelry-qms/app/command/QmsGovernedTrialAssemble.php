<?php
declare(strict_types=1);

namespace app\command;

use app\service\GovernedTrialAssemblyService;
use RuntimeException;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

final class QmsGovernedTrialAssemble extends Command
{
    protected function configure(): void
    {
        $this->setName('qms:assemble-governed-trial')
            ->addOption('apply', null, Option::VALUE_NONE, '写入隔离治理试运行环境；默认只检查')
            ->addOption('ack-signed-governance', null, Option::VALUE_NONE, '确认使用G1/G2签认材料进行试运行装配')
            ->addOption('seed-samples', null, Option::VALUE_NONE, '生成两场所代表性SIM记录和可打印HTML')
            ->addOption('output', null, Option::VALUE_REQUIRED, '可选：把JSON结果写入指定路径')
            ->setDescription('检查或装配GOV-TRIAL-20260724治理试运行体系；拒绝非试运行环境写入');
    }

    protected function execute(Input $input, Output $output): int
    {
        $apply = (bool)$input->getOption('apply');
        $ack = (bool)$input->getOption('ack-signed-governance');
        $seedSamples = (bool)$input->getOption('seed-samples');
        $outputPath = trim((string)$input->getOption('output'));
        if ($apply && !$ack) {
            $output->writeln('<error>写入前必须同时提供 --ack-signed-governance</error>');
            return 1;
        }
        if ($seedSamples && !$apply) {
            $output->writeln('<error>--seed-samples 只能与 --apply 同时使用</error>');
            return 1;
        }

        try {
            $result = $apply
                ? GovernedTrialAssemblyService::apply($seedSamples)
                : GovernedTrialAssemblyService::inspect();
            $json = json_encode(
                $result,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            ) . PHP_EOL;
            if ($outputPath !== '') {
                $directory = dirname($outputPath);
                if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                    throw new RuntimeException('无法创建输出目录：' . $directory);
                }
                if (file_put_contents($outputPath, $json) === false) {
                    throw new RuntimeException('无法写入结果：' . $outputPath);
                }
            }
            $output->writeln($json);

            return ($result['validation']['ok'] ?? $result['ok'] ?? false) === true ? 0 : 1;
        } catch (\Throwable $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return 1;
        }
    }
}
