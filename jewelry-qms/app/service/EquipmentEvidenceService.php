<?php
declare(strict_types=1);

namespace app\service;

use app\model\Calibration;
use app\model\Equipment;
use app\model\FileUpload;
use app\model\RecordFormInstance;
use think\facade\Db;

class EquipmentEvidenceService
{
    public static function periodicCheckInstances(string $equipmentId, int $limit = 10): array
    {
        $equipment = Equipment::find($equipmentId);
        if (!$equipment) {
            return [];
        }

        $equipmentNumber = trim((string)$equipment->equipment_number);
        $equipmentName = trim((string)$equipment->name);
        if ($equipmentNumber === '' && $equipmentName === '') {
            return [];
        }

        $query = RecordFormInstance::where('status', '<>', 'voided')
            ->where(function ($q) {
                $q->where('template_name', 'like', '%期间核查%')
                    ->whereOr('template_module', 'like', '%期间核查%')
                    ->whereOr('record_title', 'like', '%期间核查%')
                    ->whereOr('doc_number', 'like', 'XZTC/BG-04%');
            })
            ->where(function ($q) use ($equipmentNumber, $equipmentName) {
                if ($equipmentNumber !== '') {
                    $q->where('field_values', 'like', '%' . $equipmentNumber . '%')
                        ->whereOr('record_title', 'like', '%' . $equipmentNumber . '%');
                }
                if ($equipmentName !== '') {
                    $q->whereOr('field_values', 'like', '%' . $equipmentName . '%')
                        ->whereOr('record_title', 'like', '%' . $equipmentName . '%');
                }
            })
            ->order('created', 'desc')
            ->limit($limit);

        return $query->select()->toArray();
    }

    public static function registerCalibrationCertificate(
        string $calibrationId,
        string $filePath,
        string $displayName,
        string $comment = '',
        ?string $fileType = null
    ): FileUpload {
        return FileUpload::create([
            'id' => qms_uuid(),
            'record' => $calibrationId,
            'model_name' => 'Calibration',
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

    public static function uploadCalibrationCertificate(array $file, string $calibrationId, string $comment = ''): ?FileUpload
    {
        $upload = FileService::upload($file, 'calibrations', $calibrationId);
        if (!$upload) {
            return null;
        }

        return self::registerCalibrationCertificate(
            $calibrationId,
            (string)$upload['file_path'],
            (string)$upload['file_name'],
            $comment,
            (string)$upload['file_type']
        );
    }

    public static function calibrationCertificateAttachments(string $calibrationId): array
    {
        return FileUpload::where('model_name', 'Calibration')
            ->where('record', $calibrationId)
            ->where('soft_delete', 0)
            ->where('publish', 1)
            ->order('created', 'desc')
            ->select()
            ->toArray();
    }

    public static function findCalibrationCertificate(string $calibrationId, string $fileId): ?FileUpload
    {
        return FileUpload::where('id', $fileId)
            ->where('model_name', 'Calibration')
            ->where('record', $calibrationId)
            ->where('soft_delete', 0)
            ->where('publish', 1)
            ->find();
    }

    public static function equipmentAuthorizationRows(string $equipmentId): array
    {
        return Db::name('equipment_authorizations')
            ->alias('ea')
            ->leftJoin('employees e', 'e.id = ea.employee_id')
            ->field('ea.*, e.name AS employee_name, e.employee_number')
            ->where('ea.equipment_id', $equipmentId)
            ->where('ea.soft_delete', 0)
            ->order('ea.authorized_date', 'desc')
            ->select()
            ->toArray();
    }

    public static function calibrationEquipmentId(string $calibrationId): string
    {
        $calibration = Calibration::find($calibrationId);

        return $calibration ? (string)$calibration->equipment_id : '';
    }
}
