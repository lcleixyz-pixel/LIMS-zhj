<?php
declare(strict_types=1);

namespace app\service;

use app\model\History;
use app\model\QmsExternalChangeCandidate;
use app\model\QmsExternalChangeEvent;
use app\service\regulatory\RegulatorySourceRegistry;
use app\service\regulatory\RegulatoryUrlNormalizer;
use think\facade\Config;
use think\facade\Db;
use think\facade\Log;
use think\facade\Session;

class RegulatoryPromotionDomainException extends \RuntimeException
{
}

class ExternalChangeEventService
{
    public const STATUS_REGISTERED = 'registered';
    public const STATUS_ASSESSING = 'assessing';
    public const STATUS_REVISING = 'revising';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_EXEMPTED = 'exempted';

    public static function sourceKindLabels(): array
    {
        return [
            'cnas' => 'CNAS',
            'samr' => '市场监管总局/CMA',
            'standard_platform' => '标准公开平台',
            'gb' => '国家标准',
            'internal' => '内部查新',
            'other' => '其他',
        ];
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_REGISTERED => '已登记',
            self::STATUS_ASSESSING => '影响评估中',
            self::STATUS_REVISING => '修订处理中',
            self::STATUS_CLOSED => '已关闭',
            self::STATUS_EXEMPTED => '不适用归档',
        ];
    }

    public static function nextEventCode(): string
    {
        $year = date('Y');
        $prefix = 'QMS-CHG-' . $year . '-';
        $last = QmsExternalChangeEvent::where('event_code', 'like', $prefix . '%')
            ->order('event_code', 'desc')
            ->value('event_code');
        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', (string)$last, $match)) {
            $seq = (int)$match[1] + 1;
        }

        return $prefix . str_pad((string)$seq, 3, '0', STR_PAD_LEFT);
    }

    public static function normalizeInput(array $data, bool $isCreate = true): array
    {
        $allowed = [
            'event_code', 'source_kind', 'source_name', 'source_url', 'announcement_number',
            'old_source_id', 'new_source_id', 'old_version', 'new_version',
            'published_date', 'effective_date', 'event_summary', 'graph_snapshot_hash',
            'status', 'close_reason',
        ];
        $normalized = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $normalized[$field] = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
            }
        }

        foreach (['old_source_id', 'new_source_id', 'published_date', 'effective_date'] as $nullableField) {
            if (($normalized[$nullableField] ?? '') === '') {
                $normalized[$nullableField] = null;
            }
        }

        if ($isCreate) {
            if (($normalized['event_code'] ?? '') === '') {
                $normalized['event_code'] = self::nextEventCode();
            }
            if (($normalized['status'] ?? '') === '') {
                $normalized['status'] = self::STATUS_REGISTERED;
            }
            if (($normalized['graph_snapshot_hash'] ?? '') === '') {
                $normalized['graph_snapshot_hash'] = self::currentGraphSnapshotHash();
            }
        } else {
            unset($normalized['event_code'], $normalized['status'], $normalized['graph_snapshot_hash']);
        }

        return $normalized;
    }

    public static function validateData(array $data, bool $isCreate = true): array
    {
        $errors = [];
        $kindLabels = self::sourceKindLabels();
        $statusLabels = self::statusLabels();

        if (trim((string)($data['source_kind'] ?? '')) === '') {
            $errors[] = '请选择来源类型';
        } elseif (!array_key_exists((string)$data['source_kind'], $kindLabels)) {
            $errors[] = '来源类型不在允许范围内';
        }

        if (trim((string)($data['source_name'] ?? '')) === '') {
            $errors[] = '请填写公告/依据名称';
        }

        if (trim((string)($data['event_summary'] ?? '')) === '') {
            $errors[] = '请填写变更摘要';
        }

        if ($isCreate && trim((string)($data['event_code'] ?? '')) !== '') {
            $exists = QmsExternalChangeEvent::where('event_code', trim((string)$data['event_code']))
                ->where('soft_delete', 0)
                ->count();
            if ((int)$exists > 0) {
                $errors[] = '事件编号已存在';
            }
        }

        if (isset($data['status']) && $data['status'] !== '' && !array_key_exists((string)$data['status'], $statusLabels)) {
            $errors[] = '事件状态不在允许范围内';
        }

        foreach (['published_date' => '发布日期', 'effective_date' => '生效日期'] as $field => $label) {
            $value = trim((string)($data[$field] ?? ''));
            if ($value !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $errors[] = $label . '格式应为 YYYY-MM-DD';
            }
        }

        return $errors;
    }

    public static function transition(QmsExternalChangeEvent $event, string $action, string $closeReason = ''): QmsExternalChangeEvent
    {
        $target = match ($action) {
            'assess' => self::STATUS_ASSESSING,
            'revise' => self::STATUS_REVISING,
            'close' => self::STATUS_CLOSED,
            'exempt' => self::STATUS_EXEMPTED,
            'reopen' => self::STATUS_REGISTERED,
            default => '',
        };

        if ($target === '') {
            throw new \InvalidArgumentException('不支持的状态动作');
        }

        $data = ['status' => $target];
        if (in_array($target, [self::STATUS_CLOSED, self::STATUS_EXEMPTED], true)) {
            $data['close_reason'] = $closeReason !== '' ? $closeReason : ($target === self::STATUS_CLOSED ? '影响评估已闭环' : '评估为不适用');
        }
        if ($target === self::STATUS_REGISTERED) {
            $data['close_reason'] = null;
        }

        $event->save($data);

        return $event;
    }

    public static function promoteRegulatoryCandidate(string $candidateId, string $actorId): QmsExternalChangeEvent
    {
        self::assertPromotionIdentity($candidateId, $actorId);

        try {
            return Db::transaction(function () use ($candidateId, $actorId): QmsExternalChangeEvent {
                $companyId = trim((string)Config::get('qms.company_id'));
                if ($companyId === '') {
                    throw new RegulatoryPromotionDomainException('法规候选晋升缺少机构配置');
                }

                $candidate = QmsExternalChangeCandidate::where('company_id', $companyId)
                    ->where('publish', 1)
                    ->where('soft_delete', 0)
                    ->lock(true)
                    ->find($candidateId);
                if (!$candidate) {
                    throw new RegulatoryPromotionDomainException('法规候选不存在或无权晋升');
                }

                $status = trim((string)$candidate->review_status);
                $promotedEventId = trim((string)$candidate->promoted_event_id);
                if ($status === 'promoted') {
                    if ($promotedEventId === '') {
                        throw new RegulatoryPromotionDomainException('法规候选晋升状态异常，请人工核查');
                    }

                    return self::findValidPromotedEvent($promotedEventId, $companyId);
                }
                if ($promotedEventId !== '') {
                    throw new RegulatoryPromotionDomainException('法规候选晋升关联异常，请人工核查');
                }
                if ($status !== 'confirmed_applicable') {
                    throw new RegulatoryPromotionDomainException('仅已确认相关的法规候选可以晋升');
                }

                $officialUrl = self::officialCandidateUrl($candidate);
                $eventData = self::normalizeInput([
                    'source_kind' => self::sourceKindForCandidate((string)$candidate->source_key),
                    'source_name' => self::safeSummaryText((string)$candidate->title, 300),
                    'source_url' => $officialUrl,
                    'announcement_number' => self::safeSummaryText((string)$candidate->announcement_number, 120),
                    'published_date' => $candidate->published_date,
                    'effective_date' => $candidate->effective_date,
                    'event_summary' => self::promotionSummary($candidate, $officialUrl),
                    'graph_snapshot_hash' => self::candidateGraphSnapshotHash($candidate),
                    'status' => self::STATUS_REGISTERED,
                ], true);
                $validationErrors = self::validateData($eventData, true);
                if ($validationErrors !== []) {
                    throw new RegulatoryPromotionDomainException('法规候选无法建立正式变更事件，请核对候选字段');
                }

                $event = static::persistPromotedEvent($eventData);
                $eventId = trim((string)$event->id);
                if ($eventId === '') {
                    throw new \RuntimeException('persisted promotion event has no id');
                }

                $candidate->save([
                    'review_status' => 'promoted',
                    'promoted_event_id' => $eventId,
                    'promoted_at' => date('Y-m-d H:i:s'),
                    'promotion_error_summary' => null,
                ]);
                static::writePromotionHistory($candidateId, $eventId, $actorId);

                return $event;
            });
        } catch (RegulatoryPromotionDomainException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('[Regulatory Promotion] failure exception=' . $exception::class);
            throw new \RuntimeException('法规候选晋升失败，请稍后重试');
        }
    }

    /** @param array<string, mixed> $data */
    protected static function persistPromotedEvent(array $data): QmsExternalChangeEvent
    {
        $event = new QmsExternalChangeEvent();
        $event->save($data);

        return $event;
    }

    protected static function writePromotionHistory(string $candidateId, string $eventId, string $actorId): void
    {
        History::create([
            'id' => qms_uuid(),
            'model_name' => 'QmsExternalChangeCandidate',
            'controller_name' => 'ExternalChangeEventService',
            'action' => 'promoteRegulatoryCandidate',
            'record_id' => $candidateId,
            'user_id' => $actorId,
            'details' => implode(' ', [
                'outcome=success',
                'candidate_id=' . $candidateId,
                'event_id=' . $eventId,
                'actor_id=' . $actorId,
            ]),
            'created' => date('Y-m-d H:i:s'),
        ]);
    }

    private static function assertPromotionIdentity(string $candidateId, string $actorId): void
    {
        if (trim((string)Session::get('user.role', 'staff')) !== 'quality_manager') {
            throw new RegulatoryPromotionDomainException('仅质量负责人可以晋升法规候选');
        }
        if ($actorId !== trim($actorId)
            || preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:@-]{0,35}\z/D', $actorId) !== 1
        ) {
            throw new RegulatoryPromotionDomainException('actor_id 必须是 1–36 位安全标识符');
        }
        if (trim((string)Session::get('user.id', '')) !== $actorId) {
            throw new RegulatoryPromotionDomainException('晋升操作人与当前登录身份不一致');
        }
        if ($candidateId !== trim($candidateId)
            || preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:@-]{0,35}\z/D', $candidateId) !== 1
        ) {
            throw new RegulatoryPromotionDomainException('候选 ID 格式无效');
        }
    }

    private static function findValidPromotedEvent(string $eventId, string $companyId): QmsExternalChangeEvent
    {
        $event = QmsExternalChangeEvent::where('company_id', $companyId)
            ->where('publish', 1)
            ->where('soft_delete', 0)
            ->lock(true)
            ->find($eventId);
        if (!$event) {
            throw new RegulatoryPromotionDomainException('法规候选关联事件无效，请人工核查');
        }

        return $event;
    }

    private static function sourceKindForCandidate(string $sourceKey): string
    {
        return match ($sourceKey) {
            'cnas_lab_notice', 'cnas_lab_rules' => 'cnas',
            'samr_rkjcs_notice', 'xinjiang_samr_notice', 'cma_capability_query' => 'samr',
            default => 'other',
        };
    }

    private static function officialCandidateUrl(QmsExternalChangeCandidate $candidate): ?string
    {
        $url = trim((string)$candidate->source_url);
        if ($url === '') {
            return null;
        }
        try {
            $registry = new RegulatorySourceRegistry();

            return RegulatoryUrlNormalizer::normalize(
                $url,
                $registry->allowedHosts(trim((string)$candidate->source_key))
            );
        } catch (\Throwable) {
            return null;
        }
    }

    private static function candidateGraphSnapshotHash(QmsExternalChangeCandidate $candidate): string
    {
        $hash = strtolower(trim((string)$candidate->graph_snapshot_hash));

        return preg_match('/\A[a-f0-9]{64}\z/D', $hash) === 1 ? $hash : self::currentGraphSnapshotHash();
    }

    private static function promotionSummary(QmsExternalChangeCandidate $candidate, ?string $officialUrl): string
    {
        $sourceKey = self::safeSummaryText((string)$candidate->source_key, 100);
        $announcement = self::safeSummaryText((string)$candidate->announcement_number, 120);
        $url = self::safeSummaryText((string)$officialUrl, 500);
        $evidence = self::safeSummaryText((string)$candidate->evidence_summary, 500);

        return implode('；', array_filter([
            '机器发现/规则初判，待正式影响评估',
            '本记录仅保留候选证据，不得视为适用性正式评估',
            '来源=' . ($sourceKey !== '' ? $sourceKey : 'unknown'),
            $announcement !== '' ? '文号=' . $announcement : '',
            $url !== '' ? '官方URL=' . $url : '',
            $evidence !== '' ? '证据摘要=' . $evidence : '',
        ]));
    }

    private static function safeSummaryText(string $value, int $maxLength): string
    {
        for ($iteration = 0; $iteration < 3; $iteration++) {
            $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decoded === $value) {
                break;
            }
            $value = $decoded;
        }
        $value = strip_tags($value);
        $value = preg_replace('/javascript\s*:/iu', '', $value) ?? '';
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '';
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? '';

        return mb_substr($value, 0, $maxLength, 'UTF-8');
    }

    public static function currentGraphSnapshotHash(): string
    {
        $basis = [
            'qms_sources' => self::tableMetric('qms_sources'),
            'qms_clauses' => self::tableMetric('qms_clauses'),
            'qms_document_blocks' => self::tableMetric('qms_document_blocks'),
            'qms_document_block_links' => self::tableMetric('qms_document_block_links'),
            'record_form_templates' => self::tableMetric('record_form_templates'),
        ];

        return hash('sha256', json_encode($basis, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: serialize($basis));
    }

    private static function tableMetric(string $table): array
    {
        try {
            return [
                'rows' => (int)Db::name($table)->where('soft_delete', 0)->count(),
                'max_modified' => (string)(Db::name($table)->where('soft_delete', 0)->max('modified') ?: ''),
                'max_created' => (string)(Db::name($table)->where('soft_delete', 0)->max('created') ?: ''),
            ];
        } catch (\Throwable $exception) {
            return ['rows' => 0, 'max_modified' => '', 'max_created' => '', 'unavailable' => true];
        }
    }
}
