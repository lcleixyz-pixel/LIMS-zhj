<?php
declare(strict_types=1);

namespace app\service;

use RuntimeException;

class PdfRenderService
{
    public static function renderUrl(string $url, string $recordId, string $title): array
    {
        $recordId = trim($recordId);
        if ($recordId === '' || preg_match('/\A[a-zA-Z0-9_-]+\z/', $recordId) !== 1) {
            throw new RuntimeException('非法记录标识：' . ($recordId === '' ? '空' : $recordId));
        }

        $safeTitle = self::safeFileTitle($title);
        $relativeDir = 'uploads/record-form-pdf/' . $recordId;
        $absoluteDir = rtrim(public_path(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
            . 'uploads' . DIRECTORY_SEPARATOR . 'record-form-pdf' . DIRECTORY_SEPARATOR . $recordId . DIRECTORY_SEPARATOR;

        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0755, true) && !is_dir($absoluteDir)) {
            throw new RuntimeException('PDF 输出目录创建失败：' . $relativeDir);
        }

        $fileName = $safeTitle . '_' . date('YmdHis') . '.pdf';
        $absolutePath = $absoluteDir . $fileName;
        $root = root_path();
        $script = $root . 'scripts' . DIRECTORY_SEPARATOR . 'render-record-pdf.mjs';

        if (!is_file($script)) {
            throw new RuntimeException('PDF 渲染脚本不存在');
        }

        $command = sprintf(
            'cd %s && node %s %s %s 2>&1',
            escapeshellarg($root),
            escapeshellarg($script),
            escapeshellarg($url),
            escapeshellarg($absolutePath)
        );

        exec($command, $output, $code);
        if ($code !== 0 || !is_file($absolutePath)) {
            $message = self::summarizeRenderError(implode("\n", $output));

            throw new RuntimeException('PDF 生成失败，退出码 ' . $code . ($message === '' ? '' : '：' . $message));
        }

        return [
            'file_name' => $fileName,
            'file_path' => $relativeDir . '/' . $fileName,
        ];
    }

    private static function safeFileTitle(string $title): string
    {
        $safeTitle = preg_replace('/[^a-zA-Z0-9._-]+/', '_', trim($title)) ?? '';
        $safeTitle = trim($safeTitle, '._-');

        return $safeTitle === '' ? 'record_form' : $safeTitle;
    }

    private static function summarizeRenderError(string $message): string
    {
        $message = trim($message);
        foreach ([
            rtrim(public_path(), DIRECTORY_SEPARATOR) => '[public-root]',
            rtrim(root_path(), DIRECTORY_SEPARATOR) => '[app-root]',
        ] as $absolute => $label) {
            if ($absolute === '') {
                continue;
            }
            $message = str_replace([
                $absolute,
                str_replace(DIRECTORY_SEPARATOR, '/', $absolute),
            ], $label, $message);
        }

        if (strlen($message) > 1200) {
            $message = '...' . substr($message, -1197);
        }

        return $message;
    }
}
