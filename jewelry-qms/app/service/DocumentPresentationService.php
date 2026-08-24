<?php
declare(strict_types=1);

namespace app\service;

final class DocumentPresentationService
{
    public static function businessNumber(string $number): string
    {
        $original = trim($number);
        $number = preg_replace('/^SIM-GOV03-/', '', $original) ?? $original;
        $number = preg_replace('/^XZTC\//', '', $number) ?? $number;
        $number = preg_replace('/-(?:19|20)\d{2}$/', '', $number) ?? $number;

        return trim($number) !== '' ? trim($number) : $original;
    }

    public static function dailyStatusLabel(string $status): string
    {
        return $status === 'obsolete'
            ? '已作废 · 仅供追溯'
            : '当前治理阅读版';
    }

    public static function statusLabel(string $status, bool $trialMode = false): string
    {
        return match ($status) {
            'draft' => '修订草稿',
            'reviewing' => '签批中',
            'trial_ready' => '当前试运行版本',
            'published' => $trialMode ? '试运行环境已发布登记' : '正式发布',
            'obsolete' => '历史版本（已停用）',
            default => $status !== '' ? '其他状态：' . $status : '未标明状态',
        };
    }

    public static function changeReason(?string $raw): array
    {
        $raw = trim((string)$raw);
        if ($raw === '') {
            return ['summary' => '尚未填写修订说明', 'technical' => ''];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return ['summary' => $raw, 'technical' => ''];
        }

        $summary = '';
        foreach (['notice', 'change_reason', 'summary', 'description'] as $key) {
            $candidate = trim((string)($decoded[$key] ?? ''));
            if ($candidate !== '') {
                $summary = $candidate;
                break;
            }
        }
        if ($summary === '') {
            $summary = '系统已保存本次治理修订信息，详细来源见技术信息。';
        }

        return [
            'summary' => $summary,
            'technical' => (string)json_encode(
                $decoded,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
        ];
    }

    public static function structureSummary(array $summary): array
    {
        $statusLabels = [
            'draft' => '结构草稿',
            'structured' => '已形成结构',
            'reviewing' => '结构复核中',
            'trial_ready' => '结构已可试用',
            'published' => '结构已发布登记',
            'obsolete' => '结构已停用',
        ];
        $renderLabels = [
            'rendered' => '已生成可阅读正文',
            'not_rendered' => '尚未生成阅读稿',
            'pending' => '等待生成阅读稿',
            'failed' => '阅读稿生成失败',
        ];
        $status = (string)($summary['status'] ?? '');
        $renderStatus = (string)($summary['render_status'] ?? '');
        $summary['status_label'] = $statusLabels[$status] ?? ($status !== '' ? '结构状态：' . $status : '未建立结构');
        $summary['render_status_label'] = $renderLabels[$renderStatus]
            ?? ($renderStatus !== '' ? '阅读稿状态：' . $renderStatus : '未生成阅读稿');

        return $summary;
    }

    public static function onlyofficeAvailable(
        string $status,
        string $filePath,
        string $fileName,
        bool $enabled,
        string $serverUrl
    ): bool {
        if (!$enabled || trim($serverUrl) === '' || trim($filePath) === '' || $status !== 'draft') {
            return false;
        }
        $extension = strtolower(pathinfo($fileName !== '' ? $fileName : $filePath, PATHINFO_EXTENSION));

        return in_array($extension, ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'], true);
    }

    public static function nextVersion(string $currentVersion, int $currentRevision): string
    {
        $currentVersion = trim($currentVersion);
        if (preg_match('/^([A-Z])\/(\d+)$/', $currentVersion, $match) === 1) {
            $letter = $match[1];
            $minor = (int)$match[2] + 1;
            if ($minor > 9) {
                $letter = chr(ord($letter) + 1);
                $minor = 0;
            }

            return $letter . '/' . $minor;
        }
        if (preg_match('/^(.+)\/(\d+)\.(\d+)$/', $currentVersion, $match) === 1) {
            return $match[1] . '/' . $match[2] . '.' . ((int)$match[3] + 1);
        }
        if (preg_match('/^(\d+)\.(\d+)$/', $currentVersion, $match) === 1) {
            return $match[1] . '.' . ((int)$match[2] + 1);
        }

        return 'A/' . max(1, $currentRevision + 1);
    }
}
