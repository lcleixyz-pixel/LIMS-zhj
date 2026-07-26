<?php
declare(strict_types=1);

namespace app\service;

use RuntimeException;

class PdfRenderService
{
    public static function renderUrl(string $url, string $recordId, string $title): array
    {
        return self::renderInput($url, $recordId, $title);
    }

    public static function renderHtml(string $html, string $recordId, string $title): array
    {
        $recordId = self::normalizeRecordId($recordId);
        $htmlDir = rtrim(root_path(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
            . 'runtime' . DIRECTORY_SEPARATOR . 'record-form-pdf-html' . DIRECTORY_SEPARATOR . $recordId . DIRECTORY_SEPARATOR;

        if (!is_dir($htmlDir) && !mkdir($htmlDir, 0755, true) && !is_dir($htmlDir)) {
            throw new RuntimeException('PDF 临时 HTML 目录创建失败');
        }

        $htmlPath = $htmlDir . 'render-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.html';
        if (file_put_contents($htmlPath, $html, LOCK_EX) === false) {
            throw new RuntimeException('PDF 临时 HTML 写入失败');
        }

        try {
            return self::renderInput($htmlPath, $recordId, $title);
        } finally {
            if (is_file($htmlPath)) {
                @unlink($htmlPath);
            }
        }
    }

    public static function renderHtmlPreview(string $html, string $recordId, string $title): array
    {
        $recordId = self::normalizeRecordId($recordId);
        $safeTitle = self::safeFileTitle($title);
        $root = root_path();
        $previewDir = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
            . 'runtime' . DIRECTORY_SEPARATOR . 'record-form-preview-pdf' . DIRECTORY_SEPARATOR . $recordId . DIRECTORY_SEPARATOR;
        if (!is_dir($previewDir) && !mkdir($previewDir, 0755, true) && !is_dir($previewDir)) {
            throw new RuntimeException('PDF 预览目录创建失败');
        }

        $htmlPath = $previewDir . 'preview-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.html';
        $pdfName = $safeTitle . '_preview_' . date('YmdHis') . '.pdf';
        $pdfPath = $previewDir . $pdfName;
        if (file_put_contents($htmlPath, $html, LOCK_EX) === false) {
            throw new RuntimeException('PDF 预览 HTML 写入失败');
        }

        $script = $root . 'scripts' . DIRECTORY_SEPARATOR . 'render-record-pdf.mjs';
        if (!is_file($script)) {
            throw new RuntimeException('PDF 渲染脚本不存在');
        }

        $command = sprintf(
            'cd %s && node %s %s %s 2>&1',
            escapeshellarg($root),
            escapeshellarg($script),
            escapeshellarg($htmlPath),
            escapeshellarg($pdfPath)
        );

        exec($command, $output, $code);
        if ($code !== 0 || !is_file($pdfPath)) {
            $message = self::summarizeRenderError(implode("\n", $output));

            throw new RuntimeException('PDF 预览生成失败，退出码 ' . $code . ($message === '' ? '' : '：' . $message));
        }

        return [
            'file_name' => $pdfName,
            'file_path' => 'runtime/record-form-preview-pdf/' . $recordId . '/' . $pdfName,
            'html_path' => 'runtime/record-form-preview-pdf/' . $recordId . '/' . basename($htmlPath),
        ];
    }

    /**
     * Render a runtime-only PDF component for a governed download package.
     *
     * @return array{file_name:string,absolute_path:string}
     */
    public static function renderHtmlTemporary(string $html, string $recordId, string $title): array
    {
        $recordId = self::normalizeRecordId($recordId);
        $safeTitle = self::safeFileTitle($title);
        $root = root_path();
        $outputDir = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
            . 'runtime' . DIRECTORY_SEPARATOR . 'current-record-package-components'
            . DIRECTORY_SEPARATOR . $recordId . DIRECTORY_SEPARATOR;
        if (!is_dir($outputDir) && !mkdir($outputDir, 0755, true) && !is_dir($outputDir)) {
            throw new RuntimeException('临时更正附页目录创建失败');
        }

        $suffix = date('YmdHis') . '-' . bin2hex(random_bytes(4));
        $htmlPath = $outputDir . 'appendix-' . $suffix . '.html';
        $pdfName = $safeTitle . '-' . $suffix . '.pdf';
        $pdfPath = $outputDir . $pdfName;
        if (file_put_contents($htmlPath, $html, LOCK_EX) === false) {
            throw new RuntimeException('临时更正附页 HTML 写入失败');
        }

        $script = $root . 'scripts' . DIRECTORY_SEPARATOR . 'render-record-pdf.mjs';
        if (!is_file($script)) {
            @unlink($htmlPath);
            throw new RuntimeException('PDF 渲染脚本不存在');
        }

        $command = sprintf(
            'cd %s && node %s %s %s 2>&1',
            escapeshellarg($root),
            escapeshellarg($script),
            escapeshellarg($htmlPath),
            escapeshellarg($pdfPath)
        );

        try {
            exec($command, $output, $code);
        } finally {
            if (is_file($htmlPath)) {
                @unlink($htmlPath);
            }
        }
        if ($code !== 0 || !is_file($pdfPath)) {
            $message = self::summarizeRenderError(implode("\n", $output));

            throw new RuntimeException('临时更正附页 PDF 生成失败，退出码 ' . $code
                . ($message === '' ? '' : '：' . $message));
        }

        return [
            'file_name' => $pdfName,
            'absolute_path' => $pdfPath,
        ];
    }

    /**
     * Render a versioned clean PDF that represents all currently approved corrections.
     *
     * @return array{file_name:string,file_path:string,absolute_path:string}
     */
    public static function renderCurrentHtml(
        string $html,
        string $recordId,
        string $title,
        int $correctionCount
    ): array {
        $recordId = self::normalizeRecordId($recordId);
        if ($correctionCount < 1) {
            throw new RuntimeException('当前状态 PDF 至少需要一条已批准更正');
        }

        $safeTitle = self::safeFileTitle($title);
        $revision = RecordFormCurrentStateService::revisionNumber($correctionCount);
        $root = root_path();
        $relativeDir = 'uploads/record-form-current-pdf/' . $recordId;
        $outputDir = rtrim(public_path(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
            . 'uploads' . DIRECTORY_SEPARATOR . 'record-form-current-pdf' . DIRECTORY_SEPARATOR
            . $recordId . DIRECTORY_SEPARATOR;
        if (!is_dir($outputDir) && !mkdir($outputDir, 0755, true) && !is_dir($outputDir)) {
            throw new RuntimeException('当前状态 PDF 输出目录创建失败');
        }

        $suffix = date('YmdHis') . '-' . bin2hex(random_bytes(4));
        $htmlPath = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
            . 'runtime' . DIRECTORY_SEPARATOR . 'record-form-current-pdf-html' . DIRECTORY_SEPARATOR
            . $recordId . DIRECTORY_SEPARATOR . 'current-' . $revision . '-' . $suffix . '.html';
        $htmlDir = dirname($htmlPath);
        if (!is_dir($htmlDir) && !mkdir($htmlDir, 0755, true) && !is_dir($htmlDir)) {
            throw new RuntimeException('当前状态 PDF 临时目录创建失败');
        }

        $pdfName = $safeTitle . '_current_' . $revision . '_' . $suffix . '.pdf';
        $pdfPath = $outputDir . $pdfName;
        if (file_put_contents($htmlPath, $html, LOCK_EX) === false) {
            throw new RuntimeException('当前状态 PDF 临时 HTML 写入失败');
        }

        $script = $root . 'scripts' . DIRECTORY_SEPARATOR . 'render-record-pdf.mjs';
        if (!is_file($script)) {
            @unlink($htmlPath);
            throw new RuntimeException('PDF 渲染脚本不存在');
        }
        $command = sprintf(
            'cd %s && node %s %s %s 2>&1',
            escapeshellarg($root),
            escapeshellarg($script),
            escapeshellarg($htmlPath),
            escapeshellarg($pdfPath)
        );

        try {
            exec($command, $output, $code);
        } finally {
            if (is_file($htmlPath)) {
                @unlink($htmlPath);
            }
        }
        if ($code !== 0 || !is_file($pdfPath)) {
            $message = self::summarizeRenderError(implode("\n", $output));
            throw new RuntimeException('当前状态 PDF 生成失败，退出码 ' . $code
                . ($message === '' ? '' : '：' . $message));
        }

        return [
            'file_name' => $pdfName,
            'file_path' => $relativeDir . '/' . $pdfName,
            'absolute_path' => $pdfPath,
        ];
    }

    private static function renderInput(string $input, string $recordId, string $title): array
    {
        $recordId = self::normalizeRecordId($recordId);

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
            escapeshellarg($input),
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

    private static function normalizeRecordId(string $recordId): string
    {
        $recordId = trim($recordId);
        if ($recordId === '' || preg_match('/\A[a-zA-Z0-9_-]+\z/', $recordId) !== 1) {
            throw new RuntimeException('非法记录标识：' . ($recordId === '' ? '空' : $recordId));
        }

        return $recordId;
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
