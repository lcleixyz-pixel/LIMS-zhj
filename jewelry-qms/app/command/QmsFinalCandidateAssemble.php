<?php
declare(strict_types=1);

namespace app\command;

use app\service\FinalCandidateAssemblyService;
use RuntimeException;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

final class QmsFinalCandidateAssemble extends Command
{
    protected function configure(): void
    {
        $this->setName('qms:assemble-final-candidate')
            ->addOption('source-dir', null, Option::VALUE_REQUIRED, '65份制度文件来源快照目录')
            ->addOption('output', null, Option::VALUE_REQUIRED, '候选试装报告目录')
            ->addOption('apply', null, Option::VALUE_NONE, '写入8021隔离试运行数据库；默认只检查')
            ->addOption('ack-8021-candidate', null, Option::VALUE_NONE, '确认本次只写8021候选草稿和废止留痕')
            ->setDescription('检查或装配GOV-TRIAL/0.3的65份制度文件候选；不装表单和运行记录');
    }

    protected function execute(Input $input, Output $output): int
    {
        $sourceDir = trim((string)$input->getOption('source-dir'));
        $outputDir = trim((string)$input->getOption('output'));
        $apply = (bool)$input->getOption('apply');
        $ack = (bool)$input->getOption('ack-8021-candidate');
        if ($sourceDir === '') {
            $output->writeln('<error>必须提供 --source-dir</error>');
            return 1;
        }
        if ($apply && !$ack) {
            $output->writeln('<error>写入前必须同时提供 --ack-8021-candidate</error>');
            return 1;
        }

        try {
            $result = $apply
                ? FinalCandidateAssemblyService::apply($sourceDir, $outputDir !== '' ? $outputDir : null)
                : FinalCandidateAssemblyService::preview($sourceDir);
            if (!$apply && $outputDir !== '') {
                $result['package'] = FinalCandidateAssemblyService::writePackage($result, $outputDir, 'dry-run');
            }
            $output->writeln(json_encode(
                self::withoutBodies($result),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
            ));
            return ($result['validation']['ok'] ?? false) === true ? 0 : 1;
        } catch (\Throwable $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return 1;
        }
    }

    private static function withoutBodies(array $result): array
    {
        foreach ($result['documents'] ?? [] as $index => $document) {
            unset($document['resolved_body'], $document['rendered_markdown']);
            $result['documents'][$index] = $document;
        }
        return $result;
    }
}
