<?php
declare(strict_types=1);

namespace app\service;

use app\model\Employee;
use app\model\EmployeeCertificate;
use app\model\FileUpload;
use app\model\RecordFormInstance;
use app\model\Training;
use think\Collection;

class TrainingEvidenceService
{
    public static function planProgress(string $planId): array
    {
        $rows = Training::where('training_plan_id', $planId)
            ->where('soft_delete', 0)
            ->select();
        $total = $rows->count();
        $completed = 0;
        foreach ($rows as $row) {
            if (($row->status ?? '') === 'completed') {
                $completed++;
            }
        }

        return [
            'total' => $total,
            'completed' => $completed,
            'open' => max(0, $total - $completed),
            'rate' => $total > 0 ? round($completed * 100 / $total, 1) : 0.0,
        ];
    }

    public static function trainingRowsForPlan(string $planId): Collection
    {
        return Training::where('training_plan_id', $planId)
            ->where('soft_delete', 0)
            ->order('training_date', 'asc')
            ->select();
    }

    public static function employeeCertificateRows(string $employeeId): Collection
    {
        return EmployeeCertificate::where('employee_id', $employeeId)
            ->where('soft_delete', 0)
            ->order('valid_until', 'asc')
            ->select();
    }

    public static function trainingRecordsForEmployee(string $employeeId): array
    {
        return \think\facade\Db::name('training_records')
            ->alias('r')
            ->join('trainings t', 't.id = r.training_id')
            ->where('r.employee_id', $employeeId)
            ->where('r.soft_delete', 0)
            ->where('t.soft_delete', 0)
            ->field('r.id,r.attendance,r.evaluation_score,r.evaluation_result,r.created,t.title,t.training_date,t.trainer,t.training_type,t.status')
            ->order('t.training_date', 'desc')
            ->select()
            ->toArray();
    }

    public static function competencyRecordsForEmployee(string $employeeId): array
    {
        return \think\facade\Db::name('competency_records')
            ->alias('c')
            ->leftJoin('employees e', 'e.id = c.assessor_id')
            ->where('c.employee_id', $employeeId)
            ->where('c.soft_delete', 0)
            ->field('c.id,c.test_item,c.method_standard,c.assessment_date,c.result,c.authorization_scope,c.valid_until,e.name assessor_name')
            ->order('c.assessment_date', 'desc')
            ->select()
            ->toArray();
    }

    public static function supervisionRecordInstances(string $employeeId): Collection
    {
        $employee = Employee::find($employeeId);
        if (!$employee) {
            return new Collection();
        }

        $terms = array_values(array_filter([
            (string)$employee->id,
            (string)($employee->name ?? ''),
            (string)($employee->employee_number ?? ''),
        ]));

        if ($terms === []) {
            return new Collection();
        }

        $query = RecordFormInstance::where(function ($q) {
                $q->whereLike('template_name', '%监督%')
                    ->whereOr('record_title', 'like', '%监督%')
                    ->whereOr('doc_number', 'like', '%31-02%')
                    ->whereOr('template_print_template_key', 'like', '%31_02%');
            });

        $query->where(function ($q) use ($terms) {
            foreach ($terms as $index => $term) {
                if ($index === 0) {
                    $q->whereLike('record_title', '%' . $term . '%')
                        ->whereOr('field_values', 'like', '%' . $term . '%');
                } else {
                    $q->whereOr('record_title', 'like', '%' . $term . '%')
                        ->whereOr('field_values', 'like', '%' . $term . '%');
                }
            }
        });

        return $query->order('created', 'desc')->select();
    }

    public static function uploadCertificateAttachment(array $file, string $certificateId, string $comment = ''): ?FileUpload
    {
        $upload = FileService::upload($file, 'employee_certificates', $certificateId);
        if (!$upload) {
            return null;
        }

        return self::registerCertificateAttachment($certificateId, $upload, $comment);
    }

    public static function registerCertificateAttachment(string $certificateId, array $upload, string $comment = ''): ?FileUpload
    {
        return FileUpload::create([
            'record' => $certificateId,
            'model_name' => 'EmployeeCertificate',
            'file_details' => $upload['file_name'] ?? '',
            'file_dir' => $upload['file_path'] ?? '',
            'file_type' => $upload['file_type'] ?? '',
            'comment' => $comment,
            'version' => 1,
            'archived' => 0,
            'publish' => 1,
            'soft_delete' => 0,
        ]);
    }

    public static function certificateAttachments(string $certificateId): Collection
    {
        return FileUpload::where('record', $certificateId)
            ->where('model_name', 'EmployeeCertificate')
            ->where('soft_delete', 0)
            ->order('created', 'desc')
            ->select();
    }

    public static function findCertificateAttachment(string $certificateId, string $fileId): ?FileUpload
    {
        return FileUpload::where('id', $fileId)
            ->where('record', $certificateId)
            ->where('model_name', 'EmployeeCertificate')
            ->where('soft_delete', 0)
            ->find();
    }
}
