<?php
declare(strict_types=1);

namespace app\command;

use app\service\AiChatService;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Config;

class AiPurgeChat extends Command
{
    protected function configure(): void
    {
        $this->setName('ai:purge-chat')
            ->setDescription('清理超过保留期的 AI 聊天会话');
    }

    protected function execute(Input $input, Output $output): int
    {
        $companyId = (string)Config::get('qms.company_id');

        try {
            $deleted = AiChatService::purgeExpiredSessions($companyId);
        } catch (\Throwable $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');

            return 1;
        }

        $output->writeln('Purged expired AI chat sessions: ' . $deleted);

        return 0;
    }
}
