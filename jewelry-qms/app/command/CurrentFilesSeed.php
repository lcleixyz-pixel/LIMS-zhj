<?php
declare(strict_types=1);

namespace app\command;

use app\service\CurrentFilesSeedService;
use app\service\QmsDocumentStructureService;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

class CurrentFilesSeed extends Command
{
    protected function configure(): void
    {
        $this->setName('qms:seed-current-files')
            ->addOption('apply', null, Option::VALUE_NONE, '正式写入数据库')
            ->addOption('source', null, Option::VALUE_OPTIONAL, '现用文件目录')
            ->addOption('equipment-urumqi', null, Option::VALUE_OPTIONAL, '乌鲁木齐设备配置 Excel')
            ->addOption('equipment-hetian', null, Option::VALUE_OPTIONAL, '和田设备配置 Excel')
            ->addOption('enumerate-procedures', null, Option::VALUE_NONE, '只枚举 2022 程序文件并导出清单，不写数据库')
            ->addOption('manifest-output', null, Option::VALUE_OPTIONAL, '程序文件清单输出目录，默认 knowledge/internal/procedures')
            ->addOption('export-knowledge-internal', null, Option::VALUE_NONE, '导出结构化库到 knowledge/internal，不写业务记录')
            ->addOption('knowledge-output', null, Option::VALUE_OPTIONAL, '内部知识导出根目录，默认 knowledge/internal')
            ->addOption('refresh-structures', null, Option::VALUE_NONE, '导出前先刷新结构化库')
            ->setDescription('按现用质量手册、程序文件、作业指导书和设备配置表初始化实验室真实信息');
    }

    protected function execute(Input $input, Output $output): int
    {
        $options = ['apply' => (bool)$input->getOption('apply')];
        if ($input->getOption('source')) {
            $options['source_root'] = (string)$input->getOption('source');
        }
        if ($input->getOption('equipment-urumqi')) {
            $options['urumqi_equipment_path'] = (string)$input->getOption('equipment-urumqi');
        }
        if ($input->getOption('equipment-hetian')) {
            $options['hetian_equipment_path'] = (string)$input->getOption('equipment-hetian');
        }

        try {
            if ($input->getOption('enumerate-procedures')) {
                $sourceRoot = (string)($options['source_root'] ?? (dirname(rtrim(app()->getRootPath(), '/\\')) . DIRECTORY_SEPARATOR . '现用文件'));
                $manifest = CurrentFilesSeedService::enumerateProcedureFiles($sourceRoot);
                $paths = CurrentFilesSeedService::writeProcedureManifest(
                    $manifest,
                    $input->getOption('manifest-output') ? (string)$input->getOption('manifest-output') : null
                );
                $output->writeln('Procedure files enumerated.');
                $output->writeln('procedure_directory.total: ' . (int)($manifest['total_files'] ?? 0));
                $output->writeln('procedure_directory.numbered: ' . (int)($manifest['numbered_files'] ?? 0));
                $output->writeln('procedure_directory.excluded: ' . (int)($manifest['excluded_files'] ?? 0));
                $output->writeln('manifest.markdown: ' . (string)($paths['markdown'] ?? ''));
                $output->writeln('manifest.json: ' . (string)($paths['json'] ?? ''));
                return 0;
            }

            $exportKnowledge = (bool)$input->getOption('export-knowledge-internal');
            if ($exportKnowledge && !$options['apply'] && !$input->getOption('refresh-structures')) {
                $summary = null;
            } else {
                $summary = CurrentFilesSeedService::seed($options);
            }
            if ($input->getOption('refresh-structures')) {
                QmsDocumentStructureService::seedAll();
            }
            if ($exportKnowledge) {
                $export = QmsDocumentStructureService::exportKnowledgeInternal(
                    $input->getOption('knowledge-output') ? (string)$input->getOption('knowledge-output') : null
                );
                if ($summary !== null) {
                    $this->writeSeedSummary($output, $summary);
                }
                $output->writeln('Knowledge internal export completed.');
                $output->writeln('knowledge.manual.exported: ' . (int)($export['manual']['exported'] ?? 0));
                $output->writeln('knowledge.procedures.exported: ' . (int)($export['procedures']['exported'] ?? 0));
                $output->writeln('knowledge.issues: ' . count((array)($export['issues'] ?? [])));
                $output->writeln('knowledge.report: ' . (string)($export['reports']['markdown'] ?? ''));
                $output->writeln('knowledge.report_json: ' . (string)($export['reports']['json'] ?? ''));
                return 0;
            }
        } catch (\Throwable $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return 1;
        }

        $this->writeSeedSummary($output, $summary);

        return 0;
    }

    private function writeSeedSummary(Output $output, array $summary): void
    {
        $output->writeln($summary['apply'] ? 'Current QMS files seeded.' : 'Current QMS files parsed in dry-run mode.');
        $output->writeln('company.updated: ' . (int)($summary['company']['updated'] ?? 0));
        $output->writeln('sites.upserted: ' . (int)($summary['sites']['upserted'] ?? 0));
        $output->writeln('employees.upserted: ' . (int)($summary['employees']['upserted'] ?? 0));
        $output->writeln('appointments.upserted: ' . (int)($summary['appointments']['upserted'] ?? 0));
        $output->writeln('documents.work_instructions: ' . (int)($summary['documents']['work_instructions'] ?? 0));
        $output->writeln('equipment.urumqi: ' . (int)($summary['equipment']['urumqi'] ?? 0));
        $output->writeln('equipment.hetian: ' . (int)($summary['equipment']['hetian'] ?? 0));
        $output->writeln('missing_evidence: ' . count((array)($summary['missing_evidence'] ?? [])));
    }
}
