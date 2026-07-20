<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\QmsExternalChangeCandidate;
use app\model\QmsExternalChangeEvent;
use app\service\ExternalChangeEventService;
use app\service\FieldAuditService;
use think\exception\HttpException;
use think\facade\Db;
use think\facade\Session;
use think\facade\View;

class PlanningRegulatoryCandidate extends BaseController
{
    public function index()
    {
        $query = QmsExternalChangeCandidate::where('soft_delete', 0);
        $status = trim((string)$this->request->param('review_status', ''));
        $sourceKey = trim((string)$this->request->param('source_key', ''));
        $keyword = trim((string)$this->request->param('keyword', ''));

        if ($status !== '') {
            $query->where('review_status', $status);
        }
        if ($sourceKey !== '') {
            $query->where('source_key', $sourceKey);
        }
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('title', '%' . $keyword . '%')
                    ->whereOr('announcement_number', 'like', '%' . $keyword . '%')
                    ->whereOr('evidence_summary', 'like', '%' . $keyword . '%');
            });
        }

        $items = $query->order('modified', 'desc')->order('last_seen_at', 'desc')->paginate(20);
        foreach ($items as $item) {
            $item->impact_badges_html = self::impactBadgesHtml(self::decodeJson($item->impact_analysis ?? ''));
            $item->source_trust = self::sourceTrustFor((string)$item->monitor_run_id);
            $item->source_trust_label = self::sourceTrustLabels()[$item->source_trust] ?? '来源状态未知';
            $item->source_label = self::sourceLabel((string)$item->source_key);
            $item->first_seen_date = substr((string)$item->first_seen_at, 0, 10);
        }

        View::assign('items', $items);
        View::assign('pages', $items->render());
        $this->assignCommonContext([
            'review_status' => $status,
            'source_key' => $sourceKey,
            'keyword' => $keyword,
        ]);

        return View::fetch('planning_regulatory_candidate/index');
    }

    public function view()
    {
        $candidate = $this->findCandidate();
        $sourceTrust = self::sourceTrustFor((string)$candidate->monitor_run_id);
        View::assign('record', $candidate);
        View::assign('impactRows', self::impactRows(self::decodeJson($candidate->impact_analysis ?? '')));
        View::assign('evidenceRefs', self::decodeJson($candidate->evidence_refs ?? ''));
        View::assign('sourceTrust', $sourceTrust);
        View::assign('sourceTrustLabel', self::sourceTrustLabels()[$sourceTrust] ?? '来源状态未知');
        View::assign('sourceLabel', self::sourceLabel((string)$candidate->source_key));
        View::assign('effectiveStatus', self::effectiveStatus((string)$candidate->effective_date));
        View::assign(
            'fieldChangeLogs',
            FieldAuditService::displayLogsFor('QmsExternalChangeCandidate', (string)$candidate->id)
        );
        $this->assignCommonContext();

        return View::fetch('planning_regulatory_candidate/view');
    }

    public function review()
    {
        if (!$this->request->isPost()) {
            Session::flash('warning', '请从候选详情页进行人工确认。');

            return redirect('/planning/regulatory-candidates');
        }

        $candidate = $this->findCandidate((string)$this->request->post('id', ''));
        $action = trim((string)$this->request->post('action', ''));
        $status = match ($action) {
            'confirm_applicable' => 'confirmed_applicable',
            'confirm_not_applicable' => 'confirmed_not_applicable',
            'defer' => 'deferred',
            default => '',
        };
        if ($status === '') {
            Session::flash('warning', '候选处置动作无效。');

            return redirect('/planning/regulatory-candidates/view?id=' . rawurlencode((string)$candidate->id));
        }

        if ($status === 'confirmed_applicable') {
            $clauseError = $this->validateReferencedClauseNumbers($candidate);
            if ($clauseError !== null) {
                Session::flash('validation_errors', [$clauseError]);
                Session::flash('warning', $clauseError);

                return redirect('/planning/regulatory-candidates/view?id=' . rawurlencode((string)$candidate->id));
            }
        }

        Db::transaction(function () use ($candidate, $status): void {
            $candidate->save([
                'review_status' => $status,
                'reviewed_by' => (string)Session::get('user.id', ''),
                'reviewed_at' => date('Y-m-d H:i:s'),
                'review_comment' => trim((string)$this->request->post('review_comment', '')),
            ]);
        });
        Session::flash('success', '候选人工确认状态已更新。正式受控对象仍需按变更流程人工处理。');

        return redirect('/planning/regulatory-candidates/view?id=' . rawurlencode((string)$candidate->id));
    }

    public function promote()
    {
        if (!$this->request->isPost()) {
            Session::flash('warning', '请从候选详情页转正式变更事件。');

            return redirect('/planning/regulatory-candidates');
        }

        $candidate = $this->findCandidate((string)$this->request->post('id', ''));
        if ((string)$candidate->promoted_event_id !== '') {
            Session::flash('warning', '该候选已转正式变更事件。');

            return redirect('/planning/change-events/view?id=' . rawurlencode((string)$candidate->promoted_event_id));
        }

        $event = Db::transaction(function () use ($candidate): QmsExternalChangeEvent {
            $event = new QmsExternalChangeEvent();
            $event->save([
                'id' => qms_uuid(),
                'company_id' => (string)$candidate->company_id,
                'event_code' => ExternalChangeEventService::nextEventCode(),
                'source_kind' => self::eventSourceKind((string)$candidate->source_key),
                'source_name' => (string)$candidate->title,
                'source_url' => (string)$candidate->source_url,
                'announcement_number' => (string)$candidate->announcement_number,
                'published_date' => $candidate->published_date ?: null,
                'effective_date' => $candidate->effective_date ?: null,
                'event_summary' => self::eventSummary($candidate),
                'graph_snapshot_hash' => (string)($candidate->graph_snapshot_hash ?: ExternalChangeEventService::currentGraphSnapshotHash()),
                'status' => ExternalChangeEventService::STATUS_REGISTERED,
                'created_by' => (string)Session::get('user.id', ''),
                'modified_by' => (string)Session::get('user.id', ''),
            ]);

            $candidate->save([
                'review_status' => 'promoted',
                'reviewed_by' => (string)Session::get('user.id', ''),
                'reviewed_at' => date('Y-m-d H:i:s'),
                'review_comment' => trim((string)$this->request->post('review_comment', '')),
                'promoted_event_id' => (string)$event->id,
                'promoted_at' => date('Y-m-d H:i:s'),
                'promotion_error_summary' => null,
            ]);

            return $event;
        });
        Session::flash('success', '已转入正式变更事件；体系文件、人员授权、模板和培训仍需人工评估后处理。');

        return redirect('/planning/change-events/view?id=' . rawurlencode((string)$event->id));
    }

    private function assignCommonContext(array $filters = []): void
    {
        View::assign('pageTitle', '法规候选池');
        View::assign('filters', array_merge(['review_status' => '', 'source_key' => '', 'keyword' => ''], $filters));
        View::assign('reviewStatusLabels', self::reviewStatusLabels());
        View::assign('applicabilityLabels', self::applicabilityLabels());
        View::assign('relevanceLabels', self::relevanceLabels());
        View::assign('conclusionLabels', self::conclusionLabels());
        View::assign('sourceTrustLabels', self::sourceTrustLabels());
    }

    private function findCandidate(string $candidateId = ''): QmsExternalChangeCandidate
    {
        $id = $candidateId !== '' ? trim($candidateId) : trim((string)$this->request->param('id', ''));
        $candidate = QmsExternalChangeCandidate::where('soft_delete', 0)->find($id);
        if (!$candidate) {
            throw new HttpException(404, '法规候选不存在');
        }

        return $candidate;
    }

    private function validateReferencedClauseNumbers(QmsExternalChangeCandidate $candidate): ?string
    {
        $posted = trim((string)$this->request->post('clause_numbers', ''));
        $numbers = [];
        if ($posted !== '') {
            foreach (preg_split('/[\s,;，；]+/u', $posted) ?: [] as $item) {
                $item = trim((string)$item);
                if ($item !== '') {
                    $numbers[] = $item;
                }
            }
        }

        $scanText = trim((string)($candidate->analysis_rationale ?? ''))
            . "\n"
            . trim((string)$this->request->post('review_comment', ''));
        if (preg_match_all('/\b\d+(?:\.\d+)+\b/', $scanText, $matches) > 0) {
            foreach ($matches[0] as $item) {
                $numbers[] = (string)$item;
            }
        }
        $numbers = array_values(array_unique($numbers));
        if ($numbers === []) {
            return null;
        }

        $existing = Db::name('qms_clauses')
            ->where('soft_delete', 0)
            ->whereIn('clause_number', $numbers)
            ->column('clause_number');
        $existing = array_map('strval', $existing);
        $missing = array_values(array_diff($numbers, $existing));
        if ($missing === []) {
            return null;
        }

        return '条款号不存在于现行条款库：' . implode('、', $missing);
    }

    private static function sourceTrustFor(string $monitorRunId): string
    {
        if ($monitorRunId === '') {
            return 'unknown';
        }
        $json = self::decodeJson(Db::name('qms_regulatory_monitor_runs')->where('id', $monitorRunId)->value('result_json'));

        return (string)($json['source_trust'] ?? 'unknown');
    }

    private static function impactRows(array $impact): array
    {
        $labels = [
            'cma_scope_mark' => '能力项目/CMA 标志',
            'qms_documents' => '体系文件',
            'personnel_authorization' => '人员授权',
            'equipment_calibration' => '设备校准',
            'lims_rules' => 'LIMS 规则',
            'training' => '培训',
        ];
        $rows = [];
        foreach ($labels as $key => $label) {
            $item = is_array($impact[$key] ?? null) ? $impact[$key] : [];
            $rows[] = [
                'key' => $key,
                'label' => $label,
                'conclusion' => (string)($item['conclusion'] ?? 'no_match'),
                'evidence' => array_values((array)($item['evidence'] ?? [])),
                'rule_ids' => array_values((array)($item['rule_ids'] ?? [])),
                'confidence' => $item['confidence'] ?? null,
                'confidence_label' => self::confidenceLabel($item['confidence'] ?? null),
                'suggested_action' => self::suggestedAction($key, (string)($item['conclusion'] ?? 'no_match')),
            ];
        }

        return $rows;
    }

    private static function impactBadgesHtml(array $impact): string
    {
        $html = [];
        foreach (self::impactRows($impact) as $row) {
            $conclusion = (string)$row['conclusion'];
            $class = match ($conclusion) {
                'likely' => 'bg-danger',
                'possible' => 'bg-warning text-dark',
                default => 'bg-secondary',
            };
            $label = self::conclusionLabels()[$conclusion] ?? $conclusion;
            $html[] = '<span class="badge ' . $class . ' me-1 mb-1">'
                . htmlspecialchars((string)$row['label'] . '：' . $label, ENT_QUOTES, 'UTF-8')
                . '</span>';
        }

        return implode('', $html);
    }

    private static function decodeJson(mixed $json): array
    {
        if (is_array($json)) {
            return $json;
        }
        if (!is_string($json)) {
            return [];
        }
        $decoded = json_decode(trim($json), true);

        return is_array($decoded) ? $decoded : [];
    }

    private static function reviewStatusLabels(): array
    {
        return [
            'pending' => '待人工确认',
            'confirmed_applicable' => '确认适用',
            'confirmed_not_applicable' => '确认不适用',
            'deferred' => '暂缓',
            'promoted' => '已转正式变更',
        ];
    }

    private static function applicabilityLabels(): array
    {
        return [
            'likely_applicable' => '初判可能适用',
            'needs_review' => '需人工复核',
            'likely_not_applicable' => '初判可能不适用',
        ];
    }

    private static function relevanceLabels(): array
    {
        return ['high' => '高', 'medium' => '中', 'low' => '低', 'unknown' => '未知'];
    }

    private static function conclusionLabels(): array
    {
        return ['likely' => '较可能影响', 'possible' => '可能影响', 'no_match' => '现有规则未命中'];
    }

    private static function sourceTrustLabels(): array
    {
        return [
            'official_primary' => '官方来源已核验',
            'official' => '官方来源已核验',
            'verified' => '来源已核验',
            'unverified' => '来源待核验',
            'unknown' => '来源状态未知',
        ];
    }

    private static function sourceLabel(string $sourceKey): string
    {
        $key = strtolower(trim($sourceKey));
        if (str_contains($key, 'samr')) {
            return '市场监管总局';
        }
        if (str_contains($key, 'cnas')) {
            return 'CNAS';
        }
        if (str_contains($key, 'gb')) {
            return '国家标准公开平台';
        }

        return $sourceKey !== '' ? $sourceKey : '未标明来源';
    }

    private static function confidenceLabel(mixed $confidence): string
    {
        if ($confidence === null || $confidence === '') {
            return '-';
        }
        $value = (float)$confidence;
        $percent = (int)round($value * 100);
        $level = $percent >= 85 ? '高' : ($percent >= 65 ? '中' : '低');

        return $level . '（' . $percent . '%）';
    }

    private static function suggestedAction(string $key, string $conclusion): string
    {
        if ($conclusion === 'no_match') {
            return '现有规则未命中，人工确认是否需要补充规则或记录为无需处理。';
        }

        return match ($key) {
            'cma_scope_mark' => '请技术负责人/授权签字人复核能力范围、CMA 标志和报告签发边界。',
            'qms_documents' => '请文件管理员复核外来文件台账、体系文件和受控模板。',
            'personnel_authorization' => '请质量负责人复核授权边界和岗位职责是否受影响。',
            'equipment_calibration' => '请设备管理员复核校准、期间核查或设备确认要求。',
            'lims_rules' => '请系统管理员复核 LIMS 规则或表单逻辑是否需调整。',
            'training' => '请培训负责人确认需培训岗位、培训内容和记录模板。',
            default => '请质量负责人确认是否进入正式变更事件。',
        };
    }

    private static function effectiveStatus(string $effectiveDate): array
    {
        if ($effectiveDate === '') {
            return ['label' => '未标明生效日期', 'class' => 'alert-secondary'];
        }
        $today = strtotime(date('Y-m-d'));
        $effective = strtotime($effectiveDate);
        if (!$effective) {
            return ['label' => '生效日期格式待核验', 'class' => 'alert-warning'];
        }
        $days = (int)floor(($today - $effective) / 86400);
        if ($days >= 0) {
            return ['label' => '已生效 ' . $days . ' 天，请尽快完成人工确认。', 'class' => $days > 30 ? 'alert-danger' : 'alert-warning'];
        }

        return ['label' => '距生效还有 ' . abs($days) . ' 天，请在生效前完成人工确认。', 'class' => 'alert-info'];
    }

    private static function eventSourceKind(string $sourceKey): string
    {
        $key = strtolower($sourceKey);
        if (str_contains($key, 'samr') || str_contains($key, 'cma')) {
            return 'samr';
        }
        if (str_contains($key, 'cnas')) {
            return 'cnas';
        }
        if (str_contains($key, 'gb') || str_contains($key, 'standard')) {
            return 'gb';
        }

        return 'other';
    }

    private static function eventSummary(QmsExternalChangeCandidate $candidate): string
    {
        $summary = trim((string)$candidate->evidence_summary);
        if ($summary === '') {
            $summary = '由法规候选池人工确认转入，需开展影响评估。';
        }

        return $summary . "\n\n系统边界：本事件仅建立正式变更台账，不自动修改体系文件、人员授权、记录模板、培训或 LIMS 规则。";
    }
}
