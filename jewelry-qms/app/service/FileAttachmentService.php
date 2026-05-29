<?php
declare(strict_types=1);

namespace app\service;

use app\model\FileUpload;

class FileAttachmentService
{
    public static function upload(array $file, string $modelName, string $recordId, string $subdir, string $comment = ''): ?FileUpload
    {
        $upload = FileService::upload($file, $subdir, $recordId);
        if (!$upload) {
            return null;
        }

        return self::registerExistingFile(
            $modelName,
            $recordId,
            (string)$upload['file_path'],
            (string)$upload['file_name'],
            $comment,
            (string)$upload['file_type']
        );
    }

    public static function registerExistingFile(
        string $modelName,
        string $recordId,
        string $filePath,
        string $displayName,
        string $comment = '',
        ?string $fileType = null
    ): FileUpload {
        return FileUpload::create([
            'id' => qms_uuid(),
            'record' => $recordId,
            'model_name' => $modelName,
            'file_details' => $displayName,
            'file_dir' => $filePath,
            'file_type' => $fileType ?: strtolower(pathinfo($displayName, PATHINFO_EXTENSION)),
            'version' => 1,
            'archived' => 0,
            'comment' => $comment,
            'publish' => 1,
            'soft_delete' => 0,
            'created' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function attachmentsFor(string $modelName, string $recordId): array
    {
        return FileUpload::where('model_name', $modelName)
            ->where('record', $recordId)
            ->where('soft_delete', 0)
            ->where('publish', 1)
            ->order('created', 'desc')
            ->select()
            ->toArray();
    }

    public static function findAttachment(string $attachmentId, string $modelName, string $recordId): ?FileUpload
    {
        return FileUpload::where('id', $attachmentId)
            ->where('model_name', $modelName)
            ->where('record', $recordId)
            ->where('soft_delete', 0)
            ->where('publish', 1)
            ->find();
    }
}
