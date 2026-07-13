<?php
declare(strict_types=1);

namespace app\command;

use app\service\QmsClauseRemediationService;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

final class QmsClauseRemediate extends Command
{
    protected function configure(): void
    {
        $this->setName('qms:remediate-clause-library')
            ->addOption('source-dir', null, Option::VALUE_REQUIRED, '已核验 Markdown 原文目录')
            ->addOption('output-dir', null, Option::VALUE_REQUIRED, '预演与复核报告目录')
            ->addOption('apply', null, Option::VALUE_NONE, '在预演无冲突后事务写入数据库')
            ->setDescription('按已批准的要求原子白名单补录条款库；默认只预演');
    }

    protected function execute(Input $input, Output $output): int
    {
        $sourceDir = trim((string)$input->getOption('source-dir'));
        $outputDir = trim((string)$input->getOption('output-dir'));
        if ($sourceDir === '' || $outputDir === '') {
            $output->writeln('<error>必须同时提供 --source-dir 与 --output-dir</error>');
            return 1;
        }

        try {
            $apply = (bool)$input->getOption('apply');
            $plan = $apply
                ? QmsClauseRemediationService::apply($sourceDir)
                : QmsClauseRemediationService::buildPlan($sourceDir);
            $prefix = $apply ? '03-写库后复核' : '01-写库前预演';
            $paths = QmsClauseRemediationService::writeReports($plan, $outputDir, $prefix);
            $output->writeln($apply ? 'Clause library remediation applied.' : 'Clause library remediation previewed.');
            $output->writeln('requirements.total: ' . (int)$plan['counts']['total']);
            $output->writeln('requirements.insert: ' . (int)$plan['counts']['insert']);
            $output->writeln('requirements.existing: ' . (int)$plan['counts']['existing']);
            $output->writeln('requirements.conflict: ' . (int)$plan['counts']['conflict']);
            $output->writeln('equivalence.status: ' . (string)$plan['equivalence']['status']);
            $output->writeln('report.markdown: ' . (string)$paths['markdown']);
            return (int)$plan['counts']['conflict'] === 0 ? 0 : 1;
        } catch (\Throwable $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return 1;
        }
    }
}
