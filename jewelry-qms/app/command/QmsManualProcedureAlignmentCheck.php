<?php
declare(strict_types=1);

namespace app\command;

use app\service\QmsManualProcedureAlignmentReportService;
use app\service\QmsManualProcedureAlignmentService;
use app\service\QmsManualProcedureTraceService;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

final class QmsManualProcedureAlignmentCheck extends Command
{
    protected function configure(): void
    {
        $this->setName('qms:check-manual-procedure-alignment')
            ->addOption('spec', null, Option::VALUE_REQUIRED, '手册要求原子试点 JSON')
            ->addOption('procedure-dir', null, Option::VALUE_REQUIRED, '当前程序 Markdown 目录')
            ->addOption('output-dir', null, Option::VALUE_REQUIRED, '只读校验报告目录')
            ->addOption('trace-snapshot', null, Option::VALUE_REQUIRED, '测试用追溯快照；不提供时只读查询当前系统')
            ->addOption('report-version', null, Option::VALUE_REQUIRED, '报告版本，例如 v0.1', 'v0.1')
            ->setDescription('沿现有追溯链检查手册要求与程序文件一致性；只读');
    }

    protected function execute(Input $input, Output $output): int
    {
        $specPath = trim((string)$input->getOption('spec'));
        $procedureDir = trim((string)$input->getOption('procedure-dir'));
        $outputDir = trim((string)$input->getOption('output-dir'));
        $snapshotPath = trim((string)$input->getOption('trace-snapshot'));
        $reportVersion = trim((string)$input->getOption('report-version'));
        if ($specPath === '' || $procedureDir === '' || $outputDir === '') {
            $output->writeln('<error>必须提供 --spec、--procedure-dir 与 --output-dir</error>');
            return 1;
        }
        if (preg_match('/^v\d+\.\d+$/', $reportVersion) !== 1) {
            $output->writeln('<error>--report-version 必须使用 v0.1 格式</error>');
            return 1;
        }

        try {
            $inputs = QmsManualProcedureAlignmentService::loadInputs($specPath, $procedureDir);
            $trace = $snapshotPath !== ''
                ? QmsManualProcedureTraceService::fromSnapshot($snapshotPath)
                : QmsManualProcedureTraceService::fromDatabase(
                    array_values(array_unique(array_map(
                        static fn (array $row): string => (string)$row['manual_section'],
                        (array)$inputs['requirements']
                    ))),
                    (array)$inputs['pilot_procedures']
                );
            $result = QmsManualProcedureAlignmentService::check($inputs, $trace);
            $paths = QmsManualProcedureAlignmentReportService::write(
                $result,
                $outputDir,
                '手册程序一致性校验-' . $reportVersion
            );
            foreach ((array)$result['counts'] as $status => $count) {
                $output->writeln($status . ': ' . (int)$count);
            }
            $output->writeln('trace_gaps: ' . count((array)$result['trace_gaps']));
            $output->writeln('blockers: ' . count((array)$result['blockers']));
            $output->writeln('report.json: ' . (string)$paths['json']);
            $output->writeln('report.csv: ' . (string)$paths['csv']);
            $output->writeln('report.markdown: ' . (string)$paths['markdown']);

            return (array)$result['blockers'] === [] ? 0 : 1;
        } catch (\Throwable $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return 1;
        }
    }
}
