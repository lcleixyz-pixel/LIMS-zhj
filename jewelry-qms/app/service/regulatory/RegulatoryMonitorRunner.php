<?php
declare(strict_types=1);

namespace app\service\regulatory;

use think\facade\Db;

/**
 * Shared execution boundary for CLI and web initiated monitor runs.
 *
 * Dry-run deliberately executes the real monitor pipeline inside one ambient
 * transaction and always rolls it back.  Callers may not emulate dry-run by
 * skipping candidate creation or by deleting rows afterwards.
 */
final class RegulatoryMonitorRunner
{
    /** @return array<string, mixed> */
    public function run(
        RegulatoryMonitorService $service,
        string $triggerMode,
        ?array $sourceKeys,
        ?string $since,
        bool $dryRun,
        ?string $actorId = null
    ): array {
        if (!$dryRun) {
            return $service->run($triggerMode, $sourceKeys, $since, $actorId);
        }

        Db::startTrans();
        try {
            return $service->run($triggerMode, $sourceKeys, $since, $actorId);
        } finally {
            Db::rollback();
        }
    }
}
