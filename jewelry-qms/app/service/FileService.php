<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Config;
use think\exception\HttpException;

class FileService
{
    public static function upload(array $file, string $subdir, string $recordId): ?array
    {
        if (empty($file['name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = Config::get('qms.upload.allowed_extensions', []);
        if (!in_array($ext, $allowed)) {
            return null;
        }

        $maxSize = Config::get('qms.upload.max_size', 20 * 1024 * 1024);
        if ($file['size'] > $maxSize) {
            return null;
        }

        $dir = public_path() . 'uploads' . DIRECTORY_SEPARATOR . $subdir . DIRECTORY_SEPARATOR . $recordId . DIRECTORY_SEPARATOR;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $safeName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
        $path = $dir . $safeName;

        if (move_uploaded_file($file['tmp_name'], $path)) {
            return [
                'file_name' => $file['name'],
                'file_path' => 'uploads/' . $subdir . '/' . $recordId . '/' . $safeName,
                'file_type' => $ext,
            ];
        }
        return null;
    }

    public static function download(string $filePath, string $displayName): void
    {
        self::stream($filePath, $displayName, 'attachment');
    }

    public static function preview(string $filePath, string $displayName): void
    {
        self::stream($filePath, $displayName, 'inline');
    }

    private static function stream(string $filePath, string $displayName, string $disposition): void
    {
        $fullPath = public_path() . $filePath;
        if (!file_exists($fullPath)) {
            throw new HttpException(404, '文件未找到');
        }
        header('Content-Type: ' . self::contentType($fullPath));
        header('Content-Disposition: ' . $disposition . '; filename="' . $displayName . '"');
        header('Content-Length: ' . filesize($fullPath));
        readfile($fullPath);
        exit;
    }

    private static function contentType(string $fullPath): string
    {
        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        return match ($ext) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'txt' => 'text/plain; charset=utf-8',
            'html', 'htm' => 'text/html; charset=utf-8',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            default => 'application/octet-stream',
        };
    }
}
