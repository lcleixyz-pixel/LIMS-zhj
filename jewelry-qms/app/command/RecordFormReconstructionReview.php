<?php
declare(strict_types=1);

namespace app\command;

use app\service\RecordFormReconstructionReviewService;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;

class RecordFormReconstructionReview extends Command
{
    protected function configure(): void
    {
        $this->setName('record_form:reconstruction_review')
            ->setDescription('生成记录表格重构前合规完整性审查包')
            ->addArgument('doc_number', Argument::OPTIONAL, '仅审查指定记录编号')
            ->addOption('module', 'm', Option::VALUE_REQUIRED, '按模块关键词过滤')
            ->addOption('doc-number', null, Option::VALUE_REQUIRED, '仅审查指定记录编号')
            ->addOption('stage', 's', Option::VALUE_REQUIRED, '审查阶段：pre|post|both', 'both')
            ->addOption('format', 'f', Option::VALUE_REQUIRED, '输出格式：json|markdown|both', 'both');
    }

    protected function execute(Input $input, Output $output): int
    {
        $moduleFilter = $input->getOption('module');
        $docNumber = $input->getOption('doc-number') ?: $input->getArgument('doc_number');
        $stage = (string)$input->getOption('stage');
        $format = (string)$input->getOption('format');

        if (!in_array($stage, ['pre', 'post', 'both'], true)) {
            $output->writeln('<error>stage 仅支持 pre、post、both</error>');
            return 1;
        }
        if (!in_array($format, ['json', 'markdown', 'both'], true)) {
            $output->writeln('<error>format 仅支持 json、markdown、both</error>');
            return 1;
        }

        $review = RecordFormReconstructionReviewService::reviewAll(
            is_string($moduleFilter) && $moduleFilter !== '' ? $moduleFilter : null,
            is_string($docNumber) && $docNumber !== '' ? $docNumber : null,
            $stage
        );
        $paths = RecordFormReconstructionReviewService::saveReview(
            $review,
            in_array($format, ['json', 'both'], true),
            in_array($format, ['markdown', 'both'], true)
        );

        $output->writeln('<info>记录表格重构准备审查完成</info>');
        $output->writeln('范围: ' . json_encode($review['scope'], JSON_UNESCAPED_UNICODE));
        foreach ($review['summary'] as $decision => $count) {
            $output->writeln($decision . ': ' . $count);
        }
        foreach ($paths as $type => $path) {
            $output->writeln($type . ': ' . $path);
        }

        return 0;
    }
}
