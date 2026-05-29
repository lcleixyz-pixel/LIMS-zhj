<?php
declare(strict_types=1);

namespace app\service;

use app\model\Document;
use app\model\DocumentDistribution;
use app\model\DocumentReview;
use think\facade\Config;
use think\facade\Db;
use think\facade\Session;

class DocumentControlService
{
    public static function distribute(string $documentId, array $userIds, ?string $siteId = null, string $remarks = ''): int
    {
        $document = Document::find($documentId);
        if (!$document) {
            return 0;
        }

        $created = 0;
        $now = date('Y-m-d H:i:s');
        $siteId = $siteId !== '' ? $siteId : null;
        foreach (array_unique(array_filter($userIds)) as $userId) {
            $existsQuery = DocumentDistribution::where('document_id', $documentId)
                ->where('user_id', (string)$userId)
                ->where('soft_delete', 0);
            $siteId === null ? $existsQuery->whereNull('site_id') : $existsQuery->where('site_id', $siteId);
            if ($existsQuery->find()) {
                continue;
            }

            DocumentDistribution::create([
                'id' => qms_uuid(),
                'company_id' => Config::get('qms.company_id'),
                'document_id' => $documentId,
                'user_id' => (string)$userId,
                'site_id' => $siteId,
                'distributed_at' => $now,
                'remarks' => $remarks,
                'publish' => 1,
                'soft_delete' => 0,
                'created' => $now,
                'created_by' => Session::get('user.id'),
            ]);
            $created++;
        }

        return $created;
    }

    public static function confirmReceipt(string $distributionId, ?string $userId = null): bool
    {
        $distribution = self::findDistributionForUser($distributionId, $userId);
        if (!$distribution) {
            return false;
        }

        if (!$distribution->confirmed_at) {
            $distribution->confirmed_at = date('Y-m-d H:i:s');
            $distribution->save();
        }

        return true;
    }

    public static function confirmRecall(string $distributionId, ?string $userId = null): bool
    {
        $distribution = self::findDistributionForUser($distributionId, $userId);
        if (!$distribution) {
            return false;
        }

        if (!$distribution->recalled_at) {
            $distribution->recalled_at = date('Y-m-d H:i:s');
            $distribution->save();
        }

        return true;
    }

    public static function recordReview(
        string $documentId,
        string $result,
        string $note,
        ?string $nextReviewDate = null,
        ?string $reviewedBy = null
    ): ?DocumentReview {
        $document = Document::find($documentId);
        if (!$document || !in_array($result, ['continue', 'revise', 'obsolete'], true)) {
            return null;
        }
        $note = trim($note) !== '' ? trim($note) : '未填写评审说明';

        return Db::transaction(function () use ($document, $result, $note, $nextReviewDate, $reviewedBy) {
            $review = DocumentReview::create([
                'id' => qms_uuid(),
                'company_id' => Config::get('qms.company_id'),
                'document_id' => $document->id,
                'review_date' => date('Y-m-d'),
                'result' => $result,
                'review_note' => $note,
                'next_review_date' => $nextReviewDate ?: null,
                'reviewed_by' => $reviewedBy ?: Session::get('user.id'),
                'publish' => 1,
                'soft_delete' => 0,
                'created' => date('Y-m-d H:i:s'),
                'created_by' => Session::get('user.id'),
            ]);

            if ($result === 'continue' && $nextReviewDate) {
                $document->review_date = $nextReviewDate;
                $document->save();
            }
            if ($result === 'obsolete') {
                $document->status = 'obsolete';
                $document->publish = 0;
                $document->save();
                self::notifyRecallForDocument((string)$document->id);
            }

            return $review;
        });
    }

    public static function notifyRecallForDocument(string $documentId): int
    {
        $document = Document::find($documentId);
        if (!$document) {
            return 0;
        }

        $userIds = DocumentDistribution::where('document_id', $documentId)
            ->where('soft_delete', 0)
            ->whereNull('recalled_at')
            ->column('user_id');
        $userIds = array_values(array_unique(array_filter($userIds)));
        if ($userIds === []) {
            return 0;
        }

        NotificationService::notifyUsers(
            '文件回收确认',
            "文件「{$document->title}」已作废，请确认回收受控副本。",
            'document',
            $userIds,
            'document',
            'view',
            $documentId
        );

        return count($userIds);
    }

    protected static function findDistributionForUser(string $distributionId, ?string $userId): ?DocumentDistribution
    {
        $query = DocumentDistribution::where('id', $distributionId)->where('soft_delete', 0);
        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->find();
    }
}
