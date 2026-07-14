<?php
declare(strict_types=1);

namespace app\service\regulatory;

use app\model\QmsExternalChangeCandidate;
use app\service\NotificationService;
use InvalidArgumentException;
use RuntimeException;
use think\facade\Config;
use think\facade\Db;
use think\facade\Log;
use think\facade\Session;

final class RegulatoryCandidateReviewService
{
    public const REVIEW_STATUSES = [
        'confirmed_applicable',
        'confirmed_not_applicable',
        'deferred',
    ];

    private const VIEW_ROLES = ['admin', 'quality_manager'];
    private const MIN_COMMENT_LENGTH = 2;
    private const MAX_COMMENT_LENGTH = 1000;

    /** @return list<array<string, mixed>> */
    public function listCandidates(array $filters = [], int $limit = 100): array
    {
        $this->assertEnabled();
        $this->assertRole(self::VIEW_ROLES);
        $limit = max(1, min($limit, 200));
        $query = $this->candidateQuery($filters);

        return $query->order('published_date', 'desc')
            ->order('created', 'desc')
            ->order('id', 'asc')
            ->limit($limit)
            ->select()
            ->toArray();
    }

    public function paginateCandidates(array $filters = [], int $listRows = 20)
    {
        $this->assertEnabled();
        $this->assertRole(self::VIEW_ROLES);
        $listRows = max(1, min($listRows, 100));

        return $this->candidateQuery($filters)
            ->order('published_date', 'desc')
            ->order('created', 'desc')
            ->order('id', 'asc')
            ->paginate(['list_rows' => $listRows, 'query' => $filters]);
    }

    private function candidateQuery(array $filters)
    {
        $query = QmsExternalChangeCandidate::where($this->visibilityScope());

        $reviewStatus = trim((string)($filters['review_status'] ?? ''));
        if ($reviewStatus !== '') {
            $allowed = array_merge(['pending', 'promoted'], self::REVIEW_STATUSES);
            if (!in_array($reviewStatus, $allowed, true)) {
                throw new InvalidArgumentException('无效的候选复核状态筛选');
            }
            $query->where('review_status', $reviewStatus);
        }
        $sourceKey = trim((string)($filters['source_key'] ?? ''));
        if ($sourceKey !== '') {
            $this->assertApprovedSources([$sourceKey]);
            $query->where('source_key', $sourceKey);
        }

        return $query;
    }

    public function findCandidate(string $candidateId): QmsExternalChangeCandidate
    {
        $this->assertEnabled();
        $this->assertRole(self::VIEW_ROLES);
        $candidateId = trim($candidateId);
        if ($candidateId === '') {
            throw new InvalidArgumentException('候选 ID 不得为空');
        }
        $candidate = QmsExternalChangeCandidate::where($this->visibilityScope())->find($candidateId);
        if (!$candidate) {
            throw new RuntimeException('法规候选不存在或无权查看');
        }

        return $candidate;
    }

    /** @return list<array<string, mixed>> */
    public function versionChain(QmsExternalChangeCandidate $candidate): array
    {
        $this->assertEnabled();
        $this->assertRole(self::VIEW_ROLES);

        return QmsExternalChangeCandidate::where($this->visibilityScope())
            ->where('source_key', (string)$candidate->source_key)
            ->where('source_item_key', (string)$candidate->source_item_key)
            ->order('first_seen_at', 'desc')
            ->order('id', 'asc')
            ->select()
            ->toArray();
    }

    /** @return list<array<string, mixed>> */
    public function recentRuns(int $limit = 10): array
    {
        $this->assertEnabled();
        $this->assertRole(self::VIEW_ROLES);
        $limit = max(1, min($limit, 20));

        return Db::name('qms_regulatory_monitor_runs')
            ->where($this->visibilityScope())
            ->order('started_at', 'desc')
            ->order('id', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();
    }

    /** @return array<string, array<string, mixed>> */
    public function approvedSources(): array
    {
        $this->assertEnabled();
        $this->assertRole(self::VIEW_ROLES);

        return (new RegulatorySourceRegistry())->all();
    }

    public function review(string $candidateId, string $reviewStatus, string $comment): QmsExternalChangeCandidate
    {
        $this->assertEnabled();
        $this->assertRole(['quality_manager']);
        $candidateId = trim($candidateId);
        if ($candidateId === '') {
            throw new InvalidArgumentException('候选 ID 不得为空');
        }
        if (!in_array($reviewStatus, self::REVIEW_STATUSES, true)) {
            throw new InvalidArgumentException('仅允许提交已确认相关、已确认不相关或暂缓');
        }
        $comment = trim($comment);
        $length = mb_strlen($comment, 'UTF-8');
        if ($length < self::MIN_COMMENT_LENGTH || $length > self::MAX_COMMENT_LENGTH) {
            throw new InvalidArgumentException('复核理由必须为 2–1000 个字符');
        }
        $reviewedBy = trim((string)Session::get('user.id', ''));
        if ($reviewedBy === '') {
            throw new RuntimeException('缺少已登录复核人身份');
        }

        return Db::transaction(function () use ($candidateId, $reviewStatus, $comment, $reviewedBy) {
            $candidate = QmsExternalChangeCandidate::where($this->visibilityScope())
                ->lock(true)
                ->find($candidateId);
            if (!$candidate) {
                throw new RuntimeException('法规候选不存在或无权复核');
            }
            if ((string)$candidate->review_status === 'promoted' || trim((string)$candidate->promoted_event_id) !== '') {
                throw new RuntimeException('已晋升的候选不得重新复核');
            }

            $candidate->save([
                'review_status' => $reviewStatus,
                'review_comment' => $comment,
                'reviewed_by' => $reviewedBy,
                'reviewed_at' => date('Y-m-d H:i:s'),
            ]);

            $candidate->refresh();

            return $candidate;
        });
    }

    /** @return array<string, mixed> */
    public function runManual(?array $sourceKeys, ?string $since, bool $dryRun): array
    {
        $this->assertEnabled();
        if ($dryRun) {
            $this->assertRole(['admin', 'quality_manager']);
        } else {
            $this->assertRole(['admin']);
        }
        $sourceKeys = $this->assertApprovedSources($sourceKeys);
        $candidateService = $dryRun ? new RegulatoryCandidateService(ownsTransaction: false) : null;
        $result = (new RegulatoryMonitorRunner())->run(
            new RegulatoryMonitorService(candidateService: $candidateService),
            'manual',
            $sourceKeys,
            $since,
            $dryRun
        );

        if (!$dryRun) {
            try {
                NotificationService::notifyRegulatoryMonitorFailure($result);
            } catch (\Throwable $exception) {
                Log::error('[Regulatory Monitor UI] notification failure: ' . $exception::class);
            }
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private function visibilityScope(): array
    {
        $companyId = trim((string)Config::get('qms.company_id'));
        if ($companyId === '') {
            throw new RuntimeException('法规监测缺少 company_id 配置');
        }

        return ['company_id' => $companyId, 'publish' => 1, 'soft_delete' => 0];
    }

    /** @return list<string>|null */
    private function assertApprovedSources(?array $sourceKeys): ?array
    {
        if ($sourceKeys === null) {
            return null;
        }
        if ($sourceKeys === []) {
            throw new InvalidArgumentException('来源不得为空');
        }
        $registry = new RegulatorySourceRegistry();
        $approved = [];
        foreach ($sourceKeys as $sourceKey) {
            if (!is_string($sourceKey)) {
                throw new InvalidArgumentException('来源包含未批准项');
            }
            $sourceKey = trim($sourceKey);
            if (preg_match('/\A[a-z][a-z0-9_]{0,99}\z/D', $sourceKey) !== 1) {
                throw new InvalidArgumentException('来源包含未批准项');
            }
            try {
                $registry->source($sourceKey);
            } catch (\Throwable $exception) {
                throw new InvalidArgumentException('来源包含未批准项', 0, $exception);
            }
            $approved[$sourceKey] = $sourceKey;
        }

        return array_values($approved);
    }

    private function assertEnabled(): void
    {
        if (!filter_var(Config::get('qms.regulatory_monitor.enabled', false), FILTER_VALIDATE_BOOL)) {
            throw new RuntimeException('法规监测功能未启用');
        }
    }

    /** @param list<string> $allowed */
    private function assertRole(array $allowed): void
    {
        if (!in_array($this->role(), $allowed, true)) {
            throw new RuntimeException('无权执行法规监测操作');
        }
    }

    private function role(): string
    {
        return trim((string)Session::get('user.role', 'staff'));
    }
}
