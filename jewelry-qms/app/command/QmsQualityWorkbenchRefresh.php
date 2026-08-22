<?php
declare(strict_types=1);

namespace app\command;

use app\service\QualityWorkbenchService;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

final class QmsQualityWorkbenchRefresh extends Command
{
    protected function configure(): void
    {
        $this->setName('qms:refresh-quality-workbench')
            ->addOption('apply', null, Option::VALUE_NONE, '写入8021测试环境；默认只检查')
            ->addOption('ack-quality-workbench', null, Option::VALUE_NONE, '确认本次仅刷新8021质量工作台')
            ->addOption('notify', null, Option::VALUE_NONE, '写入时为阻断任务生成通知')
            ->addOption('output', null, Option::VALUE_REQUIRED, '报告输出目录')
            ->setDescription('刷新质量工作台系统评审项目和任务；不改业务模块事实、不生成运行记录');
    }

    protected function execute(Input $input, Output $output): int
    {
        $apply = (bool)$input->getOption('apply');
        $ack = (bool)$input->getOption('ack-quality-workbench');
        if ($apply && !$ack) {
            $output->writeln('<error>写入前必须同时提供 --ack-quality-workbench</error>');
            return 1;
        }

        try {
            $service = new QualityWorkbenchService();
            $result = $service->refreshSystemProjects($apply, (bool)$input->getOption('notify'));
            $outputDir = trim((string)$input->getOption('output'));
            if ($outputDir !== '') {
                $result['report'] = $this->writeReport($result, $outputDir, $apply);
            }
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

    private function writeReport(array $result, string $outputDir, bool $apply): array
    {
        $workspaceRoot = is_dir('/.team') ? '/' : dirname(__DIR__, 3);
        if (!str_starts_with($outputDir, '/')) {
            $outputDir = rtrim($workspaceRoot, '/') . '/' . ltrim($outputDir, '/');
        }
        if (!is_dir($outputDir) && !mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
            throw new \RuntimeException('无法创建质量工作台报告目录：' . $outputDir);
        }

        $file = rtrim($outputDir, '/') . '/' . ($apply ? '质量工作台写入报告.json' : '质量工作台干跑报告.json');
        file_put_contents(
            $file,
            json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
        );

        return [
            'path' => $file,
            'sha256' => hash_file('sha256', $file),
        ];
    }
}
