<?php
declare(strict_types=1);

namespace app\command;

use app\service\RecordFormSchemaRebuilder;
use app\service\RecordFormReconstructionReviewService;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;

class RecordFormRebuildSchema extends Command
{
    protected function configure(): void
    {
        $this->setName('record_form:rebuild_schema')
            ->setDescription('用 AI 从 Word 摘录重建记录表格 field_schema')
            ->addArgument('doc_number', Argument::OPTIONAL, '仅处理指定编号的模板（如 XZTC/BG-09-01）')
            ->addOption('module', 'm', Option::VALUE_REQUIRED, '按模块关键词过滤（如"合同评审"）')
            ->addOption('dry-run', null, Option::VALUE_NONE, '仅输出对比报告，不写入注册表')
            ->addOption('limit', 'l', Option::VALUE_REQUIRED, '最多处理 N 个文件', '0')
            ->addOption('delay', 'd', Option::VALUE_REQUIRED, 'API 调用间隔秒数（避免限流）', '2');
    }

    protected function execute(Input $input, Output $output): int
    {
        $docNumberFilter = $input->getArgument('doc_number');
        $moduleFilter = $input->getOption('module');
        $dryRun = (bool)$input->getOption('dry-run');
        $limit = max(0, (int)$input->getOption('limit'));
        $delay = max(0, (int)$input->getOption('delay'));

        $output->writeln('<info>记录表格 Schema 重建工具</info>');
        $output->writeln('');

        if ($docNumberFilter) {
            $output->writeln('过滤编号: ' . $docNumberFilter);
        }
        if ($moduleFilter) {
            $output->writeln('过滤模块: ' . $moduleFilter);
        }
        if ($dryRun) {
            $output->writeln('<comment>DRY-RUN 模式：不写入注册表</comment>');
        }

        $files = RecordFormSchemaRebuilder::listFiles(is_string($moduleFilter) ? $moduleFilter : null);
        if ($docNumberFilter) {
            $docNumberFilter = (string)$docNumberFilter;
            $files = array_filter($files, static function (string $path) use ($docNumberFilter): bool {
                $content = (string)file_get_contents($path);
                $dn = RecordFormSchemaRebuilder::parseDocNumber($content);
                if ($dn === $docNumberFilter) {
                    return true;
                }
                if ($dn === null) {
                    return false;
                }
                $sourceMarkdown = RecordFormSchemaRebuilder::extractSourceMarkdown($content);
                $identity = RecordFormSchemaRebuilder::analyzeDocNumberIdentity($dn, $sourceMarkdown);
                return ($identity['doc_number'] ?? '') === $docNumberFilter;
            });
            $files = array_values($files);
        }

        $total = count($files);
        if ($total === 0) {
            $output->writeln('<comment>未找到匹配的 .md 文件</comment>');
            return 0;
        }

        if ($limit > 0 && $total > $limit) {
            $files = array_slice($files, 0, $limit);
            $output->writeln("共 {$total} 个文件，限制处理 {$limit} 个");
            $total = $limit;
        } else {
            $output->writeln("共 {$total} 个文件待处理");
        }
        $output->writeln('');

        $docNumberCounts = [];
        foreach (RecordFormSchemaRebuilder::listFiles() as $path) {
            $content = (string)file_get_contents($path);
            $docNumber = RecordFormSchemaRebuilder::parseDocNumber($content);
            if ($docNumber !== null) {
                $identity = RecordFormSchemaRebuilder::analyzeDocNumberIdentity(
                    $docNumber,
                    RecordFormSchemaRebuilder::extractSourceMarkdown($content)
                );
                $docNumber = (string)($identity['doc_number'] ?? $docNumber);
                $docNumberCounts[$docNumber] = ($docNumberCounts[$docNumber] ?? 0) + 1;
            }
        }

        $registry = RecordFormSchemaRebuilder::loadRegistry();
        $stats = ['processed' => 0, 'skipped' => 0, 'conflicts' => 0, 'errors' => 0, 'updated' => 0];

        foreach ($files as $index => $path) {
            $filename = basename($path);
            $progress = '[' . ($index + 1) . '/' . $total . '] ';

            try {
                $result = RecordFormSchemaRebuilder::processFile($path);
            } catch (\Throwable $e) {
                $output->writeln($progress . '<error>' . $filename . ': ' . $e->getMessage() . '</error>');
                $stats['errors']++;
                continue;
            }

            $dn = $result['doc_number'];
            $name = $result['name'];
            if (!empty($result['renumbered'])) {
                $output->writeln($progress . '<comment>编号归并: ' . ($result['original_doc_number'] ?? '')
                    . ' → ' . $dn . '（' . ($result['identity_reason'] ?? '') . '）</comment>');
            }

            if (!empty($result['conflict'])) {
                $output->writeln($progress . '<comment>' . $dn . ' ' . $name . ' — 编号冲突: ' . ($result['reason'] ?? '') . '，未写入注册表</comment>');
                $stats['skipped']++;
                $stats['conflicts']++;
                $stats['processed']++;
                continue;
            }

            if (!empty($result['skipped'])) {
                $output->writeln($progress . '<comment>' . $dn . ' ' . $name . ' — 跳过: ' . ($result['reason'] ?? '') . '</comment>');
                $stats['skipped']++;
                continue;
            }

            $oldCount = count($result['old_schema']);
            $newCount = count($result['new_schema']);
            $changed = $oldCount !== $newCount || json_encode($result['old_schema']) !== json_encode($result['new_schema']);
            $marker = $changed ? '<info>已更新</info>' : '无变化';

            $output->writeln($progress . $dn . ' ' . $name . ' — 字段 ' . $oldCount . '→' . $newCount . ' ' . $marker);

            if ($changed && !$dryRun) {
                $registryKey = $dn . '::' . pathinfo($filename, PATHINFO_FILENAME);
                $registryEntry = [
                    'doc_number' => $dn,
                    'name' => $name,
                    'module' => $result['module'],
                    'field_schema' => $result['new_schema'],
                    'reconstruction_review' => RecordFormReconstructionReviewService::registrySummary($result['reconstruction_packet'] ?? []),
                    'schema_coverage' => $result['schema_coverage'] ?? [],
                    'generated_at' => date('Y-m-d H:i:s'),
                    'source_file' => $filename,
                ];
                $registry[$registryKey] = $registryEntry;
                if (($docNumberCounts[$dn] ?? 0) === 1) {
                    $registry[$dn] = $registryEntry;
                } else {
                    unset($registry[$dn]);
                }
                $stats['updated']++;
            }

            $stats['processed']++;

            if ($delay > 0 && $index < $total - 1) {
                sleep($delay);
            }
        }

        if (!$dryRun && $stats['updated'] > 0) {
            RecordFormSchemaRebuilder::saveRegistry($registry);
            $output->writeln('');
            $output->writeln('<info>注册表已写入: ' . RecordFormSchemaRebuilder::registryPath() . '</info>');
        }

        $output->writeln('');
        $output->writeln('处理: ' . $stats['processed'] . ' | 更新: ' . $stats['updated']
            . ' | 跳过: ' . $stats['skipped'] . ' | 冲突: ' . $stats['conflicts'] . ' | 错误: ' . $stats['errors']);

        return $stats['errors'] > 0 ? 1 : 0;
    }
}
