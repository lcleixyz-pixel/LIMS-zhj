<?php
declare(strict_types=1);

namespace app\command;

use app\service\NotificationService;
use app\service\regulatory\RegulatorySourceRegistry;
use app\service\regulatory\RegulatoryMonitorService;
use app\service\regulatory\RegulatoryCandidateService;
use app\service\regulatory\RegulatoryMonitorRunner;
use Closure;
use InvalidArgumentException;
use RuntimeException;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\facade\Log;

class MonitorRegulatoryChanges extends Command
{
    private Closure $failureNotifier;
    private ?Closure $serviceFactory;

    public function __construct(?callable $failureNotifier = null, ?callable $serviceFactory = null)
    {
        $this->failureNotifier = Closure::fromCallable(
            $failureNotifier ?? [NotificationService::class, 'notifyRegulatoryMonitorFailure']
        );
        $this->serviceFactory = $serviceFactory !== null ? Closure::fromCallable($serviceFactory) : null;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('qms:monitor-regulatory-changes')
            ->addOption('source', null, Option::VALUE_OPTIONAL, '来源 key，多个以逗号分隔')
            ->addOption('since', null, Option::VALUE_OPTIONAL, '仅处理发布日期不早于 YYYY-MM-DD 的条目')
            ->addOption('dry-run', null, Option::VALUE_NONE, '执行完整流程后回滚数据库')
            ->addOption('fixture-dir', null, Option::VALUE_OPTIONAL, '测试环境离线 fixture 目录')
            ->addOption('scheduled', null, Option::VALUE_NONE, '将运行记录为月度调度触发')
            ->setDescription('监测已批准的法规来源并生成隔离候选');
    }

    protected function execute(Input $input, Output $output): int
    {
        try {
            $sourceKeys = $this->sourceKeys($input);
            $since = $this->since($input);
            $fixtureFetcher = $this->fixtureFetcher($input);
            $dryRun = $input->getOption('dry-run') === true;
            $service = $this->serviceFactory !== null
                ? ($this->serviceFactory)($fixtureFetcher, $dryRun)
                : new RegulatoryMonitorService(
                    sourceFetcher: $fixtureFetcher,
                    candidateService: $dryRun
                        ? new RegulatoryCandidateService(ownsTransaction: false)
                        : null
                );
            if (!$service instanceof RegulatoryMonitorService) {
                throw new RuntimeException('法规监测服务工厂返回类型无效');
            }
            $result = (new RegulatoryMonitorRunner())->run(
                $service,
                $input->getOption('scheduled') === true ? 'scheduled' : 'manual',
                $sourceKeys,
                $since,
                $dryRun
            );
        } catch (InvalidArgumentException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return 1;
        } catch (\Throwable $exception) {
            Log::error('[Regulatory Monitor Command] system failure: ' . $exception::class);
            $output->writeln('<error>法规监测未能完成，请查看服务日志。</error>');
            return 1;
        }

        if (!$dryRun) {
            try {
                ($this->failureNotifier)($result);
            } catch (\Throwable $exception) {
                Log::error('[Regulatory Monitor Command] notification failure: ' . $exception::class);
            }
        }

        $output->writeln($dryRun
            ? '法规监测 DRY-RUN 执行完成（未持久化）。'
            : '法规监测执行完成。');
        $output->writeln('run_id: ' . (string)$result['run_id']);
        $output->writeln('status: ' . (string)$result['status']);
        $output->writeln(sprintf(
            'sources: %d, success: %d, failed: %d, candidates_new: %d, candidates_existing: %d',
            (int)$result['source_count'],
            (int)$result['success_count'],
            (int)$result['failure_count'],
            (int)$result['candidate_new_count'],
            (int)$result['candidate_existing_count']
        ));

        return match ((string)$result['status']) {
            'completed' => 0,
            'partial_failed' => 2,
            default => 1,
        };
    }

    /** @return list<string>|null */
    private function sourceKeys(Input $input): ?array
    {
        if (!$input->hasParameterOption('--source')) {
            return null;
        }
        $raw = $input->getOption('source');
        if (!is_string($raw) || trim($raw) === '') {
            throw new InvalidArgumentException('--source 不得为空');
        }

        $keys = explode(',', $raw);
        $registry = new RegulatorySourceRegistry();
        $resolved = [];
        foreach ($keys as $key) {
            $key = trim($key);
            if ($key === '' || preg_match('/\A[a-z][a-z0-9_]{0,99}\z/D', $key) !== 1) {
                throw new InvalidArgumentException('--source 包含空值或非法来源');
            }
            try {
                $registry->source($key);
            } catch (\Throwable $exception) {
                throw new InvalidArgumentException('--source 包含未批准来源', 0, $exception);
            }
            $resolved[$key] = $key;
        }

        return array_values($resolved);
    }

    private function since(Input $input): ?string
    {
        if (!$input->hasParameterOption('--since')) {
            return null;
        }
        $value = $input->getOption('since');
        if (!is_string($value) || preg_match('/\A\d{4}-\d{2}-\d{2}\z/D', $value) !== 1) {
            throw new InvalidArgumentException('--since 必须是严格的 YYYY-MM-DD 日期');
        }
        [$year, $month, $day] = array_map('intval', explode('-', $value));
        if (!checkdate($month, $day, $year)) {
            throw new InvalidArgumentException('--since 必须是有效日期');
        }

        return $value;
    }

    private function fixtureFetcher(Input $input): ?callable
    {
        if (!$input->hasParameterOption('--fixture-dir')) {
            return null;
        }
        if (strtolower(trim((string)getenv('APP_ENV'))) !== 'test') {
            throw new InvalidArgumentException('--fixture-dir 仅允许在 APP_ENV=test 使用');
        }

        $raw = $input->getOption('fixture-dir');
        if (!is_string($raw) || trim($raw) === '') {
            throw new InvalidArgumentException('--fixture-dir 不得为空');
        }
        if (preg_match('#(?:^|[\\\\/])\.\.(?:[\\\\/]|$)#D', $raw) === 1) {
            throw new InvalidArgumentException('--fixture-dir 不得包含路径穿越');
        }
        $directory = realpath($raw);
        if (!is_string($directory) || !is_dir($directory) || !is_readable($directory)) {
            throw new InvalidArgumentException('--fixture-dir 必须是可读目录');
        }
        $prefix = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return static function (array $source) use ($prefix): string {
            $sourceKey = (string)($source['key'] ?? '');
            $path = $prefix . $sourceKey . '.html';
            $realPath = realpath($path);
            if (!is_string($realPath)
                || !str_starts_with($realPath, $prefix)
                || !is_file($realPath)
                || !is_readable($realPath)
            ) {
                throw new RuntimeException('离线 fixture 不存在或越界');
            }
            $body = file_get_contents($realPath);
            if (!is_string($body)) {
                throw new RuntimeException('离线 fixture 无法读取');
            }

            return $body;
        };
    }
}
