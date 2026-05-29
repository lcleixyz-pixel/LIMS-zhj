<?php
declare(strict_types=1);

namespace app\command;

use app\service\NotificationService;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

class CheckReminders extends Command
{
    protected function configure(): void
    {
        $this->setName('check:reminders')
            ->addOption('type', null, Option::VALUE_OPTIONAL, '提醒类型：all、calibration、capa、doc_review、competency', 'all')
            ->setDescription('检查 QMS 校准、CAPA、文件评审和能力到期提醒');
    }

    protected function execute(Input $input, Output $output): int
    {
        $type = (string)$input->getOption('type');

        try {
            $summary = NotificationService::runReminderChecks($type);
        } catch (\Throwable $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return 1;
        }

        $output->writeln('QMS reminder check completed.');
        foreach ($summary as $name => $value) {
            if (str_ends_with((string)$name, '_error')) {
                $output->writeln('<comment>' . $name . ': ' . $value . '</comment>');
                continue;
            }
            $output->writeln($name . ': ' . $value);
        }

        return 0;
    }
}
