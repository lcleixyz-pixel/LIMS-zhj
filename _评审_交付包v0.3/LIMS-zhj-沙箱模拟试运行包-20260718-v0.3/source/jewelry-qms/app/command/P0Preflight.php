<?php
declare(strict_types=1);

namespace app\command;

use app\service\P0PreflightService;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

final class P0Preflight extends Command
{
    protected function configure(): void
    {
        $this->setName('qms:p0-preflight')
            ->setDescription('只读检查编号和 CAPA 双向关联完整性')
            ->addOption('format', null, Option::VALUE_REQUIRED, 'json 或 text', 'text');
    }

    protected function execute(Input $input, Output $output): int
    {
        $report = P0PreflightService::scan();
        $format = strtolower(trim((string)$input->getOption('format')));
        if ($format === 'json') {
            $output->writeln((string)json_encode(
                $report,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
            ));
        } elseif ($format === 'text') {
            $output->writeln('mode: read_only');
            $output->writeln('blocked: ' . ($report['blocked'] ? 'yes' : 'no'));
            foreach ($report['counts'] as $key => $count) {
                $output->writeln($key . ': ' . $count);
            }
        } else {
            $output->writeln('<error>--format 仅支持 json 或 text</error>');
            return 1;
        }

        return $report['blocked'] ? 2 : 0;
    }
}
