<?php
declare(strict_types=1);

namespace app\command;

use app\service\ComplianceCheckService;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Config;

class ComplianceAssess extends Command
{
    protected function configure(): void
    {
        $this->setName('compliance:assess')
            ->setDescription('执行审核准备驾驶舱合规就绪度评估');
    }

    protected function execute(Input $input, Output $output): int
    {
        $companyId = (string)Config::get('qms.company_id');

        try {
            $result = ComplianceCheckService::runFullAssessment($companyId, 'scheduled', null);
        } catch (\Throwable $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return 1;
        }

        $output->writeln('合规评估完成');
        $output->writeln('总评分: ' . number_format((float)$result['total_score'], 1) . '/100');

        $dimensionLabels = ComplianceCheckService::dimensionLabels();
        $dimensionTexts = [];
        foreach ($result['dimension_scores'] as $dimension => $score) {
            $label = $dimensionLabels[$dimension] ?? $dimension;
            $dimensionTexts[] = $label . ' ' . ($score === null ? '-' : number_format((float)$score, 1));
        }
        $output->writeln('维度评分: ' . implode(' | ', $dimensionTexts));

        $summary = $result['summary'];
        $output->writeln(
            '缺口: ' . (int)($summary['fail'] ?? 0) . '项不合规, '
            . (int)($summary['insufficient_data'] ?? 0) . '项数据不足'
        );
        $output->writeln('详情请访问 /compliance/index');

        return 0;
    }
}
