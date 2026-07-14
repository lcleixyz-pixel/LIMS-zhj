<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\service\FieldAuditService;
use app\service\regulatory\RegulatoryCandidateReviewService;
use app\service\regulatory\RegulatoryExportService;
use InvalidArgumentException;
use RuntimeException;
use think\App;
use think\exception\HttpException;
use think\facade\Config;
use think\facade\Log;
use think\facade\Session;
use think\facade\View;

class PlanningRegulatoryMonitor extends BaseController
{
    private ?RegulatoryCandidateReviewService $reviewService;
    private ?RegulatoryExportService $exportService;

    public function __construct(
        App $app,
        ?RegulatoryCandidateReviewService $reviewService = null,
        ?RegulatoryExportService $exportService = null
    )
    {
        $this->reviewService = $reviewService;
        $this->exportService = $exportService;
        parent::__construct($app);
    }

    public function index()
    {
        $this->assertControllerRole(['admin', 'quality_manager']);
        $service = $this->service();
        try {
            $filters = [
                'review_status' => trim((string)$this->request->get('review_status', '')),
                'source_key' => trim((string)$this->request->get('source_key', '')),
            ];
            $items = $service->paginateCandidates($filters);
            View::assign([
                'pageTitle' => '法规动态监测',
                'items' => $items,
                'pages' => $items->render(),
                'recentRuns' => $service->recentRuns(),
                'sources' => $service->approvedSources(),
                'filters' => $filters,
                'reviewStatusLabels' => $this->reviewStatusLabels(),
                'runStatusLabels' => $this->runStatusLabels(),
                'currentRole' => $this->role(),
            ]);
        } catch (\Throwable $exception) {
            $this->safeFailure($exception, '加载法规候选失败');
            View::assign([
                'items' => [], 'pages' => '', 'recentRuns' => [], 'sources' => [],
                'filters' => ['review_status' => '', 'source_key' => ''],
                'reviewStatusLabels' => $this->reviewStatusLabels(),
                'runStatusLabels' => $this->runStatusLabels(),
                'currentRole' => $this->role(),
            ]);
        }

        return View::fetch('planning_regulatory_monitor/index');
    }

    public function show()
    {
        $this->assertControllerRole(['admin', 'quality_manager']);
        $service = $this->service();
        try {
            $candidate = $service->findCandidate((string)$this->request->get('id', ''));
        } catch (InvalidArgumentException|RuntimeException $exception) {
            throw new HttpException(404, '法规候选不存在或无权查看');
        }
        View::assign([
            'pageTitle' => '法规候选详情',
            'record' => $candidate,
            'safeSourceUrl' => $this->safeSourceUrl($candidate->toArray(), $service->approvedSources()),
            'impactAnalysis' => $this->normalizedImpacts((array)$candidate->impact_analysis),
            'versions' => $service->versionChain($candidate),
            'fieldChangeLogs' => FieldAuditService::logsFor('QmsExternalChangeCandidate', (string)$candidate->id),
            'reviewStatusLabels' => $this->reviewStatusLabels(),
            'impactLabels' => (array)Config::get('qms.regulatory_monitor.impact_labels', []),
            'conclusionLabels' => (array)Config::get('qms.regulatory_monitor.conclusion_labels', []),
            'currentRole' => $this->role(),
        ]);

        return View::fetch('planning_regulatory_monitor/show');
    }

    public function review()
    {
        $this->assertControllerRole(['quality_manager']);
        $candidateId = trim((string)$this->request->post('id', ''));
        try {
            $candidate = $this->service()->review(
                $candidateId,
                trim((string)$this->request->post('review_status', '')),
                (string)$this->request->post('review_comment', '')
            );
            $this->markAudit((string)$candidate->id, 'success');
            Session::flash('success', '人工复核结论已保存。');
        } catch (\Throwable $exception) {
            $this->safeFailure($exception, '保存法规候选复核失败');
        }

        return redirect('/planning/regulatory-monitor/show?id=' . rawurlencode($candidateId));
    }

    public function run()
    {
        $dryRun = $this->strictDryRun($this->request->post('dry_run', null));
        $this->assertControllerRole($dryRun ? ['admin', 'quality_manager'] : ['admin']);
        $sources = $this->request->post('source', []);
        if (!is_array($sources)) {
            $sources = [$sources];
        }
        try {
            $result = $this->service()->runManual(
                array_values($sources),
                trim((string)$this->request->post('since', '')) ?: null,
                $dryRun,
                (string)Session::get('user.id', '')
            );
            // DRY-RUN is an evidence-free rehearsal: its ambient transaction is
            // rolled back and it must not leave a route History row afterwards.
            if (!$dryRun) {
                $runStatus = (string)$result['status'];
                $this->markAudit(
                    (string)$result['run_id'],
                    $runStatus === 'completed' ? 'success' : 'failed',
                    $runStatus
                );
            }
            $message = $dryRun ? 'DRY-RUN 已完成，未保存运行或候选记录。' : '手工监测已完成。';
            if (in_array((string)$result['status'], ['partial_failed', 'failed'], true)) {
                Session::flash('warning', $message . '部分或全部来源失败，请查看运行健康摘要。');
            } else {
                Session::flash('success', $message);
            }
        } catch (\Throwable $exception) {
            $this->safeFailure($exception, '手工执行法规监测失败');
        }

        return redirect('/planning/regulatory-monitor');
    }

    public function export()
    {
        $this->assertControllerRole(['admin', 'quality_manager']);
        $candidateId = (string)$this->request->get('id', '');
        try {
            $service = $this->exportService();
            $packet = $service->exportCandidate($candidateId);
            $filename = $service->filename($candidateId);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            throw new HttpException(404, '法规候选不存在或无权导出');
        }

        return json(
            $packet,
            200,
            [
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'no-store',
                'X-Content-Type-Options' => 'nosniff',
            ],
            ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR]
        );
    }

    /** @param list<string> $allowed */
    private function assertControllerRole(array $allowed): void
    {
        if (!filter_var(Config::get('qms.regulatory_monitor.enabled', false), FILTER_VALIDATE_BOOL)) {
            throw new HttpException(404, '法规监测功能未启用');
        }
        if (!in_array($this->role(), $allowed, true)) {
            throw new HttpException(403, '无权执行法规监测操作');
        }
    }

    private function role(): string
    {
        return trim((string)Session::get('user.role', 'staff'));
    }

    private function strictDryRun(mixed $raw): bool
    {
        if ($raw === 1 || $raw === '1') {
            return true;
        }
        if ($raw === 0 || $raw === '0') {
            return false;
        }

        throw new InvalidArgumentException('dry_run 必须明确为 0 或 1');
    }

    /** @return array<string, array<string, mixed>> */
    private function normalizedImpacts(array $analysis): array
    {
        $normalized = [];
        foreach (['cma_scope_mark', 'qms_documents', 'personnel_authorization', 'equipment_calibration', 'lims_rules', 'training'] as $key) {
            $item = is_array($analysis[$key] ?? null) ? $analysis[$key] : [];
            $conclusion = (string)($item['conclusion'] ?? 'no_match');
            if (!in_array($conclusion, ['likely', 'possible', 'no_match'], true)) {
                $conclusion = 'no_match';
            }
            $normalized[$key] = [
                'conclusion' => $conclusion,
                'evidence' => is_array($item['evidence'] ?? null) ? $item['evidence'] : [],
                'rule_ids' => is_array($item['rule_ids'] ?? null) ? $item['rule_ids'] : [],
                'confidence' => is_numeric($item['confidence'] ?? null) ? (float)$item['confidence'] : 0.0,
            ];
        }

        return $normalized;
    }

    private function reviewStatusLabels(): array
    {
        return (array)Config::get('qms.regulatory_monitor.review_status_labels', []);
    }

    private function runStatusLabels(): array
    {
        return (array)Config::get('qms.regulatory_monitor.run_status_labels', []);
    }

    private function markAudit(string $recordId, string $outcome, string $runStatus = ''): void
    {
        $this->request->withMiddleware(['qms_regulatory_audit' => [
            'outcome' => $outcome,
            'record_id' => $recordId,
            'run_status' => $runStatus,
        ]]);
    }

    private function service(): RegulatoryCandidateReviewService
    {
        return $this->reviewService ??= new RegulatoryCandidateReviewService();
    }

    private function exportService(): RegulatoryExportService
    {
        return $this->exportService ??= new RegulatoryExportService();
    }

    private function safeSourceUrl(array $candidate, array $sources): ?string
    {
        $sourceUrl = trim((string)($candidate['source_url'] ?? ''));
        $sourceKey = (string)($candidate['source_key'] ?? '');
        $approvedEntry = trim((string)($sources[$sourceKey]['entry_url'] ?? ''));
        $candidateParts = parse_url($sourceUrl);
        $approvedParts = parse_url($approvedEntry);
        if (!is_array($candidateParts) || !is_array($approvedParts)) {
            return null;
        }
        if (array_key_exists('user', $candidateParts) || array_key_exists('pass', $candidateParts)) {
            return null;
        }
        if (strtolower((string)($candidateParts['scheme'] ?? '')) !== 'https') {
            return null;
        }
        if (strtolower((string)($candidateParts['host'] ?? '')) !== strtolower((string)($approvedParts['host'] ?? ''))) {
            return null;
        }
        if ((int)($candidateParts['port'] ?? 443) !== (int)($approvedParts['port'] ?? 443)) {
            return null;
        }

        return $sourceUrl;
    }

    private function safeFailure(\Throwable $exception, string $context): void
    {
        if ($exception instanceof InvalidArgumentException) {
            Session::flash('error', $exception->getMessage());
            return;
        }
        Log::error($context, ['exception' => $exception::class]);
        Session::flash('error', '操作失败，请查看服务日志。');
    }
}
