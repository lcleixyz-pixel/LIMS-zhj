<?php
declare(strict_types=1);

namespace app\service;

use app\model\ControlledPrintLog;
use app\model\Document;
use RuntimeException;
use think\facade\Session;

class ControlledPrintService
{
    public static function createLog(Document $document, int $copyCount = 1, string $purpose = '', ?string $ipAddress = null): ControlledPrintLog
    {
        if ((string)$document->status !== 'published' || (int)$document->publish !== 1) {
            throw new RuntimeException('当前正式发布版本才可生成正式受控打印');
        }
        $copyCount = max(1, min(999, $copyCount));
        $printNumber = self::watermarkCode($document);
        $isTrialCopy = TrialModeService::isEnabled()
            && str_starts_with(strtoupper(trim((string)$document->doc_number)), 'SIM-');
        $watermarkPrefix = $isTrialCopy ? '试运行/非正式受控副本 ' : '受控打印 ';

        return ControlledPrintLog::create([
            'document_id' => (string)$document->id,
            'print_number' => $printNumber,
            'copy_count' => $copyCount,
            'purpose' => self::truncate(trim($purpose), 200),
            'watermark_text' => self::truncate($watermarkPrefix . $printNumber, 200),
            'printed_by' => Session::get('user.id'),
            'printed_at' => date('Y-m-d H:i:s'),
            'ip_address' => $ipAddress,
            'publish' => 1,
            'soft_delete' => 0,
        ]);
    }

    public static function watermarkCode(Document $document): string
    {
        $docNumber = preg_replace('/[^A-Za-z0-9._-]/', '-', (string)$document->doc_number);
        $docNumber = trim((string)$docNumber, '-_.');
        if ($docNumber === '') {
            $docNumber = 'DOCUMENT';
        }
        $docNumber = substr($docNumber, 0, 32);
        $suffix = strtoupper(substr(str_replace('-', '', qms_uuid()), 0, 6));

        return substr('CP-' . $docNumber . '-' . date('YmdHis') . '-' . $suffix, 0, 80);
    }

    public static function recentLogs(string $documentId, int $limit = 10)
    {
        return ControlledPrintLog::where('document_id', $documentId)
            ->where('soft_delete', 0)
            ->order('printed_at', 'desc')
            ->limit($limit)
            ->select();
    }

    private static function truncate(string $value, int $limit): string
    {
        if (function_exists('mb_strlen') && mb_strlen($value, 'UTF-8') > $limit) {
            return mb_substr($value, 0, $limit - 6, 'UTF-8') . '[截断]';
        }
        if (!function_exists('mb_strlen') && strlen($value) > $limit) {
            return substr($value, 0, $limit - 12) . '[truncated]';
        }

        return $value;
    }
}
