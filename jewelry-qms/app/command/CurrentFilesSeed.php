<?php
declare(strict_types=1);

namespace app\command;

use app\service\CurrentFilesSeedService;
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
            $summary = CurrentFilesSeedService::seed($options);
        } catch (\Throwable $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return 1;
        }

        $output->writeln($summary['apply'] ? 'Current QMS files seeded.' : 'Current QMS files parsed in dry-run mode.');
        $output->writeln('company.updated: ' . (int)($summary['company']['updated'] ?? 0));
        $output->writeln('sites.upserted: ' . (int)($summary['sites']['upserted'] ?? 0));
        $output->writeln('employees.upserted: ' . (int)($summary['employees']['upserted'] ?? 0));
        $output->writeln('appointments.upserted: ' . (int)($summary['appointments']['upserted'] ?? 0));
        $output->writeln('documents.work_instructions: ' . (int)($summary['documents']['work_instructions'] ?? 0));
        $output->writeln('equipment.urumqi: ' . (int)($summary['equipment']['urumqi'] ?? 0));
        $output->writeln('equipment.hetian: ' . (int)($summary['equipment']['hetian'] ?? 0));
        $output->writeln('missing_evidence: ' . count((array)($summary['missing_evidence'] ?? [])));

        return 0;
    }
}
