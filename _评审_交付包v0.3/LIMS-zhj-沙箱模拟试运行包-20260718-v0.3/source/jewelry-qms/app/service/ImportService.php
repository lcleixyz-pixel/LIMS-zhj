<?php
declare(strict_types=1);

namespace app\service;

use app\model\Department;
use app\model\Document;
use app\model\Employee;
use app\model\Equipment;
use think\facade\Config;
use think\facade\Session;

class ImportService
{
    public static function parseCsv(string $filePath): array
    {
        $rows = [];
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return $rows;
        }
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }
        $header = null;
        while (($data = fgetcsv($handle)) !== false) {
            if ($header === null) {
                $header = array_map(fn ($h) => trim((string) $h), $data);
                continue;
            }
            if (count($data) < count($header)) {
                $data = array_pad($data, count($header), '');
            }
            $rows[] = array_combine($header, array_slice($data, 0, count($header)));
        }
        fclose($handle);

        return $rows;
    }

    public static function importDocuments(array $rows): array
    {
        $imported = 0;
        $errors = [];
        $companyId = Config::get('qms.company_id');

        foreach ($rows as $i => $row) {
            $docNumber = trim($row['文件编号'] ?? $row['doc_number'] ?? '');
            $title = trim($row['文件名称'] ?? $row['title'] ?? '');
            if ($docNumber === '' || $title === '') {
                $errors[] = '第' . ($i + 2) . '行：文件编号或名称为空';
                continue;
            }
            if (Document::where('doc_number', $docNumber)->where('soft_delete', 0)->find()) {
                $errors[] = '第' . ($i + 2) . "行：编号 {$docNumber} 已存在";
                continue;
            }
            $level = (int) ($row['层级'] ?? $row['level'] ?? 2);
            $deptName = trim($row['归口部门'] ?? '');
            $deptId = $deptName ? Department::where('name', $deptName)->value('id') : null;

            Document::create([
                'id' => qms_uuid(),
                'company_id' => $companyId,
                'level' => $level,
                'doc_number' => $docNumber,
                'title' => $title,
                'version' => $row['版本'] ?? $row['version'] ?? 'A/0',
                'department_id' => $deptId,
                'effective_date' => $row['生效日期'] ?? $row['effective_date'] ?? null,
                'status' => 'draft',
                'publish' => 0,
                'soft_delete' => 0,
                'created_by' => Session::get('user.id'),
            ]);
            $imported++;
        }

        return ['imported' => $imported, 'errors' => $errors];
    }

    public static function importEquipments(array $rows): array
    {
        $imported = 0;
        $errors = [];
        $companyId = Config::get('qms.company_id');

        foreach ($rows as $i => $row) {
            $number = trim($row['设备编号'] ?? $row['equipment_number'] ?? '');
            $name = trim($row['设备名称'] ?? $row['name'] ?? '');
            if ($number === '' || $name === '') {
                $errors[] = '第' . ($i + 2) . '行：设备编号或名称为空';
                continue;
            }
            if (Equipment::where('equipment_number', $number)->where('soft_delete', 0)->find()) {
                $errors[] = '第' . ($i + 2) . "行：编号 {$number} 已存在";
                continue;
            }
            Equipment::create([
                'id' => qms_uuid(),
                'company_id' => $companyId,
                'equipment_number' => $number,
                'name' => $name,
                'model' => $row['型号'] ?? $row['model'] ?? null,
                'manufacturer' => $row['制造商'] ?? $row['manufacturer'] ?? null,
                'location' => $row['位置'] ?? $row['location'] ?? null,
                'calibration_required' => 1,
                'calibration_cycle_months' => (int) ($row['校准周期月'] ?? 12),
                'status' => 'active',
                'publish' => 1,
                'soft_delete' => 0,
                'created_by' => Session::get('user.id'),
            ]);
            $imported++;
        }

        return ['imported' => $imported, 'errors' => $errors];
    }

    public static function importEmployees(array $rows): array
    {
        $imported = 0;
        $errors = [];
        $companyId = Config::get('qms.company_id');

        foreach ($rows as $i => $row) {
            $number = trim($row['工号'] ?? $row['employee_number'] ?? '');
            $name = trim($row['姓名'] ?? $row['name'] ?? '');
            if ($name === '') {
                $errors[] = '第' . ($i + 2) . '行：姓名为空';
                continue;
            }
            $deptName = trim($row['部门'] ?? '');
            $deptId = $deptName ? Department::where('name', $deptName)->value('id') : null;

            Employee::create([
                'id' => qms_uuid(),
                'company_id' => $companyId,
                'employee_number' => $number ?: ('E' . date('ymd') . str_pad((string) ($imported + 1), 3, '0', STR_PAD_LEFT)),
                'name' => $name,
                'department_id' => $deptId,
                'email' => $row['邮箱'] ?? $row['email'] ?? null,
                'phone' => $row['电话'] ?? $row['phone'] ?? null,
                'entry_date' => $row['入职日期'] ?? $row['entry_date'] ?? null,
                'publish' => 1,
                'soft_delete' => 0,
                'created_by' => Session::get('user.id'),
            ]);
            $imported++;
        }

        return ['imported' => $imported, 'errors' => $errors];
    }
}
