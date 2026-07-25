<?php
declare(strict_types=1);

namespace app\service;

use RuntimeException;
use ZipArchive;

class RecordFormCurrentPackageService
{
    /**
     * Build a governed ZIP without modifying the frozen record or original PDF.
     *
     * @return array{file_path:string,file_name:string,original_sha256:string,entries:list<string>}
     */
    public static function build(
        string $recordId,
        string $recordTitle,
        string $originalPdfPath,
        string $originalPdfName,
        string $correctionPdfPath,
        int $correctionCount,
        string $latestCorrectionAt,
        ?string $outputRoot = null
    ): array {
        $recordId = trim($recordId);
        if ($recordId === '' || preg_match('/\A[a-zA-Z0-9_-]+\z/', $recordId) !== 1) {
            throw new RuntimeException('当前完整记录包的记录标识无效');
        }
        if ($correctionCount < 1) {
            throw new RuntimeException('当前记录没有已批准更正，不能生成完整记录包');
        }
        foreach ([$originalPdfPath, $correctionPdfPath] as $pdfPath) {
            if (!is_file($pdfPath) || !is_readable($pdfPath)) {
                throw new RuntimeException('完整记录包所需 PDF 不存在或不可读取');
            }
        }
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('ZIP 扩展未启用，不能生成完整记录包');
        }

        $outputRoot = $outputRoot !== null
            ? rtrim($outputRoot, DIRECTORY_SEPARATOR)
            : rtrim(root_path(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
                . 'runtime' . DIRECTORY_SEPARATOR . 'current-record-packages' . DIRECTORY_SEPARATOR . $recordId;
        if (!is_dir($outputRoot) && !mkdir($outputRoot, 0755, true) && !is_dir($outputRoot)) {
            throw new RuntimeException('当前完整记录包目录创建失败');
        }

        $safeTitle = self::safeFileName($recordTitle, 'record');
        $originalName = self::safeFileName(
            pathinfo($originalPdfName, PATHINFO_FILENAME),
            'original-record'
        ) . '.pdf';
        $stamp = date('YmdHis');
        $packageName = '当前完整记录包-' . $safeTitle . '-' . $stamp . '-' . bin2hex(random_bytes(3)) . '.zip';
        $packagePath = $outputRoot . DIRECTORY_SEPARATOR . $packageName;
        $entries = [
            '00-阅读说明.txt',
            '01-原始记录-' . $originalName,
            '02-更正附页-' . $safeTitle . '.pdf',
        ];
        $originalHash = (string)hash_file('sha256', $originalPdfPath);
        $note = implode("\n", [
            '当前完整记录包阅读说明',
            '',
            '记录标题：' . trim($recordTitle),
            '记录标识：' . $recordId,
            '本包生成时间：' . date('Y-m-d H:i:s'),
            '更正记录条数：' . $correctionCount,
            '最后更正时间：' . ($latestCorrectionAt !== '' ? $latestCorrectionAt : '未记录'),
            '原始 PDF SHA-256：' . $originalHash,
            '',
            '归档要求：原始 PDF 始终保持冻结；更正附页须与原记录一并保存。',
            '纸质记录已打印时，请打印更正附页并附在原纸质记录之后。',
            '',
        ]);

        $zip = new ZipArchive();
        $opened = $zip->open($packagePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($opened !== true) {
            throw new RuntimeException('当前完整记录包创建失败，错误码：' . (string)$opened);
        }

        $success = $zip->addFromString($entries[0], $note)
            && $zip->addFile($originalPdfPath, $entries[1])
            && $zip->addFile($correctionPdfPath, $entries[2]);
        $closed = $zip->close();
        if (!$success || !$closed || !is_file($packagePath)) {
            if (is_file($packagePath)) {
                @unlink($packagePath);
            }
            throw new RuntimeException('当前完整记录包写入失败');
        }

        return [
            'file_path' => $packagePath,
            'file_name' => $packageName,
            'original_sha256' => $originalHash,
            'entries' => $entries,
        ];
    }

    private static function safeFileName(string $value, string $fallback): string
    {
        $safe = preg_replace('/[^\p{L}\p{N}._-]+/u', '_', trim($value)) ?? '';
        $safe = trim($safe, '._-');

        return $safe !== '' ? $safe : $fallback;
    }
}
