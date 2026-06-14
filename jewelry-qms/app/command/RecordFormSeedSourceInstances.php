<?php
declare(strict_types=1);

namespace app\command;

use app\service\RecordFormSourceInstanceDraftService;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

class RecordFormSeedSourceInstances extends Command
{
    protected function configure(): void
    {
        $this->setName('record_form:seed_source_instances')
            ->setDescription('从现用记录表格源摘录逐表生成基础质量运行记录草稿')
            ->addOption('year', null, Option::VALUE_REQUIRED, '运行记录年度', '2025')
            ->addOption('module', 'm', Option::VALUE_REQUIRED, '仅处理指定模块')
            ->addOption('doc-prefix', null, Option::VALUE_REQUIRED, '仅处理指定记录编号前缀，如 XZTC/BG-01')
            ->addOption('doc-number', null, Option::VALUE_REQUIRED, '仅处理指定记录编号，如 XZTC/BG-01-02')
            ->addOption('template-id', null, Option::VALUE_REQUIRED, '仅处理指定记录模板 ID')
            ->addOption('limit', 'l', Option::VALUE_REQUIRED, '最多处理 N 张模板', '0')
            ->addOption('batch-id', null, Option::VALUE_REQUIRED, '批次报告 ID；默认自动生成')
            ->addOption('skip-existing', null, Option::VALUE_NONE, '跳过已有同模板年度运行记录（默认启用）')
            ->addOption('create-incomplete', null, Option::VALUE_NONE, '缺必填字段时仍创建 draft 草稿并在报告中标记')
            ->addOption('apply', null, Option::VALUE_NONE, '正式新建 draft 草稿实例')
            ->addOption('preview-pdf', null, Option::VALUE_NONE, '生成临时 PDF 版式确认文件，不改变实例状态')
            ->addOption('ai', null, Option::VALUE_NONE, '规则抽取缺口字段时尝试 AI 辅助候选');
    }

    protected function execute(Input $input, Output $output): int
    {
        $options = [
            'year' => (int)$input->getOption('year'),
            'module' => (string)$input->getOption('module'),
            'doc_prefix' => (string)$input->getOption('doc-prefix'),
            'doc_number' => (string)$input->getOption('doc-number'),
            'template_id' => (string)$input->getOption('template-id'),
            'limit' => (int)$input->getOption('limit'),
            'batch_id' => (string)$input->getOption('batch-id'),
            'skip_existing' => true,
            'create_incomplete' => (bool)$input->getOption('create-incomplete'),
            'apply' => (bool)$input->getOption('apply'),
            'preview_pdf' => (bool)$input->getOption('preview-pdf'),
            'ai' => (bool)$input->getOption('ai'),
        ];

        try {
            $summary = RecordFormSourceInstanceDraftService::seed($options);
        } catch (\Throwable $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return 1;
        }

        $output->writeln($summary['apply'] ? 'Source-filled draft instances created.' : 'Source-filled draft instances dry-run.');
        $output->writeln('total: ' . (int)$summary['total']);
        $output->writeln('created: ' . (int)$summary['created']);
        $output->writeln('dry_run_ready: ' . (int)$summary['dry_run']);
        $output->writeln('ready_with_gaps: ' . (int)($summary['ready_with_gaps'] ?? 0));
        $output->writeln('needs_manual_input: ' . (int)$summary['needs_manual_input']);
        $output->writeln('skipped_existing: ' . (int)($summary['skipped_existing'] ?? 0));
        $output->writeln('errors: ' . (int)$summary['errors']);
        if (!empty($summary['report']['json_path'])) {
            $output->writeln('report_json: ' . (string)$summary['report']['json_path']);
        }
        if (!empty($summary['report']['markdown_path'])) {
            $output->writeln('report_md: ' . (string)$summary['report']['markdown_path']);
        }

        foreach ($summary['rows'] as $row) {
            $label = (string)($row['doc_number'] ?? '-') . ' ' . (string)($row['name'] ?? '-');
            $output->writeln('');
            $output->writeln($label . ' => ' . (string)($row['decision'] ?? '-'));
            if (!empty($row['instance_url'])) {
                $output->writeln('instance: ' . (string)$row['instance_url']);
            }
            if (!empty($row['print_url'])) {
                $output->writeln('print_html: ' . (string)$row['print_url']);
            }
            if (!empty($row['preview_pdf']['file_path'])) {
                $output->writeln('preview_pdf: ' . (string)$row['preview_pdf']['file_path']);
            }
            if (!empty($row['preview_pdf']['download_url'])) {
                $output->writeln('preview_pdf_download: ' . (string)$row['preview_pdf']['download_url']);
            }
            if (!empty($row['source_markdown_path'])) {
                $output->writeln('source: ' . (string)$row['source_markdown_path']);
            }
            if (!empty($row['ai_candidate_fields'])) {
                $output->writeln('ai_candidate: ' . implode(', ', (array)$row['ai_candidate_fields']));
            }
            if (!empty($row['low_confidence_fields'])) {
                $output->writeln('low_confidence: ' . implode(', ', (array)$row['low_confidence_fields']));
            }
            if (!empty($row['blank_required_fields'])) {
                $output->writeln('blank_required: ' . implode(', ', (array)$row['blank_required_fields']));
            }
            if (!empty($row['manual_layout_status'])) {
                $output->writeln('manual_layout_status: ' . (string)$row['manual_layout_status']);
            }
            foreach ((array)($row['warnings'] ?? []) as $warning) {
                $output->writeln('<comment>warning: ' . (string)$warning . '</comment>');
            }
            if (!empty($row['error'])) {
                $output->writeln('<error>error: ' . (string)$row['error'] . '</error>');
            }
        }

        return $summary['errors'] > 0 ? 1 : 0;
    }
}
