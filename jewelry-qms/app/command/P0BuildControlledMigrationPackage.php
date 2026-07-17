<?php
declare(strict_types=1);

namespace app\command;

use app\service\P0ControlledMigrationPackageService;
use DomainException;
use JsonException;
use RuntimeException;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use Throwable;

final class P0BuildControlledMigrationPackage extends Command
{
    protected function configure(): void
    {
        $this->setName('qms:p0-build-controlled-migration-package')
            ->setDescription('只读采集现状并生成 B6 受控迁移、验收和回退包')
            ->addOption('confirmation', null, Option::VALUE_REQUIRED, '人工确认 JSON 文件')
            ->addOption('output', null, Option::VALUE_REQUIRED, '输出目录')
            ->addOption('rehearsal', null, Option::VALUE_NONE, '仅允许 B6 隔离数据库演练');
    }

    protected function execute(Input $input, Output $output): int
    {
        $confirmationPath = trim((string)$input->getOption('confirmation'));
        $outputDir = trim((string)$input->getOption('output'));
        if ($confirmationPath === '' || $outputDir === '') {
            $output->writeln('<error>--confirmation 和 --output 均为必填</error>');
            return 2;
        }
        if (!is_file($confirmationPath)) {
            $output->writeln('<error>人工确认文件不存在</error>');
            return 2;
        }

        try {
            $confirmation = json_decode(
                (string)file_get_contents($confirmationPath),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
            if (!is_array($confirmation)) {
                throw new RuntimeException('人工确认文件必须是 JSON 对象');
            }
            $summary = P0ControlledMigrationPackageService::build(
                $confirmation,
                $outputDir,
                (bool)$input->getOption('rehearsal')
            );
            $output->writeln((string)json_encode(
                $summary,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ));
            return 0;
        } catch (DomainException|JsonException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return 2;
        } catch (Throwable $exception) {
            $output->writeln('<error>迁移包生成失败：' . $exception->getMessage() . '</error>');
            return 3;
        }
    }
}
